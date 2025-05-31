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
	
	public function __toString(){
		return "LightUpdate({$this->minX} {$this->minY} {$this->minZ} {$this->maxX} {$this->maxY} {$this->maxZ} {$this->layer})";
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
		if($this->maxX > $maxX) $maxX = $this->maxX;
		if($this->maxY > $maxY) $maxY = $this->maxY;
		if($this->maxZ > $maxZ) $maxZ = $this->maxZ;
		
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
	static $hist = [];
	public function update(Level $level){
		$layer = $this->layer;
		for($x = $this->minX; $x <= $this->maxX; ++$x){
			for($z = $this->minZ; $z <= $this->maxZ; ++$z){
				if(!$level->level->isChunkLoaded($x >> 4, $z >> 4)){
					console(($x>>4)." ".($z>>4)." is not loaded skipping");
					continue;
				}
				for($y = $this->minY; $y <= $this->maxY; ++$y){
					$brightness = $level->level->getBrightness($layer, $x, $y, $z);
					$blockID = $level->level->getBlockID($x, $y, $z);
					$lightBlock = StaticBlock::$lightBlock[$blockID];
					if($lightBlock == 0) $lightBlock = 1;
					$lightEmission = 0;
					if($layer == LIGHTLAYER_SKY){
						if($level->level->isSkyLit($x, $y, $z)) $lightEmission = 15;
					}else{
						$lightEmission = StaticBlock::$lightEmission[$blockID];
					}
					
					if($lightBlock <= 14 || $lightEmission != 0){
						$xNegBright = $level->level->getBrightness($layer, $x-1, $y, $z);
						$xPosBright = $level->level->getBrightness($layer, $x+1, $y, $z);
						$yNegBright = $level->level->getBrightness($layer, $x, $y-1, $z);
						$yPosBright = $level->level->getBrightness($layer, $x, $y+1, $z);
						$zNegBright = $level->level->getBrightness($layer, $x, $y, $z-1);
						$zPosBright = $level->level->getBrightness($layer, $x, $y, $z+1);
					
						$v15 = $xNegBright;
						if($xPosBright > $v15) $v15 = $xPosBright;
						if($yNegBright > $v15) $v15 = $yNegBright;
						if($yPosBright > $v15) $v15 = $yPosBright;
						if($zNegBright > $v15) $v15 = $zNegBright;
						if($zPosBright > $v15) $v15 = $zPosBright;
						
						$newBrightness = $v15 - $lightBlock;
						if($newBrightness < 0) $newBrightness = 0;
						if($lightEmission > $newBrightness) $newBrightness = $lightEmission;
					}else{
						$newBrightness = 0;
					}
					
					if($brightness != $newBrightness){
						$level->level->setBrightness($layer, $x, $y, $z, $newBrightness);
						$v4 = $newBrightness - 1;
						if($v4 < 0) $v4 = 0;
						$level->updateLightIfOtherThan($layer, $x-1, $y, $z, $v4);
						$level->updateLightIfOtherThan($layer, $x, $y-1, $z, $v4);
						$level->updateLightIfOtherThan($layer, $x, $y, $z-1, $v4);
						
						if($x+1 >= $this->maxX) $level->updateLightIfOtherThan($layer, $x+1, $y, $z, $v4);
						if($y+1 >= $this->maxY) $level->updateLightIfOtherThan($layer, $x, $y+1, $z, $v4);
						if($z+1 >= $this->maxZ) $level->updateLightIfOtherThan($layer, $x, $y, $z+1, $v4);
					}
				}
			}
		}
	}
}

