<?php

class BiomeBasedTreePopulator extends \TreePopulator
{
	public function populate(Level $level, $chunkX, $chunkZ, IRandom $random){
		$this->level = $level;
		$amount = $random->nextInt($this->randomAmount + 1 + 1) + $this->baseAmount;
		for($i = 0; $i < $amount; ++$i){
			$x = ($chunkX << 4) + $random->nextInt(16);
			$z = ($chunkZ << 4) + $random->nextInt(16);
			$biomeID = $level->level->getBiomeId($x, $z);
			$biome = BiomeSelector::get($biomeID);
			$treeFeature = null;
			if($biome instanceof Biome){
				$treeFeature = $biome->getTree($random);
			}
			
			if($treeFeature instanceof TreeObject){
				$y = $this->getHighestWorkableBlock($x, $z);
				if($y === -1){
					continue;
				}
				$v3 = new Vector3($x, $y, $z); //TODO no v3
				if($treeFeature->canPlaceObject($level, $v3, $random)){
					$treeFeature->placeObject($level, $v3, $random);
				}
			}else{
				continue;
			}
		}
	}
}

