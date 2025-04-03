<?php

class DungeonFeature extends Feature
{
	
	protected function selectEID(IRandom $rand){
		
	}
	
	public function place(Level $level, IRandom $rand, $initialX, $initialY, $initialZ)
	{
		$baseSize = 3;
		$randomSizeX = $rand->nextInt(2) + 2;
		$randomSizeZ = $rand->nextInt(2) + 2;
		$var9 = 0;
		
		$minX = $initialX - $randomSizeX - 1;
		$maxX = $initialX + $randomSizeX + 1;
		$minY = $initialY - 1;
		$maxY = $initialY + $baseSize + 1;
		$minZ = $initialZ - $randomSizeZ - 1;
		$maxZ = $initialZ + $randomSizeZ + 1;
		
		for($x = $minX; $x <= $maxX; ++$x){
			for($y = $minY; $y <= $maxY; ++$y){
				for($z = $minZ; $z <= $maxZ; ++$z){
					$id = $level->level->getBlockID($x, $y, $z);
					
					if($y == $minY && !StaticBlock::getIsSolid($id)){
						return false;
					}
					
					if($y == $maxY && !StaticBlock::getIsSolid($id)){
						return false;
					}
					
					if(($x == $minX || $x == $maxX || $z == $minZ || $z == $maxZ) && $y == $initialY && $id == 0 && $level->level->getBlockID($x, $y + 1, $z) == 0){
						++$var9;
					}
				}
			}
		}

		if($var9 < 1 || $var9 > 5) return false;
		
		for($x = $minX; $x <= $maxX; ++$x){
			for($y = $maxY; $y >= $minY; --$y){
				for($z = $minZ; $z <= $maxZ; ++$z){
					$id = $level->level->getBlockID($x, $y, $z);
					if($x != $minX && $y != $minY && $z != $minZ && $x != $maxX && $y != $maxY && $z != $maxZ){
						$level->fastSetBlockUpdate($x, $y, $z, 0, 0);
					}else if($y >= 0 && !StaticBlock::getIsSolid($id)){
						$level->fastSetBlockUpdate($x, $y, $z, 0, 0);
					}else if(StaticBlock::getIsSolid($id)){
						if($y == $minY && $rand->nextInt(4) != 0){
							$level->fastSetBlockUpdate($x, $y, $z, MOSSY_COBBLESTONE, 0);
						}else{
							$level->fastSetBlockUpdate($x, $y, $z, COBBLESTONE, 0);
						}
					}
				}
			}
		}
		
		//TODO chest gen
		
		$level->fastSetBlockUpdate($initialX, $initialY, $initialZ, MONSTER_SPAWNER, 0); //TODO bteter block placement system
		ServerAPI::request()->api->tile->add($level, TILE_MOB_SPAWNER, $initialX, $initialY, $initialZ, [
			"EntityId" => match($rand->nextInt(4)){
				0 => MOB_SKELETON,
				1, 2 => MOB_ZOMBIE,
				3 => MOB_SPIDER
			},
			"id" => TILE_MOB_SPAWNER,
			"x" => $initialX,
			"y" => $initialY,
			"z" => $initialZ,
		]);
		return true;
	}
}

