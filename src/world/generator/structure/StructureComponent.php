<?php

abstract class StructureComponent
{
	public AxisAlignedBB $boundingBox;
	
	public $coordBaseMode = -1;
	public $componentType = 0;
	
	public function __construct($type){
		$this->componentType = $type;
	}
	
	public function buildComponent(StructureComponent $component, $aList, IRandom $random){}
	public abstract function addComponentParts(Level $level, IRandom $random, AxisAlignedBB $boundingBox): bool;
	
	/**
	 * @param StructureComponent[] $aList
	 * @param AxisAlignedBB $boundingBox
	 */
	public static function findIntersecting($aList, AxisAlignedBB $boundingBox){
		foreach($aList as $component){
			if($component->boundingBox != null && $component->boundingBox->intersectsWith($boundingBox)){
				return $component;
			}
		}
		return null;
	}
	
	public function getCenter(){
		return new Vector3(
			(int)($this->boundingBox->minX + (($this->boundingBox->maxX - $this->boundingBox->minX + 1) / 2)),
			(int)($this->boundingBox->minY + (($this->boundingBox->maxY - $this->boundingBox->minY + 1) / 2)),
			(int)($this->boundingBox->minZ + (($this->boundingBox->maxZ - $this->boundingBox->minZ + 1) / 2)),
		);
	}
	public function isLiquidInStructureBoundingBox(Level $level, AxisAlignedBB $boundingBox){
		$minX = max($boundingBox->minX - 1, $boundingBox->minX); //TODO optimize?
		$minY = max($boundingBox->minY - 1, $boundingBox->minY);
		$minZ = max($boundingBox->minZ - 1, $boundingBox->minZ);
		$maxX = min($boundingBox->maxX + 1, $boundingBox->maxX);
		$maxY = min($boundingBox->maxY + 1, $boundingBox->maxY);
		$maxZ = min($boundingBox->maxZ + 1, $boundingBox->maxZ);
		
		for($x = $minX; $x <= $maxX; ++$x){
			for($z = $minZ; $z <= $maxZ; ++$z){
				$blockID = $level->level->getBlockID($x, $minY, $z);
				if($blockID > 0 && StaticBlock::getIsLiquid($blockID)) return true;
				$blockID = $level->level->getBlockID($x, $maxY, $z);
				if($blockID > 0 && StaticBlock::getIsLiquid($blockID)) return true;
			}
		}
		
		for($x = $minX; $x <= $maxX; ++$x){
			for($y = $minY; $y <= $maxY; ++$y){
				$blockID = $level->level->getBlockID($x, $y, $minZ);
				if($blockID > 0 && StaticBlock::getIsLiquid($blockID)) return true;
				$blockID = $level->level->getBlockID($x, $y, $maxZ);
				if($blockID > 0 && StaticBlock::getIsLiquid($blockID)) return true;
			}
		}
		
		
		for($z = $minZ; $z <= $maxZ; ++$z){
			for($y = $minY; $y <= $maxY; ++$y){
				$blockID = $level->level->getBlockID($minX, $y, $z);
				if($blockID > 0 && StaticBlock::getIsLiquid($blockID)) return true;
				$blockID = $level->level->getBlockID($maxX, $y, $z);
				if($blockID > 0 && StaticBlock::getIsLiquid($blockID)) return true;
			}
		}
		return false;
	}
	
