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
	
	public function init(Level $level, IRandom $random){
		$this->level = $level;
		$this->random = new XorShift128Random($level->getSeed());
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
		
		$trees = new TreePopulator();
		$trees->setBaseAmount(3);
		$trees->setRandomAmount(0);
		$this->populators[] = $trees;
		
		$tallGrass = new TallGrassPopulator();
		$tallGrass->setBaseAmount(5);
		$tallGrass->setRandomAmount(0);
		$this->populators[] = $tallGrass;
		
		$this->caveGenerator = new CaveGenerator($this->level->getSeed());
		$this->genPopulators[] = new GroundCover();
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
		$noiseArray = $this->noiseBase->getFastNoise3D(16, 128, 16, 4, 8, 4, $chunkX * 16, 0, $chunkZ * 16);
		$biomeCache = [];
		$blockIds = "";
		$blockMetas = str_repeat("\x00", 16*16*64);
		
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$minSum = 1;
				$maxSum = 1;
				$weightSum = 0;
				
				for($sx = -ExperimentalGenerator::$SMOOTH_SIZE; $sx <= ExperimentalGenerator::$SMOOTH_SIZE; ++$sx){
					for($sz = -ExperimentalGenerator::$SMOOTH_SIZE; $sz <= ExperimentalGenerator::$SMOOTH_SIZE; ++$sz){
						$weight = ExperimentalGenerator::$GAUSSIAN_KERNEL[$sx + ExperimentalGenerator::$SMOOTH_SIZE][$sz + ExperimentalGenerator::$SMOOTH_SIZE];
						
						$index = ($chunkX * 16 + $x + $sx).":".($chunkZ * 16 + $z + $sz);
						if(isset($biomeCache[$index])){
							$adjacent = $biomeCache[$index];
						}else{
							$biomeCache[$index] = $adjacent = $this->pickBiome($chunkX * 16 + $x + $sx, $chunkZ * 16 + $z + $sz);
						}
						if($sx == 0 && $sz == 0) $biome = $adjacent;
						
						$minSum += ($adjacent->minY - 1) * $weight;
						$maxSum += $adjacent->maxY * $weight;
						
						$weightSum += $weight;
					}
				}
				$this->level->level->setBiomeId(($chunkX << 4) + $x, ($chunkZ << 4) + $z, $biome->id);
				$minSum /= $weightSum;
				$maxSum /= $weightSum;
				
				$caveLevel = $minSum - 10;
				$blockIds .= "\x07";
				for($y = 1; $y < 128; ++$y){
					$noiseAdjustment = 2 * (($maxSum - $y) / ($maxSum - $minSum)) - 1;
					$distAboveCaveLevel = $y - $caveLevel > 0 ? $y - $caveLevel : 0;
					$noiseAdjustment = ($noiseAdjustment < (0.4 + ($distAboveCaveLevel / 10))) ? $noiseAdjustment : (0.4 + ($distAboveCaveLevel / 10));
					$noiseValue = $noiseArray[$x][$z][$y] + $noiseAdjustment;
					$blockIds .= (($noiseValue > 0) ? "\x01" : (($y <= $this->waterHeight) ? "\x09" : "\x00"));
				}
			}
		}
		
		foreach($this->genPopulators as $pop){
			$pop->populate($this->level, $blockIds, $blockMetas, $chunkX, $chunkZ, $this->random);
		}
		if(PocketMinecraftServer::$generateCaves){
			$this->caveGenerator->generate($this->level, $blockIds, $chunkX, $chunkZ);
		}
		
		$this->level->level->setChunkData($chunkX, $chunkZ, $blockIds, $blockMetas);
	}
	
	public function populateChunk($chunkX, $chunkZ){
		$worldX = ($chunkX*16);
		$worldZ = ($chunkZ*16);
		$this->level->level->setPopulated($chunkX, $chunkZ, true);
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->level->getSeed());
		foreach($this->populators as $populator){
			$populator->populate($this->level, $chunkX, $chunkZ, $this->random);
		}
		
		
		$biomecolors = "";
		for($z = 0; $z < 16; ++$z){
			for($x = 0; $x < 16; ++$x){
				$color = GrassColor::getBlendedGrassColor($this->level, $worldX+$x, $worldZ+$z);
				$biomecolors .= $color;
			}
		}
		GrassColor::clearBiomeCache();
		$this->level->level->setGrassColorArrayForChunk($chunkX, $chunkZ, $biomecolors);
	}
	
	public function getSpawn(){
		return $this->level->getSafeSpawn(new Vector3(127.5, 128, 127.5));
	}
	public function populateLevel()
	{}
	
	/**
	 * @deprecated use {@link NoiseGenerator::getFastNoise3D()} instead
	 */
	public static function getFastNoise3D(NoiseGenerator $noise, $xSize, $ySize, $zSize, $xSamplingRate, $ySamplingRate, $zSamplingRate, $x, $y, $z){
		return $noise->getFastNoise3D($xSize, $ySize, $zSize, $xSamplingRate, $ySamplingRate, $zSamplingRate, $x, $y, $z);
	}
}
