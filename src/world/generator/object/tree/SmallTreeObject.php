<?php

/***REM_START***/
require_once("TreeObject.php");
/***REM_END***/

class SmallTreeObject extends TreeObject{

private static $leavesHeight = 4;
	private static $leafRadii = [1, 1.41, 2.83, 2.24];
	public $type = 0;
	public $treeHeight = 7;
	private $trunkHeight = 5;
	private $addLeavesVines = false;
	private $addLogVines = false;
	private $addCocoaPlants = false;
	public function __construct($treeType = 0){
		parent::__construct();
		$this->type = $treeType;
	}
	public function canPlaceObject(Level $level, Vector3 $pos, IRandom $random){
		$radiusToCheck = 0;
		for($yy = 0; $yy < $this->trunkHeight + 3; ++$yy){
			if($yy == 1 or $yy === $this->trunkHeight){
				++$radiusToCheck;
			}
			for($xx = -$radiusToCheck; $xx < ($radiusToCheck + 1); ++$xx){
				for($zz = -$radiusToCheck; $zz < ($radiusToCheck + 1); ++$zz){
					if(!isset($this->overridable[$level->level->getBlockID($pos->x + $xx, $pos->y + $yy, $pos->z + $zz)])){
						return false;
					}
				}
			}
		}
		return true;
	}

	public function placeObject(Level $level, Vector3 $pos, IRandom $random){
		$this->treeHeight = $random->nextInt(4) + 4; //randomized tree height
		$x = $pos->getX();
		$y = $pos->getY();
		$z = $pos->getZ();

		for($yy = $y - 3 + $this->treeHeight; $yy <= $y + $this->treeHeight; ++$yy){
			$yOff = $yy - ($y + $this->treeHeight);
			$mid = (int) (1 - $yOff / 2);
			for($xx = $x - $mid; $xx <= $x + $mid; ++$xx){
				$xOff = abs($xx - $x);
				for($zz = $z - $mid; $zz <= $z + $mid; ++$zz){
					$zOff = abs($zz - $z);
					if($xOff === $mid and $zOff === $mid and ($yOff === 0 or mt_rand(0, 1) === 0)){
						continue;
					}
					$block = $level->level->getBlockID($xx, $yy, $zz);
					if($block == 0 || $block == LEAVES){
						$level->fastSetBlockUpdate($xx, $yy, $zz, LEAVES, $this->type, false);
					}
				}
			}
		}
		$this->placeTrunk($level, $x, $y, $z, $random, $this->treeHeight - 1);

	}

	protected function placeTrunk(Level $level, $x, $y, $z, IRandom $random, $trunkHeight){
		// The base dirt block
		$level->fastSetBlockUpdate($x, $y - 1, $z, DIRT, 0, false);

		for($yy = 0; $yy < $this->treeHeight; ++$yy){
			$blockId = $level->level->getBlockID($x, $y + $yy, $z);
			if(isset($this->overridable[$blockId])){
				$level->fastSetBlockUpdate($x, $y + $yy, $z, TRUNK, $this->type, false);
			}
		}
	}

}
