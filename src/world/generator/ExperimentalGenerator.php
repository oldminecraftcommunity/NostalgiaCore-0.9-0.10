<?php
/***REM_START***/
require_once("NewLevelGenerator.php");
/***REM_END***/
/**
 * @author Genisys & PocketMine
 * Thank You <3
 */
class ExperimentalGenerator implements NewLevelGenerator{
	/**
	 * @var Populator[]
	 */
	public $populators = array();
	/**
	 * @var Populator[]
	 */
	public $genPopulators = array();
	public $level;
	public $random;
	public $waterHeight = 63;
	public $noiseHills;
	public $noisePatches;
	public $noisePatchesSmall;
	public $noiseBase;
	public $selector;
	
	public static $GAUSSIAN_KERNEL = null;
	public static $SMOOTH_SIZE = 2;
	
	public function __construct(array $options = array()){
		ExperimentalGenerator::generateKernel();
	}
	
	public static function generateKernel(){
		ExperimentalGenerator::$GAUSSIAN_KERNEL = [];
		
		$bellSize = 1 / ExperimentalGenerator::$SMOOTH_SIZE;
		$bellHeight = 2 * ExperimentalGenerator::$SMOOTH_SIZE;
		
		for($sx = -ExperimentalGenerator::$SMOOTH_SIZE; $sx <= ExperimentalGenerator::$SMOOTH_SIZE; ++$sx){
			ExperimentalGenerator::$GAUSSIAN_KERNEL[$sx + ExperimentalGenerator::$SMOOTH_SIZE] = [];
			
			for($sz = -ExperimentalGenerator::$SMOOTH_SIZE; $sz <= ExperimentalGenerator::$SMOOTH_SIZE; ++$sz){
				$bx = $bellSize * $sx;
				$bz = $bellSize * $sz;
				ExperimentalGenerator::$GAUSSIAN_KERNEL[$sx + ExperimentalGenerator::$SMOOTH_SIZE][$sz + ExperimentalGenerator::$SMOOTH_SIZE] = $bellHeight * exp(-($bx * $bx + $bz * $bz) / 2);
			}
		}
	}
	
	public function getSettings(){
		return array();
	}
	
	public function init(Level $level, Random $random){
		$this->level = $level;
		$this->random = $random;
		$this->random->setSeed($this->level->level->getSeed());
		$this->noiseBase = new NoiseGeneratorPerlin($this->random, 4);
		$this->selector = new BiomeSelector($this->random, BiomeSelector::$biomes[BIOME_PLAINS]);
		$ores = new OrePopulator();
		$ores->setOreTypes(array(
			new OreType(new CoalOreBlock(), 20, 16, 0, 128),
			new OreType(new IronOreBlock(), 20, 8, 0, 64),
			new OreType(new RedstoneOreBlock(), 8, 7, 0, 16),
			new OreType(new LapisOreBlock(), 1, 6, 0, 32),
			new OreType(new GoldOreBlock(), 2, 8, 0, 32),
			new OreType(new DiamondOreBlock(), 1, 7, 0, 16),
			new OreType(new EmeraldOreBlock(), 1, 2, 0, 16), //TODO vanilla
			
			new OreType(new DirtBlock(), 20, 32, 0, 128),
			new OreType(new GravelBlock(), 10, 16, 0, 128),
			new OreType(new StoneBlock(1), 12, 16, 0, 128),
			new OreType(new StoneBlock(3), 12, 16, 0, 128),
			new OreType(new StoneBlock(5), 12, 16, 0, 128),
		));
		$this->populators[] = $ores;
		$this->genPopulators[] = new GroundCover();
		$trees = new TreePopulator();
		$trees->setBaseAmount(3);
		$trees->setRandomAmount(0);
		$this->populators[] = $trees;
		
		$tallGrass = new TallGrassPopulator();
		$tallGrass->setBaseAmount(5);
		$tallGrass->setRandomAmount(0);
		$this->populators[] = $tallGrass;
		$this->caveGenerator = new CaveGenerator($this->level->getSeed());
	}
	
	public function pickBiome(int $x, int $z){
		$hash = $x * 2345803 ^ $z * 9236449 ^ $this->level->level->getSeed();
		$hash *= $hash + 223;
		$xNoise = ((int)$hash) >> 20 & 3; //why dont u have types for local variables??
		$zNoise = ((int)$hash) >> 22 & 3;
		if($xNoise == 3){
			$xNoise = 1;
		}
		if($zNoise == 3){
			$zNoise = 1;
		}
		return $this->selector->pickBiome($x + $xNoise - 1, $z + $zNoise - 1);
	}
	
