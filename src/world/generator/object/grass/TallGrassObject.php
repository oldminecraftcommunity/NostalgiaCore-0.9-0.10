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
	
	public static function useBonemeal(Level $level, Vector3 $pos, IRandom $random){
		$v5 = 16;
		while($v5 < 64){
			$x = $pos->x;
			$y = $pos->y+1;
			$z = $pos->z;
			if(self::randomWalk($level, $random, $x, $y, $z, $v5)){
				if($level->level->getBlockID($x, $y, $z) == AIR){
					$rn = $random->nextInt(16);
					$id = TALL_GRASS;
					$meta = 0;
					if($rn == 0) $id = CYAN_FLOWER;
					else if($rn == 1) $id = DANDELION;
					else if($rn == 2) $meta = 2;
					else $meta = 1;
					
					$level->fastSetBlockUpdate($x, $pos->y + 1, $z, $id, $meta, false);
				}
			}
			++$v5;
		}
	}
	
	public static function randomWalk(Level $level, IRandom $random, &$x, &$y, &$z, $a6){
		$i = 0;
		while($i < $a6/16){
			$x += $random->nextInt(3) - 1;
			$m = (int) (($random->nextInt(3) - 1) / 2);
			$y += $random->nextInt(3) * $m;
			$z += $random->nextInt(3) - 1;
			if($level->level->getBlockID($x, $y-1, $z) != GRASS || StaticBlock::getIsSolid($level->level->getBlockID($x, $y, $z))){
				return 0;
			}
			++$i;
		}
		return $a6 > 15;
	}
}