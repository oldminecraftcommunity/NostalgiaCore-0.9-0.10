<?php
/***REM_START***/
require_once("NewLevelGenerator.php");
/***REM_END***/

class NormalGenerator implements NewLevelGenerator{
	const HIDDEN_FEATURES = false;
	private $populators = array();
	private $level;
	private $random;
	public $mtrandom;
	private $worldHeight = 65;
	private $waterHeight = 63;
	private $noiseHills;
	private $noisePatches;
	private $noisePatchesSmall;
	private $noiseBase;
	private $biomeSelector;
	private $caveGenerator;
	private $mineshaftGenerator;
	public function __construct(array $options = array()){
		
	}
	
	public function getSettings(){
		return array();
	}
	
	public function init(Level $level, IRandom $random){
		$this->level = $level;
		$this->random = new XorShift128Random($level->getSeed()); //$random;
		$this->random->setSeed($this->level->getSeed());
		$this->mtrandom = new MTRandom($this->level->getSeed());
		$this->noiseHills = new NoiseGeneratorPerlin($this->random, 3);
		$this->noisePatches = new NoiseGeneratorPerlin($this->random, 2);
		$this->noisePatchesSmall = new NoiseGeneratorPerlin($this->random, 2);
		$this->noiseBase = new NoiseGeneratorPerlin($this->random, 16);
		$this->biomeSelector = new NormalGeneratorBiomeSelector($this->random, BiomeSelector::$biomes[BIOME_PLAINS]);
		
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
		$trees = new BiomeBasedTreePopulator();
		$trees->setBaseAmount(8); //TODO biome based amount?
		$trees->setRandomAmount(4);
		$this->populators[] = $trees;
		
		$this->populators[] = new FlowerPatchPopulator();
		
		$tallGrass = new TallGrassPopulator();
		$tallGrass->setBaseAmount(5);
		$tallGrass->setRandomAmount(0);
		$this->populators[] = $tallGrass;
		$this->caveGenerator = new CaveGenerator($this->level->getSeed());
		$this->mineshaftGenerator = new MineshaftGenerator($this->level->getSeed());
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
		return $this->biomeSelector->pickBiome($x + $xNoise - 1, $z + $zNoise - 1);
	}
	
	public function generateChunk($chunkX, $chunkZ){
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
		$hills = array();
		$base = array();
		$biomes = str_repeat(chr(BIOME_PLAINS), 256);
		$blockIds = "";
		$blockMetas = str_repeat("\x00", 16*16*64);
		
		for($z = 0; $z < 16; ++$z){
			for($x = 0; $x < 16; ++$x){
				$biomes[($z << 4) + $x] = chr($this->pickBiome($chunkX * 16 + $x, $chunkZ * 16 + $z)->id);
				$i = ($z << 4) + $x;
				$hills[$i] = $this->noiseHills->noise2D($x + ($chunkX << 4), $z + ($chunkZ << 4), 0.11, 12, true);
				$patches[$i] = $this->noisePatches->noise2D($x + ($chunkX << 4), $z + ($chunkZ << 4), 0.03, 16, true);
				$base[$i] = $this->noiseBase->noise2D($x + ($chunkX << 4), $z + ($chunkZ << 4), 0.7, 16, true);
				if($base[$i] < 0){
					$base[$i] *= 0.5;
				}
			}
		}


		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$i = ($z << 4) + $x;
				$height = $this->worldHeight + $hills[$i] * 14 + $base[$i] * 7;
				$height = (int) $height;
				$biomeID = ord($biomes[$i]);
				for($y = 0; $y < 128; ++$y){
					$diff = $height - $y;
					if($y <= 4 and ($y == 0 or $this->random->nextFloat() < 0.33)){
						$blockIds .= "\x07"; //bedrock
					}elseif($diff > 2){
						$blockIds .= "\x01"; //stone
					}elseif($diff > 0){
						if($patches[$i] > 0.9){
							$blockIds .= "\x01"; //stone
						}elseif($patches[$i] < -0.8){
							$blockIds .= "\x0d"; //gravel
						}else{
							$blockIds .= $biomeID == BIOME_DESERT ? chr(SANDSTONE) : chr(DIRT);
						}
					}elseif($y <= $this->waterHeight){
						if(($this->waterHeight - $y) <= 1 && $diff == 0){
							if($biomeID === BIOME_TAIGA){
								if($y == $this->waterHeight) $blockIds .= chr(GRASS);
								else $blockIds .= chr(DIRT);
							}else{
								$blockIds .= chr(SAND);
							}
						}elseif($diff == 0){
							$blockIds .= "\x03"; //dirt
						}else{
							//if($y === $this->waterHeight && $biomeID == BIOME_TAIGA){
							//	$chunk .= chr(ICE);
							///}else{
							$blockIds .= "\x09"; //still_water
							//}
						}
					}elseif($diff == 0){
						if($patches[$i] > 0.7){
							$blockIds .= "\x01"; //stone
						}elseif($patches[$i] < -0.8){
							$blockIds .= "\x0d"; //gravel
						}else{
							$blockIds .= $biomeID == BIOME_DESERT ? chr(SAND) : chr(GRASS);
						}
					}else{
						$blockIds .= "\x00";
					}
				}
			}
		}
		
		$this->level->level->setBiomeIdArrayForChunk($chunkX, $chunkZ, $biomes);
		if(PocketMinecraftServer::$generateCaves){
			$this->caveGenerator->generate($this->level, $blockIds, $chunkX, $chunkZ);
		}
		
		if(self::HIDDEN_FEATURES) {
			$this->mineshaftGenerator->generate($this->level, $blockIds, $chunkX, $chunkZ);
		}
		$this->level->level->setChunkData($chunkX, $chunkZ, $blockIds, $blockMetas);
	}
	
	public function populateChunk($chunkX, $chunkZ){
		$blockX = $chunkX * 16;
		$blockZ = $chunkZ * 16;
		
		$this->level->level->setPopulated($chunkX, $chunkZ, true);

		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
		$this->mtrandom->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
		
		$biomeID = $this->level->level->getBiomeId($chunkX*16, $chunkZ*16);
		$biome = BiomeSelector::get($biomeID);
		if($biome === false){
			ConsoleAPI::warn("Failed to get Biome with id {$biomeID} at $chunkX, $chunkZ");
		}else{
			$biome->decorator->decorate($this->level, $chunkX, $chunkZ, $this->random);	
		}

		if(self::HIDDEN_FEATURES) {
			$this->mineshaftGenerator->generateStructuresInChunk($this->level, $this->mtrandom, $chunkX, $chunkZ);
			for ($i = 0; $i < 8; ++$i){
				$x = $blockX + $this->mtrandom->nextInt(16) + 8;
				$y = $this->mtrandom->nextInt(128);
				$z = $blockZ + $this->mtrandom->nextInt(16) + 8;
				if(Feature::$DUNGEON->place($this->level, $this->mtrandom, $x, $y, $z)){
					ConsoleAPI::debug("Placed Dungeon at $x $y $z");
				}
			}
		}
		foreach($this->populators as $populator){
			$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
			$populator->populate($this->level, $chunkX, $chunkZ, $this->random);
		}

		$biomecolors = "";
		for($z = 0; $z < 16; ++$z){
			for($x = 0; $x < 16; ++$x){
				$color = GrassColor::getBlendedGrassColor($this->level, $blockX+$x, $blockZ+$z);
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

	
}
