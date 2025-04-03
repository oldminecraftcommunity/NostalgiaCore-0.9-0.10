<?php

class OrePopulator extends Populator{

	private $oreTypes = [];

	public function populate(Level $level, $chunkX, $chunkZ, IRandom $random){
		foreach($this->oreTypes as $type){
			$ore = new OreObject($random, $type);
			for($i = 0; $i < $ore->type->clusterCount; ++$i){
				$x = ($chunkX << 4) + $random->nextInt(16);
				$y = $ore->type->minHeight+$random->nextInt($ore->type->maxHeight+1);
				$z = ($chunkZ << 4) + $random->nextInt(16);
				if($ore->canPlaceObject($level, $x, $y, $z)){
					$ore->placeObject($level, $x, $y, $z);
				}
			}
		}
	}

	public function setOreTypes(array $types){
		$this->oreTypes = $types;
	}
}