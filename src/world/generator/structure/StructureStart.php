<?php

abstract class StructureStart
{
	/**
	 * @var StructureComponent[]
	 */
	public $components = [];
	public $boundingBox;
	
	public function generateStructure(Level $level, IRandom $random, AxisAlignedBB $boundingBox){
		foreach($this->components as $k => $component){
			if($component->boundingBox->intersectsWith($boundingBox) && !$component->addComponentParts($level, $random, $boundingBox)){
				unset($this->components[$k]);
			}
		}
	}
	
	public function updateBoundingBox(){
		$this->boundingBox = new StructureAABB(0, 0, 0, 0, 0, 0);
		foreach($this->components as $k => $component){
			$this->boundingBox->expandTo($component->boundingBox);
		}
	}
	
	public function markAvailableHeight(Level $level, IRandom $random, $height){
		$var4 = 63 - $height;
		$var5 = $this->boundingBox->getYSize() + 1;
		
		if($var5 < $var4) $var5 += $random->nextInt($var4 - $var5);
		
		$var6 = (int)($var5 - $this->boundingBox->maxY);
		$this->boundingBox->offset(0, $var6, 0);
		
		foreach($this->components as $component){
			$component->boundingBox->offset(0, $var6, 0);
		}
	}
	
	public function setRandomHeight(Level $level, IRandom $random, $par3, $par4){
		$var5 = (int)($par4 - $par3 + 1 - $this->boundingBox->getYSize());
		$var10 = $var5 > 1 ? $par3 + $random->nextInt($var5) : $par3;
		$var6 = (int)($var10 - $this->boundingBox->minY);
		$this->boundingBox->offset(0, $var6, 0);
		foreach($this->components as $component){
			$component->boundingBox->offset(0, $var6, 0);
		}
	}
	
	public function isSizeableStructure(){
		return true;
	}
}

