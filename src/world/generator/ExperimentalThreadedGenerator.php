<?php

class ExperimentalThreadedGenerator implements NewLevelGenerator, ThreadedGenerator{
	/**
	 * @var Level
	 */
	public $level;
	public $dataProvider;
	
	/**
	 * @var Populator[]
	 */
	public $populators = array();
	/**
	 * @var Populator[]
	 */
	public $genPopulators = array();
	public $caveGenerator;
	
	
	public function __construct(array $settings = []){
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see ThreadedGenerator::getDataProvider()
	 */
	public function getDataProvider(){
		return $this->dataProvider;
	}
	
	public function init(Level $level, Random $random){
		$this->level = $level;
		$this->random = $random;
		$this->random->setSeed($this->level->level->getSeed());
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
		$trees = new BiomeBasedTreePopulator();
		$trees->setBaseAmount(3);
		$trees->setRandomAmount(0);
		$this->populators[] = $trees;
		
		$tallGrass = new TallGrassPopulator();
		$tallGrass->setBaseAmount(5);
		$tallGrass->setRandomAmount(0);
		$this->populators[] = $tallGrass;
		$this->caveGenerator = new CaveGenerator($this->level->getSeed());
		
		$this->dataProvider = new ExperimentalThreadedChunkDataProvider($level->level->getSeed());
	}

	public function getSpawn(){
		return new Vector3(127, 128, 127);
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

	public function getSettings(){}
	static function seed($X, $Z){
		return ($Z << 16) | ($X < 0 ? (~--$X & 0x7fff) | 0x8000 : $X & 0x7fff);
	}
	
	public function preGenerateChunk($chunkX, $chunkZ){
		if($this->getDataProvider()->isReady($chunkX, $chunkZ)) return true;
		$this->getDataProvider()->request($chunkX, $chunkZ);
		
		return false;
	}
	public function generateChunk($chunkX, $chunkZ){
		$data = $this->getDataProvider()->get($chunkX, $chunkZ);
		
		for($i = 0; $i < 8; ++$i){
			$this->level->setMiniChunk($chunkX, $chunkZ, $i, $data[$i]);
		}
		
		$this->level->level->setBiomeIdArrayForChunk($chunkX, $chunkZ, $data["biomes"]);
		
		foreach($this->genPopulators as $pop){
			$pop->populate($this->level, $chunkX, $chunkZ, $this->random);
		}
	}

	public function populateLevel(){}
}