	public function getXWithOffset($par1, $par2){
		return match($this->coordBaseMode){
			0, 2 => $this->boundingBox->minX + $par1,
			1 => $this->boundingBox->maxX - $par2,
			3 => $this->boundingBox->minX + $par2,
			default => $par1
		};
	}
	public function getYWithOffset($par1){
		return $this->coordBaseMode == -1 ? $par1 : $par1 + $this->boundingBox->minY;
	}
	public function getZWithOffset($par1, $par2){
		return match($this->coordBaseMode){
			0 => $this->boundingBox->minZ + $par2,
			1, 3 => $this->boundingBox->minZ + $par1,
			2 => $this->boundingBox->maxZ - $par2,
			default => $par2
		};
	}
	public function getMetadataWithOffset($blockID, $blockMeta){
		switch($blockID){
			case WOODEN_DOOR_BLOCK:
			case IRON_DOOR_BLOCK:
				switch($this->coordBaseMode){
					case 0:
						return match($blockMeta){
							0 => 2,
							2 => 0,
							default => $blockMeta
						};
					case 1:
						return $blockMeta + 1 & 3;
					case 3:
						return $blockMeta + 3 & 3;
				}
				
				break;
			case COBBLESTONE_STAIRS:
			case WOODEN_STAIRS:
			case NETHER_BRICKS_STAIRS:
			case STONE_BRICK_STAIRS:
			case SANDSTONE_STAIRS:
				switch($this->coordBaseMode){
					case 0:
						return match($blockMeta){
							2 => 3,
							3 => 2,
							default => $blockMeta
						};
					case 1:
						return match($blockMeta){
							0 => 2,
							1 => 3,
							2 => 0,
							3 => 1,
							default => $blockMeta
						};
					case 3:
						return match($blockMeta){
							0 => 2,
							1 => 3,
							2 => 1,
							3 => 0,
							default => $blockMeta
						};
				}
				break;
			case RAIL:
				if($this->coordBaseMode == 1 || $this->coordBaseMode == 3){
					return $blockMeta == 1 ? 0 : 1;
				}
				break;
			case LADDER:
				switch($this->coordBaseMode){
					case 0:
						return match($blockMeta){
							2 => 3,
							3 => 2,
							default => $blockMeta
						};
					case 1:
						return match($blockMeta){
							2 => 4,
							3 => 5,
							4 => 2,
							5 => 3,
							default => $blockMeta
						};
					case 3:
						return match($blockMeta){
							2 => 5,
							3 => 4,
							4 => 2,
							5 => 3,
							default => $blockMeta
						};
				}
			//case stone_button, not needed
			//case pistonbase, pistonstickybase, level, dispenser, not needed
			
		}
		/* TODO directional blocks, tripwire source(not needed prob)
		 * 		if (this.coordBaseMode == 0)
                {
                    if (blockMeta == 0 || blockMeta == 2) return Direction.rotateOpposite[blockMeta];
                }
                else if (this.coordBaseMode == 1)
                {
                    if (blockMeta == 2) return 1;

                    if (blockMeta == 0) return 3;

                    if (blockMeta == 1) return 2;

                    if (blockMeta == 3) return 0;
                }
                else if (this.coordBaseMode == 3)
                {
                    if (blockMeta == 2) return 3;
                    if (blockMeta == 0) return 1;
                    if (blockMeta == 1)  return 2;
                    if (blockMeta == 3) return 0;
                }
		 */
		return $blockMeta;
	}
	
	public function placeBlockAtCurrentPosition(Level $level, $blockID, $blockMeta, $par4, $par5, $par6, AxisAlignedBB $boundingBox){
		$x = $this->getXWithOffset($par4, $par6);
		$y = $this->getYWithOffset($par5);
		$z = $this->getZWithOffset($par4, $par6);
		
		if($boundingBox->isXYZInside($x, $y, $z)){
			$level->fastSetBlockUpdate($x, $y, $z, $blockID, $blockMeta);
		}
	}
	
	public function getBlockIdAtCurrentPosition(Level $level, $par2, $par3, $par4, AxisAlignedBB $boundingBox){
		$x = $this->getXWithOffset($par2, $par4);
		$y = $this->getYWithOffset($par3);
		$z = $this->getZWithOffset($par2, $par4);
		return !$boundingBox->isXYZInside($x, $y, $z) ? 0 : $level->level->getBlockID($x, $y, $z);
	}
	
	public function fillWithAir(Level $level, AxisAlignedBB $boundingBox, $minX, $minY, $minZ, $maxX, $maxY, $maxZ){
		for($y = $minY; $y <= $maxY; ++$y){
			for($x = $minX; $x <= $maxX; ++$x){
				for($z = $minZ; $z < $maxZ; ++$z){
					$this->placeBlockAtCurrentPosition($level, AIR, 0, $x, $y, $z, $boundingBox);
				}
			}
		}
	}
	
