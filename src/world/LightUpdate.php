<?php

class LightUpdate
{
	public $layer, $minX, $minY, $minZ, $maxX, $maxY, $maxZ;
	public function __construct($layer, $minX, $minY, $minZ, $maxX, $maxY, $maxZ){
		$this->layer = $layer;
		$this->minX = $minX;
		$this->minY = $minY;
		$this->minZ = $minZ;
		$this->maxX = $maxX;
		$this->maxY = $maxY;
		$this->maxZ = $maxZ;
		
		if($this->minY < 0) $this->minY = 0;
		if($this->maxY > 127) $this->maxY = 127;
	}
	
	public function contains($minX, $minY, $minZ, $maxX, $maxY, $maxZ){
		return $this->minX <= $minX && $this->maxX >= $maxX && $this->minY <= $minY && $this->maxY >= $maxY && $this->minZ <= $minZ && $this->maxZ >= $maxZ;
	}
	
	public function update(Level $level){
		//if(($this->maxZ - $this->minZ+1)*($this->maxY - $this->minY+1)*($this->maxX - $this->minX+1) > ) return false;
		
		$isBlockLight = $this->layer == LIGHTLAYER_BLOCK;
		if($isBlockLight) $lightsa = &$level->level->blockLight;
		else $lightsa = &$level->level->skyLight;
		
		for($x = $this->minX; $x <= $this->maxX; ++$x){
			$xb = $x & 0xf;
			for($z = $this->minZ; $z <= $this->maxZ; ++$z){
				
				$zb = $z & 0xf;
				$index = PMFLevel::getIndex($x >> 4, $z >> 4);
				if(!isset($level->level->blockIds[$index])) continue;
				
				if(!isset($lightsa[PMFLevel::getIndex(($x-1) >> 4, ($z) >> 4)])) continue;
				if(!isset($lightsa[PMFLevel::getIndex(($x+1) >> 4, ($z) >> 4)])) continue;
				if(!isset($lightsa[PMFLevel::getIndex(($x) >> 4, ($z-1) >> 4)])) continue;
				if(!isset($lightsa[PMFLevel::getIndex(($x) >> 4, ($z+1) >> 4)])) continue;
				
				$ids = &$level->level->blockIds[$index];
				$xneglight = &$lightsa[PMFLevel::getIndex(($x-1) >> 4, ($z) >> 4)];
				$xposlight = &$lightsa[PMFLevel::getIndex(($x+1) >> 4, ($z) >> 4)];
				$zposlight = &$lightsa[PMFLevel::getIndex(($x) >> 4, ($z+1) >> 4)];
				$zneglight = &$lightsa[PMFLevel::getIndex(($x) >> 4, ($z-1) >> 4)];
				$lights = &$lightsa[$index];
				
				
				for($y = $this->minY; $y <= $this->maxY; ++$y){
					$bindex = ($xb << 11) | ($zb << 7) | $y;
					$mindex = $bindex >> 1;
					$upper = $y & 1;
					
					$id = ord($ids[$bindex]);
					//$brightness = $level->level->getBrightness($this->layer, $x, $y, $z);
					$braw = ord($lights[$mindex]);
					if($upper){
						$brightness = $braw >> 4;
						$brightness_low = ($braw & 0xf);
					}else{
						$brightness_high = $braw >> 4;
						$brightness = ($braw & 0xf);
					}
					
					$lightBlock = StaticBlock::$lightBlock[$id];
					if($lightBlock == 0) $lightBlock = 1;
					$lightEmission = 0;
					if($isBlockLight){
						$lightEmission = StaticBlock::$lightEmission[$id];
					}else{
						if($level->level->isSkyLit($x, $y, $z)) $lightEmission = 15;
					}
					
					if($lightBlock <= 14 || $lightEmission != 0){
						$xbm1 = ($x-1)&0xf;
						$xbp1 = ($x+1)&0xf;
						$zbm1 = ($z-1)&0xf;
						$zbp1 = ($z+1)&0xf;
						
						$xNeg = ord($xneglight[(($xbm1 << 11) | ($zb << 7) | $y) >> 1]);
						$xNeg = $upper ? $xNeg >> 4 : $xNeg & 0xf;
						$xPos = ord($xposlight[(($xbp1 << 11) | ($zb << 7) | $y) >> 1]);
						$xPos = $upper ? $xPos >> 4 : $xPos & 0xf;
						$zNeg = ord($zneglight[(($xb << 11) | ($zbm1 << 7) | $y) >> 1]);
						$zNeg = $upper ? $zNeg >> 4 : $zNeg & 0xf;
						$zPos = ord($zposlight[(($xb << 11) | ($zbp1 << 7) | $y) >> 1]);
						$zPos = $upper ? $zPos >> 4 : $zPos & 0xf;
						
						if($upper){
							$yNeg = $brightness_low;
							$yPos = $bindex+1 > 127 ? $this->layer : (ord($lights[($bindex+1) >> 1]) & 0xf);
						}else{
							$yNeg = ord($lights[($bindex-1) >> 1]) >> 4;
							$yPos = $bindex-1 < 0 ? 0 : $brightness_high;
						}
						/*$xNeg = $level->level->getBrightness($this->layer, $x-1, $y, $z);
						$xPos = $level->level->getBrightness($this->layer, $x+1, $y, $z);
						$yNeg = $level->level->getBrightness($this->layer, $x, $y-1, $z);
						$yPos = $level->level->getBrightness($this->layer, $x, $y+1, $z);
						$zNeg = $level->level->getBrightness($this->layer, $x, $y, $z-1);
						$zPos = $level->level->getBrightness($this->layer, $x, $y, $z+1);*/
						
						
						$v15 = $xNeg;
						if($xPos > $v15) $v15 = $xPos;
						if($yNeg > $v15) $v15 = $yNeg;
						if($yPos > $v15) $v15 = $yPos;
						if($zNeg > $v15) $v15 = $zNeg;
						if($zPos > $v15) $v15 = $zPos;
						$newBrightness = $v15 - $lightBlock;
						
						if($newBrightness < 0) $newBrightness = 0;
						if($lightEmission > $newBrightness) $newBrightness = $lightEmission;
						//console("$isBlockLight $id $v15 $lightBlock $lightEmission $newBrightness");
					}else{
						$newBrightness = 0;
						//console("NUL $newBrightness");
					}
					
					if($brightness != $newBrightness){
						if($upper){
							$lights[$mindex] = chr(($newBrightness << 4) | $brightness_low);
						}else{
							$lights[$mindex] = chr(($brightness_high << 4) | $newBrightness);
						}
						//$level->level->setBrightness($this->layer, $x, $y, $z, $newBrightness);
						
						$v4 = $newBrightness-1;
						if($v4 < 0) $v4 = 0;
						$level->updateLightIfOtherThan($this->layer, $x-1, $y, $z, $v4);
						$level->updateLightIfOtherThan($this->layer, $x, $y-1, $z, $v4);
						$level->updateLightIfOtherThan($this->layer, $x, $y, $z-1, $v4);
						if($x+1 >= $this->maxX) $level->updateLightIfOtherThan($this->layer, $x+1, $y, $z, $v4);
						if($y+1 >= $this->maxY) $level->updateLightIfOtherThan($this->layer, $x, $y+1, $z, $v4);
						if($z+1 >= $this->maxZ) $level->updateLightIfOtherThan($this->layer, $x, $y, $z+1, $v4);
					}
				}
			}
		}
	}
}

