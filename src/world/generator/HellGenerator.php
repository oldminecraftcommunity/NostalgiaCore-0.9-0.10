<?php

//from HellGenerator for PocketMine mcpe 0.11 by Р¶пїЅСџРґС”вЂ�

class HellGenerator implements NewLevelGenerator{

	/** @var Populator[] */
	private $populators = [];
	private $level;
	/** @var Random */
	private $random;
	private $waterHeight = 32;
	private $emptyHeight = 64;
	private $emptyAmplitude = 1;
	private $density = 0.5;
	private $bedrockDepth = 5;

	/** @var Populator[] */
	private $generationPopulators = [];
	private $noiseBase;

	private static $GAUSSIAN_KERNEL = null; 
	private static $SMOOTH_SIZE = 2;

	public function __construct(array $options = []){
		if(self::$GAUSSIAN_KERNEL === null){
			self::generateKernel();
		}
	}

	private static function generateKernel(){
		self::$GAUSSIAN_KERNEL = [];

		$bellSize = 1 / self::$SMOOTH_SIZE;
		$bellHeight = 2 * self::$SMOOTH_SIZE;

		for($sx = -self::$SMOOTH_SIZE; $sx <= self::$SMOOTH_SIZE; ++$sx){
			self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE] = [];

			for($sz = -self::$SMOOTH_SIZE; $sz <= self::$SMOOTH_SIZE; ++$sz){
				$bx = $bellSize * $sx;
				$bz = $bellSize * $sz;
				self::$GAUSSIAN_KERNEL[$sx + self::$SMOOTH_SIZE][$sz + self::$SMOOTH_SIZE] = $bellHeight * exp(-($bx * $bx + $bz * $bz) / 2);
			}
		}
	}

	public function getName(){
		return "hell";
	}

	public function getSettings(){
		return [];
	}

	public function init(Level $level, IRandom $random){
		$this->level = $level;
		$this->random = $random;
		$this->random->setSeed($this->level->getSeed());
		$this->noiseBase = new NoiseGeneratorSimplex($this->random, 4, 1 / 4, 1 / 64);

		/*$ores = new Ore();
		$ores->setOreTypes([
			new OreType(new CoalOre(), 20, 16, 0, 128),
			new OreType(New IronOre(), 20, 8, 0, 64),
			new OreType(new RedstoneOre(), 8, 7, 0, 16),
			new OreType(new LapisOre(), 1, 6, 0, 32),
			new OreType(new GoldOre(), 2, 8, 0, 32),
			new OreType(new DiamondOre(), 1, 7, 0, 16),
			new OreType(new Dirt(), 20, 32, 0, 128),
			new OreType(new Gravel(), 10, 16, 0, 128)
		]);
		$this->populators[] = $ores;*/
	}

	public function generateChunk($chunkX, $chunkZ){
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());

		$noise = $this->noiseBase->getFastNoise3D(16, 128, 16, 4, 8, 4, $chunkX * 16, 0, $chunkZ * 16);
		$blockIds = "";
		$blockMetas = str_repeat("\x00", 16*16*64);
		$blockLight = str_repeat("\x00", 16*16*64);
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				for($y = 0; $y < 128; ++$y){
					if($y == 0 || $y == 127){//Bedrock
						$blockIds .= "\x07";
						continue;
					}
					
					$noiseValue = (abs($this->emptyHeight - $y) / $this->emptyHeight) * $this->emptyAmplitude - $noise[$x][$z][$y];
					$noiseValue -= 1 - $this->density;
					
					if($noiseValue > 0) $blockIds .= "\x57"; //Netherrack
					elseif($y <= $this->waterHeight){
						$blockIds .= "\x0b"; //Lava
						$in = ($x << 11) | ($z << 7) | $y;
						$light = ord($blockLight[$in >> 1]);
						$blockLight[$in >> 1] = chr(($in & 1) ? ($light & 0xf0) | 0xf : 0xf0 | ($light & 0xf)); 
					}
					else $blockIds .= "\x00"; //Air
				}
			}
		}
		
		$this->level->level->setBiomeIdArrayForChunk($chunkX, $chunkZ, str_repeat(chr(BIOME_HELL), 256));
		foreach($this->generationPopulators as $populator){
			$populator->populate($this->level, $chunkX, $chunkZ, $this->random);
		}
		$this->level->level->setChunkData($chunkX, $chunkZ, $blockIds, $blockMetas, $blockLight);
	}

	public function populateChunk($chunkX, $chunkZ){
		$this->level->level->setPopulated($chunkX, $chunkZ, true); 
		$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
		foreach($this->populators as $populator){
			$this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->level->getSeed());
			$populator->populate($this->level, $blockIds, $blockMetas, $chunkX, $chunkZ, $this->random);
		}
		
		$biomecolors = "";
		for($z = 0; $z < 16; ++$z){
			for($x = 0; $x < 16; ++$x){
				$color = GrassColor::getBlendedGrassColor($this->level, ($chunkX*16)+$x, ($chunkZ*16)+$z);
				$biomecolors .= $color;
			}
		}
		GrassColor::clearBiomeCache();
		$this->level->level->setGrassColorArrayForChunk($chunkX, $chunkZ, $biomecolors);
	}

	public function getSpawn(){
		return new Vector3(127.5, 128, 127.5);
	}

	public function populateLevel()
	{}
}
