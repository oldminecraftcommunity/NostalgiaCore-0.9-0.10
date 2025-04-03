<?php

class BiomeTaiga extends BiomeWithSnow
{
	public function __construct($id, $name){
		parent::__construct($id, $name);
		
		$this->setMinMax(63, 81);
		$this->setTopBlocks([
			[GRASS, 0],
			[DIRT, 0],
			[DIRT, 0],
			[DIRT, 0],
		]);
	}
	
	public function getTree(IRandom $random){
		$n = $random->nextInt(3);
		if($n == 0){
			return new PineTreeObject();
		}
		return new SpruceTreeObject();
	}
}

