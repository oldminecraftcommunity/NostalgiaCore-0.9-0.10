<?php

abstract class Biome{
	public $id, $topBlocks, $fillerBlock, $name, $minY, $maxY;
	
	public $decorator = false;
	
	public function __construct($id, $name){
		$this->name = $name;
		$this->id = $id;
		$this->decorator = $this->createBiomeDecorator();
	}
	
	public function setTopBlocks($id){
		$this->topBlocks = $id;
	}
	
	public function createBiomeDecorator(){
		return new BiomeDecorator();
	}
	
	public function getTree(IRandom $random){
		return null;
	}
	
	public function getID(){
		return $this->id;
	}
	
	public function setTempDown($temp, $down){
		$this->temperature = $temp;
		$this->downfall = $down;
	}
	
	public function setMinMax($min, $max){
		$this->minY = $min;
		$this->maxY = $max;
	}
	
	public function getMin(){
		return $this->minY;
	}
	
	public function getTopBlocks(){
		return $this->topBlocks;
	}
	
	public function getMax(){
		return $this->maxY;
	}
	
	public function setFillerBlock($id){
		$this->fillerBlock = $id;
	}
	
	public function getGrassColor($x, $z){
		$temp = $this->temperature;
		$down = $this->downfall;
		if($temp > 1) $temp = 1;
		if($temp < 0) $temp = 0;
		if($down > 1) $down = 1;
		if($down < 0) $down = 0;
		return GrassColor::getGrassColor($temp, $down);
	}
	
	
	public function getBiomeAt($x, $z){
		self::$biomeLookup[$x + $z * 64];
	}
	
	public function __toString(){
		return $this->name;
	}
	
	public static function init(){
		ConsoleAPI::debug("Init Biomes");
		BiomeSelector::registerBiome(new BiomeExtremeHills(BIOME_EXTREME_HILLS, "Extreme Hills"));
		BiomeSelector::registerBiome(new BiomePlains(BIOME_PLAINS, "Plains"));
		BiomeSelector::registerBiome(new BiomeExtremeHillsEdge(BIOME_EXTREME_HILLS_EDGE, "Extreme Hills Edge"));
		BiomeSelector::registerBiome(new BiomeRiver(BIOME_RIVER, "River"));
		BiomeSelector::registerBiome(new BiomeOcean(BIOME_OCEAN, "Ocean"));
		BiomeSelector::registerBiome(new BiomeSwamp(BIOME_SWAMP, "Swamp"));
		BiomeSelector::registerBiome(new BiomeIcePlains(BIOME_ICE_PLAINS, "Ice Plains"));
		BiomeSelector::registerBiome(new BiomeDesert(BIOME_DESERT, "Desert"));
		BiomeSelector::registerBiome(new BiomeTaiga(BIOME_TAIGA, "Taiga"));
		BiomeSelector::registerBiome($bf = new BiomeForest(BIOME_FOREST, "Forest"));
		$bf->setTempDown(0.7, 0.8);
		BiomeSelector::registerBiome($bf = new BiomeForest(BIOME_BIRCH_FOREST, "Birch Forest"));
		$bf->setTempDown(0.6, 0.6);
		BiomeSelector::registerBiome(new BiomeJungle(BIOME_JUNGLE, "Jungle"));
		BiomeSelector::registerBiome(new BiomeSavanna(BIOME_SAVANNA, "Savanna"));
		BiomeSelector::registerBiome(new BiomeHell(BIOME_HELL, "Hell"));
		BiomeSelector::registerBiome(new BiomeEnd(BIOME_SKY, "Sky")); //crashes the game
	}

	private static $initialized = false;
	public $temperature, $downfall;
}
