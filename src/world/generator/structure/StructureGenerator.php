<?php

abstract class StructureGenerator extends StructureBase
{
	/**
	 * @var StructureStart
	 */
	public $structureMap = []; //php array is hashmap
	
	public function recursiveGenerate(Level $level, $chunkXoffsetted, $chunkZoffsetted, $chunkX, $chunkZ){
		$chunkXZ = Utils::chunkPos2Int($chunkXoffsetted, $chunkZoffsetted);
		if(!isset($this->structureMap[$chunkXZ])){
 			$this->rand->nextInt(); //shouldnt be neccessary, but vanilla has it.
 			
 			if($this->canSpawnStructureAt($chunkXoffsetted, $chunkZoffsetted)){
 				$structureStrat = $this->getStructureStart($level, $chunkXoffsetted, $chunkZoffsetted);
 				$this->structureMap[$chunkXZ] = $structureStrat;
 			}
 			
		}
	}
	
	public function generateStructuresInChunk(Level $level, IRandom $random, $chunkX, $chunkZ){
		$chunkXCenter = ($chunkX << 4) + 8;
		$chunkZCenter = ($chunkZ << 4) + 8;
		$var7 = false;
		foreach($this->structureMap as $start){
			if($start->isSizeableStructure() && $start->boundingBox->intersectsWith($chunkXCenter, $chunkZCenter, $chunkXCenter + 15, $chunkZCenter + 15)){
				$start->generateStructure($level, $random, new StructureAABB($chunkXCenter, 1, $chunkZCenter, $chunkXCenter + 15, 512, $chunkZCenter + 15));
				$var7 = true;
			}
		}
		return $var7;
	}
	
	public abstract function canSpawnStructureAt($chunkX, $chunkZ);
	public abstract function getStructureStart(Level $level, $chunkX, $chunkZ);
}

