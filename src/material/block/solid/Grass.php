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
			TallGrassObject::growGrass($this->level, $this, new Random(), 8, 2);
			return true;
		}elseif($item->isHoe()){
			if($this->getSide(1)->isTransparent === false) return false;
			if(($player->gamemode & 0x01) === 0){
				$item->useOn($this);
			}
			$this->level->setBlock($this, new FarmlandBlock());
			$this->seedsDrop();
			return true;
		}
		return false;
	}
	
	public function seedsDrop(){
		$chance = Utils::randomFloat() * 100;
		if($chance <= 1){
			ServerAPI::request()->api->entity->drop(new Position($this->x+0.5, $this->y+1, $this->z+0.5, $this->level), BlockAPI::getItem(458,0,1));
			return;
		}
		elseif($chance > 1 and $chance <= 16){
			ServerAPI::request()->api->entity->drop(new Position($this->x+0.5, $this->y+1, $this->z+0.5, $this->level), BlockAPI::getItem(295,0,1));
			return;
		}
                elseif($chance > 1 and $chance <= 17){
			ServerAPI::request()->api->entity->drop(new Position($this->x+0.5, $this->y+1, $this->z+0.5, $this->level), BlockAPI::getItem(361,0,1));
			return;
		}
                elseif($chance > 1 and $chance <= 18){
			ServerAPI::request()->api->entity->drop(new Position($this->x+0.5, $this->y+1, $this->z+0.5, $this->level), BlockAPI::getItem(362,0,1));
			return;
		}
		return;
	}

	public function onUpdate($type){
		$this->level->scheduleBlockUpdate(new Position($this, 0, 0, $this->level), Utils::getRandomUpdateTicks(), BLOCK_UPDATE_RANDOM);
		if($type === BLOCK_UPDATE_RANDOM){
			if(mt_rand(0, 2) == 1){
				if($this->getSide(1)->isTransparent === false){
					$this->level->setBlock($this, BlockAPI::get(DIRT, 0), true, false, true);
					return BLOCK_UPDATE_RANDOM;
				}
			}
			return false;
		}
	}

}
