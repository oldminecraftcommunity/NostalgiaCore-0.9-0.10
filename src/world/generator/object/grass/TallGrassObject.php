<?php

class TallGrassObject{
	//checking biome
	public static function growGrass(Level $level, Vector3 $pos, IRandom $random, $count = 15, $radius = 10){
		$arr = [
			[DANDELION, 0],
			[CYAN_FLOWER, 0],
			[TALL_GRASS, 1],
			[TALL_GRASS, 1],
			[TALL_GRASS, 2],
			[TALL_GRASS, 1]
		];
		$arrC = count($arr);
		for($c = 0; $c < $count; ++$c){
			$x = ($pos->x - $radius) + $random->nextInt($radius+$radius+1);
			$z = ($pos->z - $radius) + $random->nextInt($radius+$radius+1);
			if($level->level->getBlockID($x, $pos->y + 1, $z) === AIR && $level->level->getBlockID($x, $pos->y, $z) === GRASS){
				$t = $arr[$random->nextInt($arrC)];
				$level->fastSetBlockUpdate($x, $pos->y + 1, $z, $t[0], $t[1], false);
			}
		}
	}
}