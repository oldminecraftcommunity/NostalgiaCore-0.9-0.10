<?php

/***REM_START***/
require_once("LevelGenerator.php");
/***REM_END***/

class VoidGenerator implements LevelGenerator{
	private $level, $random, $structure, $chunks, $options, $floorLevel, $populators = array();
	
	public function __construct(array $options = array()){
		$this->options = $options;
		/*if(isset($this->options["mineshaft"])){
			$this->populators[] = new MineshaftPopulator(isset($this->options["mineshaft"]["chance"]) ? floatval($this->options["mineshaft"]["chance"]) : 0.01);
		}*/
	}
	
	public function init(Level $level, Random $random){
		$this->level = $level;
		$this->random = $random;
	}
		
	public function generateChunk($chunkX, $chunkZ){
		for($Y = 0; $Y < 8; ++$Y){
			$this->level->setMiniChunk($chunkX, $chunkZ, $Y, $this->chunks[$Y]);
		}
	}
	
	public function populateChunk($chunkX, $chunkZ){		
		foreach($this->populators as $populator){
			$this->random->setSeed((int) ($chunkX * 0xdead + $chunkZ * 0xbeef) ^ $this->level->getSeed());
			$populator->populate($this->level, $chunkX, $chunkZ, $this->random);
		}
	}
	
	public function populateLevel(){
		$this->random->setSeed($this->level->getSeed());
	}
	
	public function getSpawn(){
		return new Vector3(128, $this->floorLevel, 128);
	}
	public function preGenerateChunk($chunkX, $chunkZ){
		return true;
	}
}