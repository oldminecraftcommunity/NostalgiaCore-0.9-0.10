<?php

class FarmlandBlock extends TransparentBlock{
	public function __construct($meta = 0){
		parent::__construct(FARMLAND, $meta, "Farmland");
		$this->hardness = 3;
		$this->boundingBox = new AxisAlignedBB($this->x, $this->y, $this->z, $this->x + 1, $this->y + 1, $this->z + 1);
	}

	public function getDrops(Item $item, Player $player){
		return [
			[DIRT, 0, 1]
		];
	}

	public function hasCrops(){
		//TODO vanilla 0.8.1 detection method
		$b = $this->getSide(1);
		return $b->isTransparent && $b->id != 0;
	}

	public function onUpdate($type){
		if($type === BLOCK_UPDATE_NORMAL){
			if(!$this->getSide(1)->isTransparent){
				$this->level->setBlock($this, BlockAPI::get(DIRT, 0), true, false, true);
				return $type;
			}
		}
		if($type === BLOCK_UPDATE_RANDOM){
			if($this->meta === 0 && mt_rand(0,5) == 0){
				$water = $this->checkWater();
				if($water){
					$this->level->setBlock($this, BlockAPI::get(FARMLAND, 1), true, false, true);
				}elseif(!$this->hasCrops()){
					$this->level->setBlock($this, BlockAPI::get(DIRT, 0), true, false, true);
				}
			}
			if(!$this->getSide(1)->isFlowable){
				$this->level->setBlock($this, BlockAPI::get(DIRT, 0), true, false, true);
			}
			return $type;
		}
		return false;
	}

	public function getBlockID($x, $y, $z){
		return $this->level->level->getBlockID($x, $y, $z); //PMFLevel method
	}

	public function checkWater(){
		for($x = $this->x - 4; $x <= $this->x + 4; $x++){
			for($y = $this->y; $y <= $this->y + 1; $y++){
				for($z = $this->z - 4; $z <= $this->z + 4; $z++){
					$id = $this->getBlockID($x, $y, $z);
					if($id === 8 || $id === 9){
						return true;
					}
				}
			}
		}
		return false;
	}
}
