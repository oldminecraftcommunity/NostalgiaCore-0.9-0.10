<?php

class Minecart extends Vehicle{
	const TYPE = OBJECT_MINECART;
	private $moveVector = [];
	const STATE_INITIAL = 0;
	const STATE_ON_RAIL = 1;
	const STATE_OFF_RAIL = 2;
	
	function __construct(Level $level, $eid, $class, $type = 0, $data = []){
		parent::__construct($level, $eid, $class, $type, $data);
		$this->canBeAttacked = true;
		$this->x = isset($this->data["TileX"]) ? (float)$this->data["TileX"]:$this->x;
		$this->y = isset($this->data["TileY"]) ? (float)$this->data["TileY"]:$this->y;
		$this->z = isset($this->data["TileZ"]) ? (float)$this->data["TileZ"]:$this->z;
		$this->setHealth(3, "generic");
		//$this->setName((isset($objects[$this->type]) ? $objects[$this->type]:$this->type));
		$this->width = 0.98;
		$this->height = 0.7;
		$this->update();
	}
	
	public function getDrops(){
		return [
			[MINECART, 0, 1]
		];
	}
	
	public function spawn($player){
		$pk = new AddEntityPacket;
		$pk->eid = $this->eid;
		$pk->type = $this->type;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$player->dataPacket($pk);
					
		$pk = new SetEntityMotionPacket;
		$pk->entities = [[$this->eid, $this->speedX, $this->speedY, $this->speedZ]];
		$player->dataPacket($pk);
	}
	
	public function interactWith(Entity $e, $action){
		if($action === InteractPacket::ACTION_HOLD && $e->isPlayer() && $this->canRide($e)){
			$this->linkedEntity = $e;
			$e->isRiding = true;
			$this->linkEntity($e, SetEntityLinkPacket::TYPE_RIDE);
			return true;
		}
		if($e->isPlayer() && $action === InteractPacket::ACTION_HOLD){
			$this->linkEntity($e, SetEntityLinkPacket::TYPE_REMOVE);
			$this->linkedEntity = 0;
			$e->isRiding = false;
			return true;
		}
		parent::interactWith($e, $action);
	}
	
	public function canRide($e)
	{
	   return !($this->linkedEntity instanceof Entity) && !$e->isRiding;
	}

}
