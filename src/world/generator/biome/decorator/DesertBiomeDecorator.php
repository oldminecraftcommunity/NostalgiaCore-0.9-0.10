<?php

class DesertBiomeDecorator extends BiomeDecorator
{
	public function decorate(Level $level, $chunkX, $chunkZ, IRandom $random){
		for($i = 0; $i < 2; ++$i){
			$x = $chunkX*16 + $random->nextInt(16);
			$z = $chunkZ*16 + $random->nextInt(16);
			$y = $this->getHighestWorkableBlock($level, $x, $z);
			$level->level->setBlockID($x, $y, $z, DEAD_BUSH);
		}
		
		for($i = 0; $i < 2; ++$i){
			$x = $chunkX*16 + $random->nextInt(16);
			$z = $chunkZ*16 + $random->nextInt(16);
			$y = $this->getHighestWorkableBlock($level, $x, $z);
			$cactiSize = $random->nextInt(4);
			for($yOff = 0; $yOff < $cactiSize; ++$yOff){
				$level->level->setBlockID($x, $y + $yOff, $z, CACTUS);
			}
		}
	}
	
	private function getHighestWorkableBlock(Level $level, $x, $z){
		for($y = 128; $y > 0; --$y){
			$b = $level->level->getBlockID($x, $y, $z);
			if($b == SAND && $level->level->getBlockID($x, $y + 1, $z) == 0){
				return $y + 1;
			}
		}
		return -1;
	}
}

