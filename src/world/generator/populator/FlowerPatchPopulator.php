<?php

class FlowerPatchPopulator extends Populator
{
	public static $flowerIDS = [
		[DANDELION, 0],
		[MULTIFLOWER, 0],
		[MULTIFLOWER, 1],
		[MULTIFLOWER, 2],
		[MULTIFLOWER, 3],
		[MULTIFLOWER, 4],
		[MULTIFLOWER, 5],
		[MULTIFLOWER, 6],
		[MULTIFLOWER, 7],
		[MULTIFLOWER, 8],
		
	];
	
	public function populate(Level $level, $chunkX, $chunkZ, IRandom $random)
	{
		$x = $chunkX*16 + $random->nextInt(16);
		$z = $chunkZ*16 + $random->nextInt(16);
		
		for($i = 0; $i < 4; ++$i){
			$xPos = ($x + $random->nextInt(8)) - $random->nextInt(8);
			$zPos = ($z + $random->nextInt(8)) - $random->nextInt(8);
			$yPos = $this->getHighestWorkableBlock($level, $xPos, $zPos);
			if($level->level->getBlockID($xPos, $yPos, $zPos) == 0){
				$flowerIDMeta = self::$flowerIDS[$random->nextInt(count(self::$flowerIDS))];
				$level->level->setBlock($xPos, $yPos, $zPos, $flowerIDMeta[0], $flowerIDMeta[1]);
			}
		}
	}
	
	private function getHighestWorkableBlock(Level $level, $x, $z){
		for($y = 128; $y > 0; --$y){
			$b = $level->level->getBlockID($x, $y, $z);
			if($b == GRASS || $b == DIRT){
				return $y + 1;
			}
		}
		return -1;
	}
}

