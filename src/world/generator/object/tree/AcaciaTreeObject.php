<?php
/***REM_START***/
require_once("TreeObject.php");
/***REM_END***/

class AcaciaTreeObject extends TreeObject{
	public $height;
	
	//TODO name me better
	public function setBlockWithSomeAdditionalConditions(Level $level, $x, $y, $z){
		$id = $level->level->getBlockID($x, $y, $z);
		if($id == AIR || $id == LEAVES || $id == LEAVES2){
			$level->fastSetBlockUpdate($x, $y, $z, LEAVES2, 0);
		}
	}
	
	public function placeObject(Level $level, Vector3 $pos, IRandom $random){ //World p_76484_1_, Random $random, int $pos->x, int $pos->y, int $pos->z
		$var6 = $this->height;
		$var21 = $level->level->getBlockID($pos->x, $pos->y - 1, $pos->z);
		
		if (($var21 == GRASS || $var21 == DIRT) && $pos->y < 128 - $var6 - 1)
		{
			$level->fastSetBlockUpdate($pos->x, $pos->y - 1, $pos->z, DIRT, 0);
			$var22 = $random->nextInt(4);
			$var10 = $var6 - $random->nextInt(4) - 1;
			$var11 = 3 - $random->nextInt(3);
			$var23 = (int)$pos->x;
			$var13 = (int)$pos->z;
			$var14 = (int)0;
			//int var15;
			//int var16;
			
			for ($var15 = 0; $var15 < $var6; ++$var15)
			{
				$var16 = (int)($pos->y + $var15);
				
				if ($var15 >= $var10 && $var11 > 0)
				{
					$var23 += Direction::offsetX[$var22];
					$var13 += Direction::offsetZ[$var22];
					--$var11;
				}
				
				$var17 = $level->level->getBlockID($var23, $var16, $var13);
				
				if ($var17 == AIR || $var17 == LEAVES || $var17 == LEAVES2 || $var17 == SAPLING)
				{
					$level->fastSetBlockUpdate($var23, $var16, $var13, TRUNK2, 0);
					$var14 = $var16;
				}
			}
			
			for ($var15 = -1; $var15 <= 1; ++$var15)
			{
				for ($var16 = -1; $var16 <= 1; ++$var16)
				{
					$this->setBlockWithSomeAdditionalConditions($level, $var23 + $var15, $var14 + 1, $var13 + $var16);
				}
			}
			
			$this->setBlockWithSomeAdditionalConditions($level, $var23 + 2, $var14 + 1, $var13);
			$this->setBlockWithSomeAdditionalConditions($level, $var23 - 2, $var14 + 1, $var13);
			$this->setBlockWithSomeAdditionalConditions($level, $var23, $var14 + 1, $var13 + 2);
			$this->setBlockWithSomeAdditionalConditions($level, $var23, $var14 + 1, $var13 - 2);
			
			for ($var15 = -3; $var15 <= 3; ++$var15)
			{
				for ($var16 = -3; $var16 <= 3; ++$var16)
				{
					if (abs($var15) != 3 || abs($var16) != 3)
					{
						$this->setBlockWithSomeAdditionalConditions($level, $var23 + $var15, $var14, $var13 + $var16);
					}
				}
			}
			
			$var23 = $pos->x;
			$var13 = $pos->z;
			$var15 = $random->nextInt(4);
			
			if ($var15 != $var22)
			{
				$var16 = $var10 - $random->nextInt(2) - 1;
				$var24 = 1 + $random->nextInt(3);
				$var14 = 0;
				//int var18;
				//int var19;
				
				for ($var18 = $var16; $var18 < $var6 && $var24 > 0; --$var24)
				{
					if ($var18 >= 1)
					{
						$var19 = $pos->y + $var18;
						$var23 += Direction::offsetX[$var15];
						$var13 += Direction::offsetZ[$var15];
						$var20 = $level->level->getBlockID($var23, $var19, $var13);
						
						if ($var20 == AIR || $var20 == LEAVES || $var20 == LEAVES2 || $var20 == SAPLING)
						{
							$level->fastSetBlockUpdate($var23, $var19, $var13, TRUNK2, 0);
							$var14 = $var19;
						}
					}
					
					++$var18;
				}
				
				if ($var14 > 0)
				{
					for ($var18 = -1; $var18 <= 1; ++$var18)
					{
						for ($var19 = -1; $var19 <= 1; ++$var19)
						{
							$this->setBlockWithSomeAdditionalConditions($level, $var23 + $var18, $var14 + 1, $var13 + $var19);
						}
					}
					
					for ($var18 = -2; $var18 <= 2; ++$var18)
					{
						for ($var19 = -2; $var19 <= 2; ++$var19)
						{
							if (abs($var18) != 2 || abs($var19) != 2)
							{
								$this->setBlockWithSomeAdditionalConditions($level, $var23 + $var18, $var14, $var13 + $var19);
							}
						}
					}
				}
			}
			
			return true;
		}
	}
	
	public function canPlaceObject(Level $level, Vector3 $pos, IRandom $random)
	{
		$var6 = $this->height = ($random->nextInt(3) + $random->nextInt(3) + 5);
		$var7 = true;

		if ($pos->y >= 1 && $pos->y + $var6 + 1 <= 128) {
			for ($var8 = $pos->y; $var8 <= $pos->y + 1 + $var6; ++$var8) {
				$var9 = 1;

				if ($var8 == $pos->y) {
					$var9 = 0;
				}

				if ($var8 >= $pos->y + 1 + $var6 - 2) {
					$var9 = 2;
				}

				for ($var10 = (int)($pos->x - $var9); $var10 <= $pos->x + $var9 && $var7; ++$var10) {
					for ($var11 = (int)($pos->z - $var9); $var11 <= $pos->z + $var9 && $var7; ++$var11) {
						if ($var8 >= 0 && $var8 < 128) {
							$var12 = $level->level->getBlockID($var10, $var8, $var11);

							if (! $this->isBlockCorrect($var12)) {
								return false;
							}
						} else {
							return false;
						}
					}
				}
			}
			return true;
		}
		return false;
	}
}