<?php

class BiomePlains extends BiomeWithGrass{
	public function __construct($id, $name){
		parent::__construct($id, $name);
		$this->setMinMax(61, 68);
		$this->setTempDown(0.8, 0.4);
	}
	
	public function getTree(IRandom $random){
		return $random->nextInt(20) == 0 ? new SmallTreeObject(SaplingBlock::OAK) : null;
	}
}
