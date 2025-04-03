<?php

class BiomeSavanna extends BiomeWithGrass
{
	public function __construct($id, $name){
		parent::__construct($id, $name);
		$this->setMinMax(63, 81);
		$this->setTempDown(1.2, 0.0);
	}
	
	public function getTree(IRandom $random){
		return $random->nextInt(5) == 0 ? new AcaciaTreeObject() : null;
	}
}

