<?php

class GroundCover extends GenPopulator
{
	public function populate(Level $level, &$blocks, &$meta, $chunkX, $chunkZ, IRandom $random)
	{
		$waterHeight = 63;
		for($x = 0; $x < 16; ++$x){
			for($z = 0; $z < 16; ++$z){
				$pcx = ($chunkX << 4) + $x;
				$pcz = ($chunkZ << 4) + $z;
				$biome = BiomeSelector::get($level->level->getBiomeId($pcx, $pcz));
				$cover = $biome->getTopBlocks();
				if(count($cover) > 0){
					$diffY = 0;
					
					if(!StaticBlock::getIsSolid($cover[0][0])){
						$diffY = 1;
					}
					
					for($y = 127; $y > 0; --$y){
						$b = $blocks[($x << 11) | ($z << 7) | $y];
						if($b != "\x00" and !StaticBlock::getIsTransparent(ord($b))){
							break;
						}
					}
					
					$startY = min(126, $y + $diffY);
					$endY = $startY - count($cover);
					for($y = $startY; $y > $endY && $y >= 0; --$y){
						$index = ($x << 11) | ($z << 7) | $y;
						$pair = $cover[$startY - $y];
						$bid = $pair[0];
						$bmeta = $pair[1];
						if($blocks[($x << 11) | ($z << 7) | $y] == "\x00" and StaticBlock::getIsSolid($bid)){
							break;
						}
						if($y <= $waterHeight and $bid == GRASS and ord($blocks[$index+1]) == STILL_WATER){
							$blocks[$index] = chr(DIRT);
							continue;
						}
						$blocks[$index] = chr($bid);
						$m = ord($meta[$index >> 1]);
						$meta[$index >> 1] =  chr(($m & 1) ? ($m & 0xf0) | $bmeta : ($bmeta << 4) | ($m & 0xf));
					}
				}
			}
		}
	}

	
}

