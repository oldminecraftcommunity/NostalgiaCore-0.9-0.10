<?php

class GrassBlock extends SolidBlock{
	public function __construct(){
		parent::__construct(GRASS, 0, "Grass");
		$this->isActivable = true;
		$this->hardness = 3;
	}

	public function getDrops(Item $item, Player $player){
		return array(
			array(DIRT, 0, 1),
		);
	}

	public function onActivate(Item $item, Player $player){
		if($item->getID() === DYE and $item->getMetadata() === 0x0F){
			if(($player->gamemode & 0x01) === 0){
				$player->removeItem(DYE,0x0F,1);
			}
			TallGrassObject::useBonemeal($this->level, $this, new XorShift128Random());
			return true;
		}elseif($item->isHoe()){
			if($this->getSide(1)->isTransparent === false) return false;
			if(($player->gamemode & 0x01) === 0){
				$item->useOn($this);
				if($item->getMetadata() >= $item->getMaxDurability()) $player->setSlot($player->slot, new Item(AIR, 0, 0), false);
				else $player->setSlot($player->slot, $item, true);
			}
			$this->level->setBlock($this, new FarmlandBlock());
			//$this->seedsDrop();
			return true;
		}
		return false;
	}
	
	/*public function seedsDrop(){
		$chance = Utils::randomFloat() * 100;
		if($chance <= 1){
			ServerAPI::request()->api->entity->drop(new Position($this->x+0.5, $this->y+1, $this->z+0.5, $this->level), BlockAPI::getItem(458,0,1));
			return;
		}
		elseif($chance > 1 and $chance <= 16){
			ServerAPI::request()->api->entity->drop(new Position($this->x+0.5, $this->y+1, $this->z+0.5, $this->level), BlockAPI::getItem(295,0,1));
			return;
		}
		return;
	}*/

	public function onUpdate($type){
		if($type === BLOCK_UPDATE_RANDOM && !$this->getSide(1)->isTransparent && mt_rand(0, 2) == 1){
			$this->level->setBlock($this, BlockAPI::get(DIRT, 0), true, false, true);
		}
		return BLOCK_UPDATE_RANDOM;
	}
}
