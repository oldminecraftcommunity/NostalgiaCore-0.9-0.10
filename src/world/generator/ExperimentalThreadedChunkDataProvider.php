<?php

class ExperimentalThreadedChunkDataProvider extends ThreadedChunkDataProvider
{
	/**
	 * @var Random
	 */
	public $random;
	/**
	 * @var BiomeSelector
	 */
	public $selector;
	
	public $genPopulators;
	public $levelSeed;
	public $noiseBase;
	
	public $hasKernel = false;
	public $gaussianKernel;
	public static $SMOOTH_SIZE = 2;
	
	private static $biomes; //making it not static will autoconvert it to volatile which is bad
	public function __construct($seed){
		parent::__construct();
		$this->levelSeed = $seed;
		$this->random = new Random($seed);
		$this->selector = new BiomeSelector($this->random, BiomeSelector::$biomes[BIOME_PLAINS]);
		$this->noiseBase = new NoiseGeneratorPerlin($this->random, 4);
		$this->biomesvolatile = [];
		foreach(BiomeSelector::$biomes as $k => $b){
			$this->biomesvolatile[$k] = $b;
		}
		$this->startThread();
	}
	
	public function threadInit(){
		$this->gaussianKernel = [];
		
		$bellSize = 1 / self::$SMOOTH_SIZE;
		$bellHeight = 2 * self::$SMOOTH_SIZE;
		
		for($sx = -self::$SMOOTH_SIZE; $sx <= self::$SMOOTH_SIZE; ++$sx){
			$this->gaussianKernel[$sx + self::$SMOOTH_SIZE] = [];
			
			for($sz = -self::$SMOOTH_SIZE; $sz <= self::$SMOOTH_SIZE; ++$sz){
				$bx = $bellSize * $sx;
				$bz = $bellSize * $sz;
				$this->gaussianKernel[$sx + self::$SMOOTH_SIZE][$sz + self::$SMOOTH_SIZE] = $bellHeight * exp(-($bx * $bx + $bz * $bz) / 2);
			}
		}
		
		ThreadedBiomeSelector::saveState($this->selector); //using instances makes pickBiome 10x slower
		$bs = $this->biomesvolatile;
		self::$biomes = [];
		foreach($bs as $id => $v){
			self::$biomes[$id] = $v;
		}
		
		$this->hasKernel = true;
	}
	
	public function pickBiome(int $x, int $z){
		$hash = $x * 2345803 ^ $z * 9236449 ^ $this->levelSeed;
		$hash *= $hash + 223;
		$xNoise = ((int)$hash) >> 20 & 3; //why dont u have types for local variables??
		$zNoise = ((int)$hash) >> 22 & 3;
		if($xNoise == 3){
			$xNoise = 1;
		}
		if($zNoise == 3){
			$zNoise = 1;
		}
		return self::$biomes[ThreadedBiomeSelector::pickBiomeID($x + $xNoise - 1, $z + $zNoise - 1)] ?? $this->selector->fallback; //$this->selector->pickBiomeID($x + $xNoise - 1, $z + $zNoise - 1)] ?? $this->selector->fallback;
	}
	
	public function getChunkData($chunkX, $chunkZ){
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->levelSeed);
		$noiseArray = ExperimentalGenerator::getFastNoise3D($this->noiseBase, 16, 128, 16, 4, 8, 4, $chunkX * 16, 0, $chunkZ * 16);
		$biomeCache = [];
		$biomedata = [];
		$data = [];
		$biomes = "";
		for($chunkY = 0; $chunkY < 8; ++$chunkY){
			$chunk = "";
			
			$startY = $chunkY << 4;
			$endY = $startY + 16;
			for($z = 0; $z < 16; ++$z){
				for($x = 0; $x < 16; ++$x){
					$minSum = 0;
					$maxSum = 0;
					$weightSum = 0;
					//$this->level->level->setBiomeId(($chunkX << 4) + $x, ($chunkZ << 4) + $z, $biome->id); //TODO biome array
					
					if($chunkY == 0){
						$biome = $this->pickBiome($chunkX * 16 + $x, $chunkZ * 16 + $z);
						$biomes .= chr($biome->id);
						for($sx = -self::$SMOOTH_SIZE; $sx <= self::$SMOOTH_SIZE; ++$sx){
							for($sz = -self::$SMOOTH_SIZE; $sz <= self::$SMOOTH_SIZE; ++$sz){
								$weight = $this->gaussianKernel[$sx + self::$SMOOTH_SIZE][$sz + self::$SMOOTH_SIZE];
								
								if($sx === 0 and $sz === 0){
									$adjacent = $biome;
								}else{
									$index = ($chunkX * 16 + $x + $sx).":".($chunkZ * 16 + $z + $sz);
									if(isset($biomeCache[$index])){
										$adjacent = $biomeCache[$index];
									}else{
										$biomeCache[$index] = $adjacent = $this->pickBiome($chunkX * 16 + $x + $sx, $chunkZ * 16 + $z + $sz);
									}
								}
								
								$minSum += ($adjacent->minY - 1) * $weight;
								$maxSum += $adjacent->maxY * $weight;
								
								$weightSum += $weight;
							}
						}
						$biomedata[$z*16+$x] = [$minSum, $maxSum, $weightSum];
					}else{
						$biome = self::$biomes[ord($biomes[$z*16+$x])];
						$minSum = $biomedata[$z*16+$x][0];
						$maxSum = $biomedata[$z*16+$x][1];
						$weightSum = $biomedata[$z*16+$x][2];
					}
					
					$minSum /= $weightSum;
					$maxSum /= $weightSum;
					for($y = $startY; $y < $endY; ++$y){
						if($y == 0){
							$chunk .= "\x07";
							continue;
						}
						$noiseAdjustment = 2 * (($maxSum - $y) / ($maxSum - $minSum)) - 1;
						$caveLevel = $minSum - 10;
						$distAboveCaveLevel = $y - $caveLevel > 0 ? $y - $caveLevel : 0; //max(0, $y - $caveLevel); // must be positive, looks like max is slower
						$noiseAdjustment = ($noiseAdjustment < (0.4 + ($distAboveCaveLevel / 10))) ? $noiseAdjustment : (0.4 + ($distAboveCaveLevel / 10)); //min($noiseAdjustment, 0.4 + ($distAboveCaveLevel / 10));
						$noiseValue = $noiseArray[$x][$z][$y] + $noiseAdjustment;
						$chunk .= (($noiseValue > 0) ? "\x01" : (($y <= $this->waterHeight) ? "\x09" : "\x00"));
					}
					$chunk .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
					$chunk .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; //light
					$chunk .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; //more light
				}
				
			}
			
			$data[$chunkY] = $chunk;
		}
		
		$data["biomes"] = $biomes;
		
		return $data;
	}

}

