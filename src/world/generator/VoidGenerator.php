<?php

/**
 *
 *  ____			_		_   __  __ _				  __  __ ____  
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___	  |  \/  |  _ \ 
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/ 
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|	 |_|  |_|_| 
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 * 
 *
*/

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
		//for($Y = 0; $Y < 8; ++$Y){
			//$this->level->setMiniChunk($chunkX, $chunkZ, $Y, $this->chunks[$Y]);
		//}
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
}