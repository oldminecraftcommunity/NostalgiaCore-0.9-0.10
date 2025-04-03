<?php

/***REM_START***/
require_once("src/world/generator/object/tree/TreeObject.php");

/***REM_END***/
class PineTreeObject extends TreeObject{

	var $type = 1;
	private $totalHeight = 8;
	private $leavesSizeY = -1;
	private $leavesAbsoluteMaxRadius = -1;

	public function canPlaceObject(Level $level, Vector3 $pos, IRandom $random){
		$this->findRandomLeavesSize($random);
		$checkRadius = 0;
		for($yy = 0; $yy < $this->totalHeight; ++$yy){
			if($yy === $this->leavesSizeY){
				$checkRadius = $this->leavesAbsoluteMaxRadius;
			}
			for($xx = -$checkRadius; $xx < ($checkRadius + 1); ++$xx){
				for($zz = -$checkRadius; $zz < ($checkRadius + 1); ++$zz){
					if(!isset($this->overridable[$level->level->getBlockID($pos->x + $xx, $pos->y + $yy, $pos->z + $zz)])){
						return false;
					}
				}
			}
		}
		return true;
	}

	private function findRandomLeavesSize(IRandom $random){
		$this->totalHeight += -1 + $random->nextInt(5);
		$this->leavesSizeY = 1 + $random->nextInt(3);
		$this->leavesAbsoluteMaxRadius = 2 + $random->nextInt(2);
	}

	public function placeObject(Level $level, Vector3 $pos, IRandom $random){
		if($this->leavesSizeY === -1 or $this->leavesAbsoluteMaxRadius === -1){
			$this->findRandomLeavesSize($random);
		}
		$level->fastSetBlockUpdate($pos->x, $pos->y - 1, $pos->z, DIRT, 0, false);
		$leavesRadius = 0;
		$leavesMaxRadius = 1;
		$leavesBottomY = $this->totalHeight - $this->leavesSizeY;
		$firstMaxedRadius = false;
		for($leavesY = 0; $leavesY <= $leavesBottomY; ++$leavesY){
			$yy = $this->totalHeight - $leavesY;
			for($xx = -$leavesRadius; $xx <= $leavesRadius; ++$xx){
				for($zz = -$leavesRadius; $zz <= $leavesRadius; ++$zz){
					if(abs($xx) != $leavesRadius or abs($zz) != $leavesRadius or $leavesRadius <= 0){
						$level->fastSetBlockUpdate($pos->x + $xx, $pos->y + $yy, $pos->z + $zz, LEAVES, $this->type, false);
					}
				}
			}
			if($leavesRadius >= $leavesMaxRadius){
				$leavesRadius = $firstMaxedRadius ? 1 : 0;
				$firstMaxedRadius = true;
				if(++$leavesMaxRadius > $this->leavesAbsoluteMaxRadius){
					$leavesMaxRadius = $this->leavesAbsoluteMaxRadius;
				}
			}else{
				++$leavesRadius;
			}
		}
		$trunkHeightReducer = $random->nextInt(4);
		for($yy = 0; $yy < ($this->totalHeight - $trunkHeightReducer); ++$yy){
			$level->fastSetBlockUpdate($pos->x, $pos->y + $yy, $pos->z, TRUNK, $this->type);
		}
	}


}