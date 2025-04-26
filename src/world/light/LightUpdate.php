<?php

class LightUpdate extends LightUpdateBase
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
		return $this->minX <= $minX && $this->minY <= $minY && $this->minZ <= $minZ && $this->maxX >= $maxX && $this->maxY >= $maxY && $this->maxZ >= $maxZ;
	}
	
	const ALREADY_CONTAINED = 2;
	const SUCCESS = 1;
	const NOT_POSSIBLE = 0;
	public function expand($minX, $minY, $minZ, $maxX, $maxY, $maxZ){
		if($this->minX <= $minX && $this->minY <= $minY && $this->minZ <= $minZ && $this->maxX >= $maxX && $this->maxY >= $maxY && $this->maxZ >= $maxZ){
			return self::ALREADY_CONTAINED;
		}
		
		if($this->minX-1 > $minX || $this->minY-1 > $minY || $this->minZ-1 > $minZ || $this->maxX+1 < $maxX || $this->maxY+1 < $maxY || $this->maxZ+1 < $maxZ){
			return self::NOT_POSSIBLE;
		}
		
		if($this->minX < $minX) $minX = $this->minX;
		if($this->minY < $minY) $minY = $this->minY;
		if($this->minZ < $minZ) $minZ = $this->minZ;
		if($this->maxX < $maxX) $maxX = $this->maxX;
		if($this->maxY < $maxY) $maxY = $this->maxY;
		if($this->maxZ < $maxZ) $maxZ = $this->maxZ;
		
		if(($maxZ-$minZ)*($maxY-$minY)*($maxX-$minX) - ($this->maxX-$this->minX)*($this->maxZ-$this->minZ)*($this->maxY-$this->minY) > 2){
			return self::NOT_POSSIBLE;
		}
		$this->minX = $minX;
		$this->minY = $minY;
		$this->minZ = $minZ;
		$this->maxX = $maxX;
		$this->maxY = $maxY;
		$this->maxZ = $maxZ;
		return self::SUCCESS;
	}
	
	public function update(Level $level){
		//if(($this->maxZ - $this->minZ+1)*($this->maxY - $this->minY+1)*($this->maxX - $this->minX+1) > ) return false;
		
		$isBlockLight = $this->layer == LIGHTLAYER_BLOCK;
		if($isBlockLight) $lightsa = &$level->level->blockLight;
		else $lightsa = &$level->level->skyLight;
		
		for($x = $this->minX; $x <= $this->maxX; ++$x){
			$xb = $x & 0xf;
			$xbm1 = ($x-1)&0xf;
			$xbp1 = ($x+1)&0xf;
			
			for($z = $this->minZ; $z <= $this->maxZ; ++$z){
				$zbm1 = ($z-1)&0xf;
				$zbp1 = ($z+1)&0xf;
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
							$yPos = $y+1 > 127 ? $this->layer : (ord($lights[($bindex+1) >> 1]) & 0xf);
						}else{
							$yNeg = $y-1 < 0 ? 0 : ord($lights[($bindex-1) >> 1]) >> 4;
							$yPos = $brightness_high;
						}
						
						
						$v15 = $xNeg;
						if($xPos > $v15) $v15 = $xPos;
						if($yNeg > $v15) $v15 = $yNeg;
						if($yPos > $v15) $v15 = $yPos;
						if($zNeg > $v15) $v15 = $zNeg;
						if($zPos > $v15) $v15 = $zPos;
						$newBrightness = $v15 - $lightBlock;
						
						if($newBrightness < 0) $newBrightness = 0;
						if($lightEmission > $newBrightness) $newBrightness = $lightEmission;
					}else{
						$newBrightness = 0;
					}
					
					if($brightness != $newBrightness){
						if($upper){
							$lights[$mindex] = chr(($newBrightness << 4) | $brightness_low);
						}else{
							$lights[$mindex] = chr(($brightness_high << 4) | $newBrightness);
						}
						
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