	public function generateChunk($chunkX, $chunkZ){
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->level->getSeed());
		$noiseArray = ExperimentalGenerator::getFastNoise3D($this->noiseBase, 16, 128, 16, 4, 8, 4, $chunkX * 16, 0, $chunkZ * 16);
		$biomeCache = [];
		$biomedata = [];
		for($chunkY = 0; $chunkY < 8; ++$chunkY){
			$chunk = "";
			$startY = $chunkY << 4;
			$endY = $startY + 16;
			for($z = 0; $z < 16; ++$z){
				for($x = 0; $x < 16; ++$x){
					$minSum = 0;
					$maxSum = 0;
					$weightSum = 0;
					if($chunkY == 0){
						$biome = $this->pickBiome($chunkX * 16 + $x, $chunkZ * 16 + $z);
						$this->level->level->setBiomeId(($chunkX << 4) + $x, ($chunkZ << 4) + $z, $biome->id);
						for($sx = -ExperimentalGenerator::$SMOOTH_SIZE; $sx <= ExperimentalGenerator::$SMOOTH_SIZE; ++$sx){
							for($sz = -ExperimentalGenerator::$SMOOTH_SIZE; $sz <= ExperimentalGenerator::$SMOOTH_SIZE; ++$sz){
								$weight = ExperimentalGenerator::$GAUSSIAN_KERNEL[$sx + ExperimentalGenerator::$SMOOTH_SIZE][$sz + ExperimentalGenerator::$SMOOTH_SIZE];
								
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
						$biome = BiomeSelector::$biomes[$this->level->level->getBiomeId(($chunkX << 4) + $x, ($chunkZ << 4) + $z)];
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
			$this->level->setMiniChunk($chunkX, $chunkZ, $chunkY, $chunk);
		}
		
		foreach($this->genPopulators as $pop){
			$pop->populate($this->level, $chunkX, $chunkZ, $this->random);
		}
		
		$this->caveGenerator->generate($this->level, $chunkX, $chunkZ);
	}
	
	public function populateChunk($chunkX, $chunkZ){
		$this->level->level->setPopulated($chunkX, $chunkZ, true);
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->level->getSeed());
		foreach($this->populators as $populator){
			$populator->populate($this->level, $chunkX, $chunkZ, $this->random);
		}
		
		$biomecolors = "";
		for($z = 0; $z < 16; ++$z){
			for($x = 0; $x < 16; ++$x){
				$color = GrassColor::getBlendedGrassColor($this->level, ($chunkX*16)+$x, ($chunkZ*16)+$z);
				$biomecolors .= $color;
			}
		}
		$this->level->level->setGrassColorArrayForChunk($chunkX, $chunkZ, $biomecolors);
	}
	public static function getFastNoise3D(NoiseGenerator $noise, $xSize, $ySize, $zSize, $xSamplingRate, $ySamplingRate, $zSamplingRate, $x, $y, $z){
		$noiseArray = array_fill(0, $xSize + 1, array_fill(0, $zSize + 1, []));
		
		for($xx = 0; $xx <= $xSize; $xx += $xSamplingRate){
			for($zz = 0; $zz <= $zSize; $zz += $zSamplingRate){
				for($yy = 0; $yy <= $ySize; $yy += $ySamplingRate){
					$noiseArray[$xx][$zz][$yy] = $noise->noise3D(($x + $xx) / 32, ($y + $yy) / 32, ($z + $zz) / 32, 2, 1/4, true);
				}
			}
		}
		
		for($xx = 0; $xx < $xSize; ++$xx){
			for($zz = 0; $zz < $zSize; ++$zz){
				for($yy = 0; $yy < $ySize; ++$yy){
					if($xx % $xSamplingRate != 0 or $zz % $zSamplingRate != 0 or $yy % $ySamplingRate != 0){
						$nx = (int) ($xx / $xSamplingRate) * $xSamplingRate;
						$ny = (int) ($yy / $ySamplingRate) * $ySamplingRate;
						$nz = (int) ($zz / $zSamplingRate) * $zSamplingRate;
						
						$nnx = $nx + $xSamplingRate;
						$nny = $ny + $ySamplingRate;
						$nnz = $nz + $zSamplingRate;
						
						$dx1 = (($nnx - $xx) / ($nnx - $nx));
						$dx2 = (($xx - $nx) / ($nnx - $nx));
						$dy1 = (($nny - $yy) / ($nny - $ny));
						$dy2 = (($yy - $ny) / ($nny - $ny));
						
						$noiseArray[$xx][$zz][$yy] = (($nnz - $zz) / ($nnz - $nz)) * 
							(
								$dy1 * ($dx1 * $noiseArray[$nx][$nz][$ny] + $dx2 * $noiseArray[$nnx][$nz][$ny]) + 
								$dy2 * ($dx1 * $noiseArray[$nx][$nz][$nny] + $dx2 * $noiseArray[$nnx][$nz][$nny])
							) + 
							(($zz - $nz) / ($nnz - $nz)) * 
							(
								$dy1 * ($dx1 * $noiseArray[$nx][$nnz][$ny] + $dx2 * $noiseArray[$nnx][$nnz][$ny]) + 
								$dy2 * ($dx1 * $noiseArray[$nx][$nnz][$nny] + $dx2 * $noiseArray[$nnx][$nnz][$nny])
							);
					}
				}
			}
		}
		
		return $noiseArray;
	}
	public function getSpawn(){
		return $this->level->getSafeSpawn(new Vector3(127.5, 128, 127.5));
	}
	public function populateLevel(){}
	public function preGenerateChunk($chunkX, $chunkZ){
		return true;
	}
}
