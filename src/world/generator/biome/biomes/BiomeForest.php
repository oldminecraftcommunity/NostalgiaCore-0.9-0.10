<?php

class BiomeForest extends BiomeWithGrass
{
	public function __construct($id, $name){ //TODO tree populators
		parent::__construct($id, $name);
		
		$this->setMinMax(63, 81);
	}
	
	public function getTree(IRandom $random){
		$f = $random->nextFloat();
		if($f > 0.75){
			return new SmallTreeObject(SaplingBlock::BIRCH);
		}
		return new SmallTreeObject(SaplingBlock::OAK);
	}
}