	public function fillWithBlocks(Level $level, AxisAlignedBB $boundingBox, $minX, $minY, $minZ, $maxX, $maxY, $maxZ, $blockID, $replaceID, $alwaysReplace){
		for($y = $minY; $y <= $maxY; ++$y){
			for($x = $minX; $x <= $maxX; ++$x){
				for($z = $minZ; $z < $maxZ; ++$z){
					if(!$alwaysReplace || $this->getBlockIdAtCurrentPosition($level, $x, $y, $z, $boundingBox) != 0){
						if($y != $minY && $y != $maxY && $x != $minX && $x != $maxX && $z != $minZ && $z != $maxZ){
							$this->placeBlockAtCurrentPosition($level, $replaceID, 0, $x, $y, $z, $boundingBox);
						}else{
							$this->placeBlockAtCurrentPosition($level, $blockID, 0, $x, $y, $z, $boundingBox);
						}
					}
				}
			}
		}
	}
	
	public function fillWithMetadataBlocks(){throw new Exception("Not Implemented");} //TODO it is too long + not used for mineshafts i want now
	public function fillWithRandomizedBlocks(){throw new Exception("Not Implemented");}  //TODO it is too long + not used for mineshafts i want now
	
	public function randomlyFillWithBlocks(Level $level, AxisAlignedBB $boundingBox, IRandom $random, $randomChance, $minX, $minY, $minZ, $maxX, $maxY, $maxZ, $blockID, $replaceID, $alwaysReplace){
		for($y = $minY; $y <= $maxY; ++$y){
			for($x = $minX; $x <= $maxX; ++$x){
				for($z = $minZ; $z < $maxZ; ++$z){
					if($random->nextFloat() <= $randomChance && (!$alwaysReplace || $this->getBlockIdAtCurrentPosition($level, $x, $y, $z, $boundingBox) != 0)){
						if($y != $minY && $y != $maxY && $x != $minX && $x != $maxX && $z != $minZ && $z != $maxZ){
							$this->placeBlockAtCurrentPosition($level, $replaceID, 0, $x, $y, $z, $boundingBox);
						}else{
							$this->placeBlockAtCurrentPosition($level, $blockID, 0, $x, $y, $z, $boundingBox);
						}
					}
				}
			}
		}
	}
	
	public function randomlyPlaceBlock(Level $level, AxisAlignedBB $boundingBox, IRandom $random, $randomChance, $x, $y, $z, $blockID, $blockMeta){
		if($random->nextFloat() < $randomChance) $this->placeBlockAtCurrentPosition($level, $blockID, $blockMeta, $x, $y, $z, $boundingBox);
	}
	
	public function randomlyRareFillWithBlocks(Level $level, AxisAlignedBB $boundingBox, $minX, $minY, $minZ, $maxX, $maxY, $maxZ, $blockID, $alwaysreplace){
		$v11 = ($maxX - $minX + 1);
		$v12 = ($maxY - $minY + 1);
		$v13 = ($maxZ - $minZ + 1);
		$v14 = $minX + $v11 / 2;
		$v15 = $minZ + $v13 / 2;
		
		for($y = $minY; $y <= $maxY; ++$y){
			$v17 = ($y - $minY) / $v12;
			for($x = $minX; $x <= $maxX; ++$x){
				$v19 = ($x - $v14) / ($v11 * 0.5);
				for($z = $minZ; $z <= $maxZ; ++$z){
					$v21 = ($z - $v15) / ($v13 * 0.5);
					
					if(!$alwaysreplace || $this->getBlockIdAtCurrentPosition($level, $x, $y, $z, $boundingBox) != 0){
						$v22 = $v19*$v19 + $v17*$v17 + $v21*$v21;
						
						if($v22 <= 1.05){
							$this->placeBlockAtCurrentPosition($level, $blockID, 0, $x, $y, $z, $boundingBox);
						}
					}
					
				}
			}
		}
	}
	
	public function clearCurrentPositionBlocksUpwards(){throw new Exception("Not Implemented");} //TODO implement, used for villages
	public function fillCurrentPositionBlocksDownwards(){throw new Exception("Not Implemented");} //TODO implement, used for villages
	public function generateStructureChestContents(){throw new Exception("Not Implemented");} //TODO implement
	public function placeDoorAtCurrentPosition(){throw new Exception("Not Implemented");} //TODO implement
	
}

