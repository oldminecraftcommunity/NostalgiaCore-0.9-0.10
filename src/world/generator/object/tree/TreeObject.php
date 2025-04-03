<?php

class TreeObject{
	public function __construct(){}
	public $overridable = [
		0 => true,
		6 => true,
		18 => true,
	];
	public function isBlockCorrect(int $id)
	{
		return $id == 0 || ($id == LEAVES || $id == LEAVES2) || $id == GRASS || $id == DIRT || $id == TRUNK || $id == TRUNK2 || $id == SAPLING || $id == VINES;
	}
	public static function growTree(Level $level, Vector3 $pos, IRandom $random, $type = 0){
		switch($type & 0xf){
			case SaplingBlock::SPRUCE:
				if($random->nextInt(2) === 1){
					$tree = new SpruceTreeObject();
				}else{
					$tree = new PineTreeObject();
				}
				break;
			case SaplingBlock::BIRCH:
				$tree = new SmallTreeObject(SaplingBlock::BIRCH);
				break;
			case SaplingBlock::JUNGLE:
				$tree = new SmallTreeObject(SaplingBlock::JUNGLE);
				break;
			case SaplingBlock::ACACIA:
				$tree = new AcaciaTreeObject();
				break;
			case SaplingBlock::DARK_OAK:
				
				break;
			default:
			case SaplingBlock::OAK:
				/*if($random->nextRange(0, 9) === 0){
					$tree = new BigTreeObject();
				}else{*/
				$tree = new SmallTreeObject();
				//}
				break;
		}
		if($tree->canPlaceObject($level, $pos, $random)){
			$tree->placeObject($level, $pos, $random);
		}
	}
}