<?php

class Entity extends Position
{

	const TYPE = -1;
	const CLASS_TYPE = -1;
	const MIN_POSSIBLE_SPEED = 1/8000; //anything below will send 0 to player
	
	public $counter = 0;
	public $invincible, $status, $health, $fire, $crouched, $position, $meta, $stack;
	public static $updateOnTick, $allowedAI;
	public $isCollidable;
	public $canBeAttacked;
	public $moveTime, $lookTime, $idleTime, $knockbackTime = 0;
	public $needsUpdate = true;
	public $speedModifer;
	public $hasGravity;
	/**
	 * @var AxisAlignedBB
	 */
	public $boundingBox;
	public $age;
	public $air;
	public $spawntime;
	public $dmgcounter;
	public $eid;
	public $type;
	public $name;
	public $x, $y, $z;
	public $speedX, $speedY, $speedZ, $speed;
	public $lastX = 0, $lastY  = 0, $lastZ  = 0, $lastYaw  = 0, $lastPitch  = 0, $lastTime = 0, $lastHeadYaw = 0, $lastSpeedX = 0, $lastSpeedY = 0, $lastSpeedZ = 0;
	/**
	 * 0 = lastX, 
	 * 1 = lastY, 
	 * 2 = lastZ, 
	 * 3 = lastYaw, 
	 * 4 = lastPitch, 
	 * 5 = lastTime. 
	 * 
	 * It is not recommended to use this and it is left as a backwards compability.
	 * 
	 * @var array
	 */
	public $last;
	public $yaw, $headYaw;
	public $pitch;
	public $dead;
	public $data;
	public $class;
	public $attach;
	public $closed;
	/**
	 * @var Player
	 */
	public $player;
	public $fallY;
	public $fallStart;
	private $state;
	private $tickCounter;
	private $speedMeasure = array(0, 0, 0, 0, 0, 0, 0);
	public $server;
	private $isStatic;
	public $level;
	public $isRiding = false;
	public $lastUpdate;
	public $linkedEntity = null;
	public $check = true;
	public $width = 1;
	public $height = 1;
	public $random;
	public $radius;
	public $inAction = false;
	public $hasKnockback;
	public $hasJumped;
	public $onGround, $inWater;
	public $carryoverDamage;
	public $gravity;
	public $delayBeforePickup;
	
	function __construct(Level $level, $eid, $class, $type = 0, $data = array())
	{
		$this->random = new Random();
		$this->last = [&$this->lastX, &$this->lastY, &$this->lastZ, &$this->lastYaw, &$this->lastPitch, &$this->lastTime]; //pointers to variables
		$this->canBeAttacked = false;
		$this->hasKnockback = false;
		$this->level = $level;
		$this->speedModifer = 0.7;
		$this->fallY = false;
		$this->fallStart = false;
		$this->server = ServerAPI::request();
		$this->eid = (int) $eid;
		$this->type = (int) $type;
		$this->class = (int) $class;
		$this->player = false;
		$this->attach = false;
		$this->data = $data;
		$this->status = 0;
		$this->health = 20;
		$this->hasGravity = false;
		$this->dmgcounter = array(0, 0, 0);
		$this->air = 200;
		$this->fire = 0;
		$this->crouched = false;
		$this->invincible = false;
		$this->lastUpdate = $this->spawntime = microtime(true);
		$this->dead = false;
		$this->closed = false;
		$this->isStatic = false;
		$this->name = "";
		$this->state = $this->data["State"] = isset($this->data["State"]) ? $this->data["State"] : 0;
		$this->tickCounter = 0;
		$this->server->query("INSERT OR REPLACE INTO entities (EID, level, type, class, health, hasUpdate) VALUES (" . $this->eid . ", '" . $this->level->getName() . "', " . $this->type . ", " . $this->class . ", " . $this->health . ", 0);");
		$this->x = isset($this->data["x"]) ? (float) $this->data["x"] : 0;
		$this->y = isset($this->data["y"]) ? (float) $this->data["y"] : 0;
		$this->z = isset($this->data["z"]) ? (float) $this->data["z"] : 0;
		$this->speedX = isset($this->data["speedX"]) ? (float) $this->data["speedX"] : 0;
		$this->speedY = isset($this->data["speedY"]) ? (float) $this->data["speedY"] : 0;
		$this->speedZ = isset($this->data["speedZ"]) ? (float) $this->data["speedZ"] : 0;
		$this->speed = 0;
		$this->yaw = isset($this->data["yaw"]) ? (float) $this->data["yaw"] : 0;
		$this->headYaw = isset($this->data["headYaw"]) ? $this->data["headYaw"] : $this->yaw;
		$this->pitch = isset($this->data["pitch"]) ? (float) $this->data["pitch"] : 0;
		$this->position = array(
			"level" => $this->level,
			"x" => &$this->x,
			"y" => &$this->y,
			"z" => &$this->z,
			"yaw" => &$this->yaw,
			"pitch" => &$this->pitch
		);
		$this->moveTime = 0;
		$this->lookTime = 0;
		$this->onGround = false;
		$this->gravity = 0.08;
		switch($this->class) {
			case ENTITY_PLAYER:
				$this->player = $this->data["player"];
				$this->setHealth($this->health, "generic");
				$this->speedModifer = 1;
				$this->width = 0.6;
				$this->height = 1.8;
				$this->hasKnockback = true;
				$this->hasGravity = true;
				$this->canBeAttacked = true;
				break;
			case ENTITY_ITEM:
				if(isset($data["item"]) and ($data["item"] instanceof Item)){
					$this->meta = $this->data["item"]->getMetadata();
					$this->stack = $this->data["item"]->count;
				} else{
					$this->meta = (int) $this->data["meta"];
					$this->stack = (int) $this->data["stack"];
				}
				$this->gravity = 0.06;
				$this->hasGravity = true;
				$this->setHealth(5, "generic");
				$this->setSize(0.25, 0.25);
				$this->delayBeforePickup = 40; //in vanilla it is 0 by default
				break;
			case ENTITY_FALLING:
				$this->setHealth(PHP_INT_MAX, "generic");
				$this->height = 0.98;
				$this->width = 0.98;
				$this->gravity = 0.04;
				$this->hasGravity = true;
				break;
			case ENTITY_OBJECT:
				$this->x = isset($this->data["TileX"]) ? $this->data["TileX"] : $this->x;
				$this->y = isset($this->data["TileY"]) ? $this->data["TileY"] : $this->y;
				$this->z = isset($this->data["TileZ"]) ? $this->data["TileZ"] : $this->z;
				$this->setHealth(1, "generic");
				// $this->setName((isset($objects[$this->type]) ? $objects[$this->type]:$this->type));
				$this->width = 1;
				$this->height = 1;
				if($this->type === OBJECT_SNOWBALL){
					$this->server->schedule(1210, array(
						$this,
						"update"
					)); // Despawn
						// $this->update();
				}
				break;
		}
		$this->radius = $this->width / 2;
		$this->boundingBox = new AxisAlignedBB($this->x - $this->radius, $this->y, $this->z - $this->radius, $this->x + $this->radius, $this->y + $this->height, $this->z + $this->radius);
		//$this->update();
		$this->updateLast();
		$this->updatePosition();
		if($this->isInVoid()){
			$this->outOfWorld();
		}
	}
	
	public function isType(){
		return in_array($this->type, func_get_args());
	}
	
	public function attackEntity($entity){

	}
	
	public function addVelocity($vX, $vY = 0, $vZ = 0)
	{
		if($vX instanceof Vector3){
			return $this->addVelocity($vX->x, $vX->y, $vX->z);
		}
		$this->speedX += $vX;
		$this->speedY += $vY;
		$this->speedZ += $vZ;
	}
	public function isMovingHorizontally()
	{
		return ($this->speedX >= self::MIN_POSSIBLE_SPEED || $this->speedX <= -self::MIN_POSSIBLE_SPEED) || ($this->speedZ >= self::MIN_POSSIBLE_SPEED || $this->speedZ <= -self::MIN_POSSIBLE_SPEED);
	}
	public function isMoving()
	{
		return $this->isMovingHorizontally() || ($this->speedY > self::MIN_POSSIBLE_SPEED || $this->speedY < -self::MIN_POSSIBLE_SPEED);
	}

	public function setVelocity($vX, $vY = 0, $vZ = 0)
	{
		if($vX instanceof Vector3){
			return $this->setVelocity($vX->x, $vX->y, $vX->z);
		}
		$this->speedX = $vX;
		$this->speedY = $vY;
		$this->speedZ = $vZ;
	}

	/**
	 *
	 * @param mixed $e
	 *			Entity instance or EID
	 * @return string|false if failed
	 */
	public static function getNameOf($e)
	{
		if($e instanceof Entity){
			return $e->getName();
		} elseif(($e = ServerAPI::request()->api->entity->get($e)) != false){
			return $e->getName();
		}
		return false;
	}

	/**
	 *
	 * @param mixed $e
	 *			Entity instance or EID
	 * @return number|false if failed
	 */
	public static function getHeightOf($e)
	{
		if($e instanceof Entity){
			return $e->getHeight();
		} elseif(($e = ServerAPI::request()->api->entity->get($e)) != false){
			return $e->getHeight();
		}
		return false;
	}

	/**
	 *
	 * @param mixed $e
	 *			Entity instance or EID
	 * @return number|false if failed
	 */
	public static function getWidthOf($e)
	{
		if($e instanceof Entity){
			return $e->getWidth();
		} elseif(($e = ServerAPI::request()->api->entity->get($e)) != false){
			return $e->getWidth();
		}
		return false;
	}

	/**
	 * Get an entity height
	 *
	 * @return number
	 */
	public function getHeight($a)
	{
		return $this->height;
	}

	/**
	 * Get an entity width
	 *
	 * @return number
	 */
	public function getWidth()
	{
		return $this->width;
	}

	public function lookOn(Vector3 $target)
	{
		$horizontal = sqrt(pow(($target->x - $this->x), 2) + pow(($target->z - $this->z), 2));
		$vertical = $target->y - ($this->y + - 0.5); /* 0.5 = $entity->getEyeHeight() */
		$pitch = - asin($horizontal) / M_PI * 180; // negative is up, positive is down

		$xDist = $target->x - $this->x;
		$zDist = $target->z - $this->z;

		$yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;
		if($yaw < 0){
			$yaw += 360.0;
		}
		$this->yaw = $yaw;
		$this->pitch = $pitch;
		$this->server->query("UPDATE entities SET pitch = " . $this->pitch . ", yaw = " . $this->yaw . " WHERE EID = " . $this->eid . ";");
	}

	public function getDrops()
	{
		if($this->class === ENTITY_PLAYER and $this->player instanceof Player and ($this->player->gamemode & 0x01) === 0){
			$inv = [];
			for($i = 0; $i < PLAYER_SURVIVAL_SLOTS; ++ $i){
				$slot = $this->player->getSlot($i);
				$this->player->setSlot($i, BlockAPI::getItem(AIR, 0, 0));
				if($slot->getID() !== AIR and $slot->count > 0){
					$inv[] = array(
						$slot->getID(),
						$slot->getMetadata(),
						$slot->count
					);
				}
			}
			for($re = 0; $re < 4; $re ++){
				$slot = $this->player->getArmor($re);
				$this->player->setArmor($re, BlockAPI::getItem(AIR, 0, 0));
				if($slot->getID() !== AIR and $slot->count > 0){
					$inv[] = array(
						$slot->getID(),
						$slot->getMetadata(),
						$slot->count
					);
				}
			}
			return $inv;
		}
		return [];
	}

	private function spawnDrops()
	{
		foreach($this->getDrops() as $drop){
			$this->server->api->entity->drop($this, BlockAPI::getItem($drop[0] & 0xFFFF, $drop[1] & 0xFFFF, $drop[2] & 0xFF));
		}
	}

	public function environmentUpdate()
	{
		$hasUpdate = Entity::$updateOnTick ? $this->class === ENTITY_MOB : false; // force true for mobs
		$time = microtime(true);
		if($this->class === ENTITY_PLAYER and ($this->player instanceof Player) and $this->player->spawned === true and $this->player->blocked !== true && ! $this->dead){
			$myBB = $this->boundingBox->grow(1, 0.5, 1);
			foreach($this->server->api->entity->getRadius($this, 2, ENTITY_ITEM) as $item){ //TODO vanilla method of searching/radius
				if(!$item->closed && $item->spawntime > 0 && $item->delayBeforePickup == 0){
					if($item->boundingBox->intersectsWith($myBB)){ 
						if((($this->player->gamemode & 0x01) === 1 || $this->player->hasSpace($item->type, $item->meta, $item->stack) === true) && $this->server->api->dhandle("player.pickup", array(
							"eid" => $this->player->eid,
							"player" => $this->player,
							"entity" => $item,
							"block" => $item->type,
							"meta" => $item->meta,
							"target" => $item->eid
						)) !== false){
							$item->close();
						}
					}
				}
			}
		} elseif($this->class === ENTITY_ITEM){
			if(($time - $this->spawntime) >= 300){
				$this->close(); // Despawn timer
				return false;
			}
		} elseif($this->class === ENTITY_OBJECT and $this->type === OBJECT_SNOWBALL){
			if(($time - $this->spawntime) >= 60){
				$this->close();
				return false;
			}
		}

		if($this->class !== ENTITY_PLAYER and ($this->y >= 128 or $this->y <= 0)){
			$this->close();
			return false;
		}

		if($this->dead === true){
			$this->fire = 0;
			$this->air = 200;
			return false;
		}
		if($this->isInVoid()){
			$this->outOfWorld();
			$hasUpdate = true;
		}

		if($this->fire > 0){
			if(($this->fire % 20) === 0){
				$this->harm(1, "burning");
			}
			$this->fire -= 10;
			if($this->fire <= 0){
				$this->fire = 0;
				$this->updateMetadata();
			} else{
				$hasUpdate = true;
			}
			if(($this->player instanceof Player) and ($this->player->gamemode & 0x01) === CREATIVE){
				$this->fire = 0;
			}
		}

		$startX = floor($this->boundingBox->minX);
		$startY = floor($this->boundingBox->minY);
		$startZ = floor($this->boundingBox->minZ);
		$endX = ceil($this->boundingBox->maxX);
		$endY = ceil($this->boundingBox->maxY);
		$endZ = ceil($this->boundingBox->maxZ);
		if(!($this instanceof Painting) && !($this->isPlayer() && $this->player->isSleeping !== false)){
			for($i = 0; $i < 8; ++$i){
				$x = ((($i >> 0) % 2) - 0.5) * $this->width * 0.8;
				$y= ((($i >> 1) % 2) - 0.5) * 0.1;
				$z = ((($i >> 2) % 2) - 0.5) * $this->width * 0.8;
				
				$blockX = floor($this->x + $x);
				$blockY = floor($this->y + $this->getEyeHeight() + $y);
				$blockZ = floor($this->z + $z);
			
				if(StaticBlock::getIsSolid($this->level->level->getBlockID($blockX, $blockY, $blockZ))){
					$this->harm(1, "suffocation"); // Suffocation
					$hasUpdate = true;
					break;
				}
			}
		}
		
		
		$waterDone = false;
		for ($y = $startY; $y <= $endY; ++$y){
			for ($x = $startX; $x <= $endX; ++$x){
				for ($z = $startZ; $z <= $endZ; ++$z){
					$pos = new Vector3($x, $y, $z);
					$b = $this->level->getBlock($pos);
					switch ($b->getID()) {
						case WATER:
						case STILL_WATER: // Drowing
							if ($this->fire > 0 and $this->inBlock($pos)) {
								$this->fire = 0;
								$this->updateMetadata();
							}
							if (($this->air <= 0 && ! $waterDone) || $this->type === MOB_ENDERMAN) { //TODO check is correct
								$this->harm(2, "water");
								$hasUpdate = true;
								$waterDone = true;
							} elseif ($x == ($endX - 1) and $y == $endY and $z == ($endZ - 1 - (($b->getMetadata() % 8) / 9)) and ($this->class === ENTITY_MOB or $this->class === ENTITY_PLAYER) and !$waterDone) {
								$this->air -= 1;
								$waterDone = true;
								$this->updateMetadata();
								$hasUpdate = true;
							}
							break;
						case LAVA: // Lava damage
						case STILL_LAVA:
							if ($this->inBlock($pos)) {
								$this->harm(5, "lava");
								$this->fire = 300;
								$this->updateMetadata();
								$hasUpdate = true;
							}
							break;
						case FIRE: // Fire block damage
							if ($this->inBlock($pos)) {
								$this->harm(1, "fire");
								$this->fire = 300;
								$this->updateMetadata();
								$hasUpdate = true;
							}
							break;
						case CACTUS: // Cactus damage
							if ($this->touchingBlock($pos)) {
								$this->harm(1, "cactus");
								$hasUpdate = true;
							}
							break;
					}
				}
			}
		}
		return $hasUpdate;
	}
	
	public function updateEntityMovement(){}

	public function isInVoid(){
		return $this->y < -1.6;
	}

	
	public function update(){
		if($this->closed === true){
			return false;
		}
		
		$now = microtime(true);
		if($this->check === false){
			$this->lastUpdate = $now;
			return;
		}
		
		if(!$this->isPlayer() && !isset($this->level->usedChunks[((int)$this->x >> 4).".".((int)$this->z >> 4)])){
			$this->close();
			return false;
		}
		
		$tdiff = $now - $this->lastUpdate;
		if($this->tickCounter === 0){
			$this->tickCounter = 1;
			$hasUpdate = $this->environmentUpdate();
		} else{
			$hasUpdate = true;
			$this->tickCounter = 0;
		}

		if($this->closed === true){
			return false;
		}
		++$this->counter;
		if($this->isStatic === false){
			$startX = floor($this->x - 0.5 - $this->width - 1);
			// prefix for flying when player on fence
			$y = floor($this->y - 1);
			$yC = ceil($this->y - 1);
			$startZ = floor($this->z - 0.5 - $this->width - 1);
			$endX = ceil($this->x - 0.5 + $this->width + 1);
			$endZ = ceil($this->z - 0.5 + $this->width + 1);
			$support = false;
			$isFlying = true;
			
			if($this->isPlayer()){
				for($z = $startZ; $z <= $endZ; ++ $z){
					for($x = $startX; $x <= $endX; ++ $x){
						$v = new Vector3($x, $y, $z);
						$v1 = new Vector3($x, $yC, $z);
						if($this->isSupport($v, $this->width)){
							$b = $this->level->getBlock($v);
							if($b->isSolid === true){
								$support = true;
								$isFlying = false;
								break;
							} elseif(($b instanceof LiquidBlock) or $b->getID() === COBWEB or $b->getID() === LADDER or $b->getID() === FENCE or $b->getID() === STONE_WALL or $b->getID() === IRON_BARS){
								$isFlying = false;
							}
						} elseif($this->isSupport($v1, $this->width)){
							$b = $this->level->getBlock($v1);
							if($b->isSolid === true){
								$support = true;
								$isFlying = false;
								break;
							} elseif(($b instanceof LiquidBlock) or $b->getID() === COBWEB or $b->getID() === LADDER or $b->getID() === FENCE or $b->getID() === STONE_WALL or $b->getID() === IRON_BARS){
								$isFlying = false;
							}
						}
					}
					if($support === true){
						break;
					}
				}
			}
			if(!$this->isPlayer()){
				$this->updateEntityMovement();
				$update = false;
				if($this->speedX > -self::MIN_POSSIBLE_SPEED && $this->speedX < self::MIN_POSSIBLE_SPEED){
					$this->speedX = 0;
				}
				if($this->speedZ > -self::MIN_POSSIBLE_SPEED && $this->speedZ < self::MIN_POSSIBLE_SPEED){
					$this->speedZ = 0;
				}
				if($this->speedY > -self::MIN_POSSIBLE_SPEED && $this->speedY < self::MIN_POSSIBLE_SPEED){
					$this->speedY = 0;
				}
				
				$horizontalMultiplyFactor = 0.91;
				if($this->onGround){
					$horizontalMultiplyFactor = 0.54;
					$b = $this->level->level->getBlockID(floor($this->x), floor($this->boundingBox->minX) - 1, floor($this->z));
					if($b > 0){
						$horizontalMultiplyFactor = StaticBlock::getSlipperiness($b) * 0.91;
					}
				}
				if($this->inWater){
					$this->speedX *= 0.8;
					$this->speedY *= 0.8;
					$this->speedZ *= 0.8;
				}else{
					$this->speedX *= $horizontalMultiplyFactor;
					$this->speedY *= 0.98;
					$this->speedZ *= $horizontalMultiplyFactor;
				}
				
				$savedSpeedY = $this->speedY;
				if($this->class === ENTITY_MOB || $this->class === ENTITY_ITEM || ($this->class === ENTITY_OBJECT && $this->type === OBJECT_PRIMEDTNT) || $this->class === ENTITY_FALLING){
					$water = false;
					$aABB = $this->boundingBox->addCoord($this->speedX, $this->speedY, $this->speedZ);
					$x0 = floor($aABB->minX);
					$x1 = ceil($aABB->maxX);
					$y0 = floor($aABB->minY);
					$y1 = ceil($aABB->maxY);
					$z0 = floor($aABB->minZ);
					$z1 = ceil($aABB->maxZ);
					//$x1 = $x1 > 256 ? 256 : $x1;
					//$y1 = $y1 > 128 ? 128 : $y1;
					//$z1 = $z1 > 256 ? 256 : $z1;
					for($x = $x0; $x < $x1; ++$x){
						for($y = $y0; $y < $y1; ++$y){
							for($z = $z0; $z < $z1; ++$z){
								$pos = new Vector3($x, $y, $z);
								$b = $this->level->getBlock($pos);
								if($b->y == ($y1 - 1) && ($b->getID() === WATER || $b->getID() === STILL_WATER)){
									$water = true;
								}
								if($b != false && $b->isSolid){
									$this->speedY = $b->boundingBox->calculateYOffset($this->boundingBox, $this->speedY);
									$this->speedX = $b->boundingBox->calculateXOffset($this->boundingBox, $this->speedX);
									$this->speedZ = $b->boundingBox->calculateZOffset($this->boundingBox, $this->speedZ);
								}
							}
						}
					}
					
					$this->inWater = $water;
				}
				
				$support = $savedSpeedY != $this->speedY && $savedSpeedY < 0;
				
				if($this->speedX != 0){
					$this->x += $this->speedX;
					$update = true;
				}
				if($this->speedZ != 0){
					$this->z += $this->speedZ;
					$update = true;
				}
				if($this->speedY != 0){
					$ny = $this->y + $this->speedY;
					if($this->class === ENTITY_FALLING){
						if($support){
							$this->level->fastSetBlockUpdate($this->x, $this->y, $this->z, $this->data["Tile"], 0);
							$this->close();
							return;
						}
						$id = $this->level->level->getBlockID($this->x, $this->y, $this->z);
						if($id > 0 && !StaticBlock::getIsSolid($id) && !StaticBlock::getIsLiquid($id)){
							$this->server->api->entity->drop($this, BlockAPI::getItem($this->data["Tile"], 0, 1));
							$this->close();
							return;
						}
						
					}
					$this->y = $ny;
					
					$update = true;
				}
				$this->onGround = $support;
				
				if($this->hasGravity){
					$this->speedY -= $this->inWater ? 0.02 : $this->gravity; // TODO: fix packet spam
					$update = true;
				} elseif($this->lastX != $this->x || $this->lastZ != $this->z || $this->lastY != $this->z){
					$this->server->api->handle("entity.move", $this);
					$update = true;
				}elseif ($this->lastYaw != $this->yaw || $this->lastPitch != $this->pitch || $this->lastHeadYaw != $this->headYaw) {
					$update = true;
				}

				if($update === true){
					$hasUpdate = true;
					if(($this->server->ticks % 4 === 0 && $this->class === ENTITY_ITEM) || $this->class != ENTITY_ITEM){ //update item speed every 4 ticks
						$this->server->api->handle("entity.motion", $this);
						$this->lastSpeedZ = $this->speedZ;
						$this->lastSpeedY = $this->speedY;
						$this->lastSpeedX = $this->speedX;
					}
				}
			} elseif($this->player instanceof Player){
				if($isFlying === true and ($this->player->gamemode & 0x01) === 0x00){
					if($this->fallY === false or $this->fallStart === false){
						$this->fallY = $y;
						$this->fallStart = microtime(true);
					} elseif($y > $this->fallY){
						$this->fallY = $y;
					}
				} elseif($this->fallY !== false){ // Fall damage!
					if($y < $this->fallY){
						$blockToFallOn = $this->level->getBlock(new Vector3($x, $y, $z));
						if($blockToFallOn->getID() === FARMLAND){
							$this->level->setBlock($blockToFallOn, new DirtBlock(), true, false, true);
						}

						$d = $this->level->getBlock(new Vector3($x, $y + 1, $z));
						$d2 = $this->level->getBlock(new Vector3($x, $y + 2, $z));
						$dmg = ($this->fallY - $y) - 3;
						if($dmg > 0 and ! ($d instanceof LiquidBlock) and $d->getID() !== LADDER and $d->getID() !== COBWEB and ! ($d2 instanceof LiquidBlock) and $d2->getID() !== LADDER and $d2->getID() !== COBWEB){
							$this->harm($dmg, "fall");
						}
					}
					$this->fallY = false;
					$this->fallStart = false;
				}
				$this->calculateVelocity();
				if($this->speed <= 9 or ($this->speed <= 20 and ($this->player->gamemode & 0x01) === 0x01)){
					$this->player->lastCorrect = new Vector3($this->last[0], $this->last[1], $this->last[2]);
				}
				
				$hasUpdate = true;
			}
		}
		
		$this->counterUpdate();
		
		if($this->lastHeadYaw != $this->headYaw){
			$this->sendHeadYaw();
		}
		if($this->class === ENTITY_PLAYER || $update){
			$this->updateMovement();
		}
		
		if($this->isPlayer()){
			$this->player->entityTick();
		}
		
		$this->needsUpdate = $hasUpdate;
		$this->lastUpdate = $now;
	}
	
	public function counterUpdate(){
		if($this->knockbackTime > 0){
			--$this->knockbackTime;
		}
		if($this->moveTime > 0){
			-- $this->moveTime;
		}
		if($this->lookTime > 0){
			-- $this->lookTime;
		}
		if($this->idleTime > 0){
			-- $this->idleTime;
		}
		if($this->delayBeforePickup > 0){
			--$this->delayBeforePickup;
		}
	}
	
	public function updateMovement()
	{
		if($this->closed === true){
			return false;
		}
		$now = microtime(true);
		if($this->isStatic === false and ($this->lastX != $this->x or $this->lastY != $this->y or $this->lastZ != $this->z or $this->lastYaw != $this->yaw or $this->lastPitch != $this->pitch or $this->lastHeadYaw != $this->headYaw)){
			if($this->class === ENTITY_PLAYER or ($this->last[5] + 8) < $now){
				if($this->server->api->handle("entity.move", $this) === false){
					if($this->class === ENTITY_PLAYER){
						$this->player->teleport(new Vector3($this->last[0], $this->last[1], $this->last[2]), $this->last[3], $this->last[4]);
					} else{
						//TODO fix $this->setPosition($this->last[0], $this->last[1], $this->last[2], $this->last[3], $this->last[4]);
					}
				} else{
					$this->updateLast();
					$players = $this->server->api->player->getAll($this->level);
					if($this->player instanceof Player){
						//console("gujnbguib");
						unset($players[$this->player->CID]);
						$pk = new MovePlayerPacket();
						$pk->eid = $this->eid;
						$pk->x = $this->x;
						$pk->y = $this->y;
						$pk->z = $this->z;
						$pk->yaw = $this->yaw;
						$pk->pitch = $this->pitch;
						$pk->bodyYaw = $this->yaw;
						$this->server->api->player->broadcastPacket($players, $pk);
					} else{
						
						$pk = new MoveEntityPacket;
						$pk->entities = [[$this->eid, $this->x, $this->y, $this->z, $this->yaw, $this->pitch]];
						$this->server->api->player->broadcastPacket($players, $pk);
					}
				}
			} else{
				$this->updatePosition();
				$this->updateLast();
			}
			
			
		}

		$this->lastUpdate = $now;
	}
	
	/**
	 * Handle fall out of world
	 */
	public function outOfWorld(){
		if($this->isPlayer()){
			$this->health = 0;
			$this->makeDead("void");
		}else{
			$this->close();
		}
	}
	
	public function getEyeHeight(){
		return $this->isPlayer() ? 1.62 : $this->width;
	}
	
	public function interactWith(Entity $e, $action)
	{
		if($this->class === ENTITY_PLAYER and ($this->server->api->getProperty("pvp") == false or $this->server->difficulty <= 0 or ($e->player->gamemode & 0x01) === 0x01)){
			return false;
		}

		if($action === InteractPacket::ACTION_ATTACK && $e->isPlayer()){
			$slot = $e->player->getHeldItem();
			$damage = $slot->getDamageAgainstOf($e);
			$this->harm($damage, $e->eid);
			if($slot->isTool() === true and ($e->player->gamemode & 0x01) === 0){
				if($slot->useOn($e) and $slot->getMetadata() >= $slot->getMaxDurability()){
					$e->player->removeItem($slot->getID(), $slot->getMetadata(), 1, true);
				}
			}
			return true;
		}
		return false;
	}

	public function getDirection()
	{
		$rotation = ((int)($this->yaw - 90)) % 360;
		if($rotation < 0){
			$rotation += 360.0;
		}
		if((0 <= $rotation and $rotation < 45) or (315 <= $rotation and $rotation < 360)){
			return 2; //x-
		} elseif(45 <= $rotation and $rotation < 135){
			return 3; //z-
		} elseif(135 <= $rotation and $rotation < 225){
			return 0; //x+
		} elseif(225 <= $rotation and $rotation < 315){
			return 1; //z+
		} else{
			return null;
		}
	}

	/**
	 * METADATA VALUES(EXPEREMENTAL)
	 * *****************
	 * Types: Get input type of <value>
	 * 0 -> Byte
	 * 1 -> Short
	 * 2 -> Integer
	 * 3 -> Float
	 * 4 -> Length of <value>, Short
	 * 5 -> [Short, Byte, Short]
	 * 6 -> [Integer, Integer, Integer]
	 * *****************
	 * 0 => ["type" => 0, "value" => $flags] --> DATA_FLAGS
	 * 1 => ["type" => 1, "value" => $this->air] --> Entity Air
	 * 14 => ["type" => 0, "value" => 1] --> IsBaby, value: 0 => false, 1 => true
	 * 16 => ["type" => 0, "value" => 0] --> Fuse(TNT), Saddled(Pig), Creeper(29 ticks before explosion)
	 * 17 => ["type" => 6, "value" => [0, 0, 0]] --> Bed Position <?>
	 *
	 *
	 * DATA FLAGS IDS<br>
	 * 0 - fire<br>
	 * 1 - crouching<br>
	 * 4 - inAction(ex.: using a bow)<br>
	 */
	public function getMetadata()
	{
		$flags = 0;
		$flags ^= $this->fire > 0 ? 0b1 : 0;
		$flags ^= ($this->crouched ? 0b1 : 0) << 1;
		$flags ^= ($this->inAction ? 0b1 : 0) << 4;
		$d = [
			0 => [
				"type" => 0,
				"value" => $flags
			],
			1 => [
				"type" => 1,
				"value" => $this->air
			],
			14 => [
				"type" => 0,
				"value" => 0
			],
			16 => [
				"type" => 0,
				"value" => 0
			],
			17 => [
				"type" => 6,
				"value" => [
					0,
					0,
					0
				]
			]
		];
		$d[16]["value"] = $this->data["State"];
		if($this->class === ENTITY_PLAYER){
			if($this->player->isSleeping !== false){
				$d[16]["value"] = 2;
				$d[17]["value"] = [
					$this->player->isSleeping->x,
					$this->player->isSleeping->y,
					$this->player->isSleeping->z
				];
			}
		}
		return $d;
	}

	public function updateMetadata()
	{
		$this->server->api->dhandle("entity.metadata", $this);
	}

	public function spawn($player)
	{
		if(! ($player instanceof Player)){
			$player = $this->server->api->player->get($player);
		}
		if($player->eid === $this->eid or $this->closed !== false or ($player->level !== $this->level and $this->class !== ENTITY_PLAYER)){
			return false;
		}
		switch($this->class) {
			case ENTITY_PLAYER:
				if($this->player->connected !== true or $this->player->spawned === false){
					return false;
				}
				if($this->player->gamemode === SPECTATOR){
					break;
				}
				$pk = new AddPlayerPacket();
				$pk->clientID = 0; // $this->player->clientID;
				$pk->username = $this->player->username;
				$pk->eid = $this->eid;
				$pk->x = $this->x;
				$pk->y = $this->y;
				$pk->z = $this->z;
				$pk->yaw = 0;
				$pk->pitch = 0;
				$pk->itemID = 0;
				$pk->itemAuxValue = 0;
				$pk->metadata = $this->getMetadata();
				$player->dataPacket($pk);

				$pk = new SetEntityMotionPacket();
				$pk->entities = [[$this->eid, $this->speedX, $this->speedY, $this->speedZ]];
				$player->dataPacket($pk);

				$pk = new PlayerEquipmentPacket();
				$pk->eid = $this->eid;
				$pk->item = $this->player->getSlot($this->player->slot)->getID();
				$pk->meta = $this->player->getSlot($this->player->slot)->getMetadata();
				$pk->slot = 0;
				$player->dataPacket($pk);
				$this->player->sendArmor($player);
				break;
			case ENTITY_ITEM:
				$pk = new AddItemEntityPacket();
				$pk->eid = $this->eid;
				$pk->x = $this->x;
				$pk->y = $this->y;
				$pk->z = $this->z;
				$pk->yaw = $this->yaw;
				$pk->pitch = $this->pitch;
				$pk->roll = 0;
				$pk->item = BlockAPI::getItem($this->type, $this->meta, $this->stack);
				$pk->metadata = $this->getMetadata();
				$player->dataPacket($pk);

				$pk = new SetEntityMotionPacket();
				$pk->entities = [[$this->eid, $this->speedX, $this->speedY, $this->speedZ]];
				$player->dataPacket($pk);
				break;
			case ENTITY_FALLING:
				$pk = new AddEntityPacket(); //TODO fix
				$pk->eid = $this->eid;
				$pk->type = $this->type;
				$pk->x = $this->x;
				$pk->y = $this->y;
				$pk->z = $this->z;
				$pk->did = -($this->data["Tile"] | $this->data["Metadata"] << 0x10);
				$player->dataPacket($pk);

				$pk = new SetEntityMotionPacket();
				$pk->entities = [[$this->eid, $this->speedX, $this->speedY, $this->speedZ]];
				$player->dataPacket($pk);
				break;
		}
	}

	public function close()
	{
		//if($this->closed === false){
			$this->closed = true;
			//TODO fix:
			//if($this?->linkedEntity?->linkedEntity?->eid === $this->eid){
			//	$this->linkedEntity->linkedEntity = false;
			//}
			$this->linkedEntity = false;
			$this->server->api->entity->remove($this->eid);
		//}
	}

	public function __destruct()
	{
		$this->close();
	}

	public function getEID()
	{
		return $this->eid;
	}

	public function getName()
	{
		return $this->name;
	}

	public function setName($name)
	{
		$this->name = $name;
		// $this->server->query("UPDATE entities SET name = '".str_replace("'", "", $this->name)."' WHERE EID = ".$this->eid.";"); is this neccessary for database?
	}

	public function look($pos2)
	{
		$pos = $this->getPosition();
		$angle = Utils::angle3D($pos2, $pos);
		$this->yaw = $angle["yaw"];
		$this->pitch = $angle["pitch"];
		$this->server->query("UPDATE entities SET pitch = " . $this->pitch . ", yaw = " . $this->yaw . " WHERE EID = " . $this->eid . ";");
	}

	public function move(Vector3 $pos, $yaw = 0, $pitch = 0)
	{
		$this->x += $pos->x;
		$this->y += $pos->y;
		$this->z += $pos->z;
		$this->yaw += $yaw;
		$this->yaw %= 360;
		$this->pitch += $pitch;
		$this->pitch %= 90;
		$this->server->query("UPDATE entities SET x = " . $this->x . ", y = " . $this->y . ", z = " . $this->z . ", pitch = " . $this->pitch . ", yaw = " . $this->yaw . " WHERE EID = " . $this->eid . ";");
		$this->updateAABB();
	}

	public function updateAABB()
	{
		$this->boundingBox->setBounds($this->x - $this->radius, $this->y, $this->z - $this->radius, $this->x + $this->radius, $this->y + $this->height, $this->z + $this->radius);
	}

	public function updatePosition()
	{
		$this->server->query("UPDATE entities SET level = '" . $this->level->getName() . "', x = " . $this->x . ", y = " . $this->y . ", z = " . $this->z . ", pitch = " . $this->pitch . ", yaw = " . $this->yaw . " WHERE EID = " . $this->eid . ";");
		$this->sendMoveUpdate();
		// $this->sendMotion();
		$this->updateAABB();
	}
	
	public function sendHeadYaw(){
		$pk = new RotateHeadPacket;
		$pk->entities = [[$this->eid, $this->headYaw]];
		$this->server->api->player->broadcastPacket($this->level->players, $pk);
	}
	
	public function setPosition(Vector3 $pos, $yaw = false, $pitch = false)
	{
		if($pos instanceof Position and $pos->level instanceof Level and $this->level !== $pos->level){
			$this->level = $pos->level;
			$this->server->preparedSQL->entity->setLevel->reset();
			$this->server->preparedSQL->entity->setLevel->clear();
			$this->server->preparedSQL->entity->setLevel->bindValue(":level", $this->level->getName(), SQLITE3_TEXT);
			$this->server->preparedSQL->entity->setLevel->bindValue(":eid", $this->eid, SQLITE3_INTEGER);
			$this->server->preparedSQL->entity->setLevel->execute();
		}
		$this->x = $pos->x;
		$this->y = $pos->y;
		$this->z = $pos->z;
		if($yaw !== false){
			$this->yaw = $yaw;
		}
		if($pitch !== false){
			$this->pitch = $pitch;
		}
		$this->server->preparedSQL->entity->setPosition->reset();
		$this->server->preparedSQL->entity->setPosition->clear();
		$this->server->preparedSQL->entity->setPosition->bindValue(":x", $this->x, SQLITE3_TEXT);
		$this->server->preparedSQL->entity->setPosition->bindValue(":y", $this->y, SQLITE3_TEXT);
		$this->server->preparedSQL->entity->setPosition->bindValue(":z", $this->z, SQLITE3_TEXT);
		$this->server->preparedSQL->entity->setPosition->bindValue(":pitch", $this->pitch, SQLITE3_TEXT);
		$this->server->preparedSQL->entity->setPosition->bindValue(":yaw", $this->yaw, SQLITE3_TEXT);
		$this->server->preparedSQL->entity->setPosition->bindValue(":eid", $this->eid, SQLITE3_INTEGER);
		$this->server->preparedSQL->entity->setPosition->execute();
	}

	public function inBlock(Vector3 $block, $radius = 0.8)
	{
		$me = new Vector3($this->x - 0.5, $this->y, $this->z - 0.5);
		if(($block->y == (ceil($this->y)) or $block->y == (ceil($this->y) + 1)) and $block->maxPlainDistance($me) < $radius){
			return true;
		}
		return false;
	}

	public function touchingBlock(Vector3 $block, $radius = 0.9)
	{
		$me = new Vector3($this->x - 0.5, $this->y, $this->z - 0.5);
		if(($block->y == (((int) $this->y) - 1) or $block->y == ((int) $this->y) or $block->y == (((int) $this->y) + 1)) and $block->maxPlainDistance($me) < $radius){
			return true;
		}
		return false;
	}

	public function isSupport(Vector3 $pos, $radius = 1)
	{
		$me = new Vector2($this->x - 0.5, $this->z - 0.5);
		$diff = $this->y - $pos->y;
		if($me->distance(new Vector2($pos->x, $pos->z)) < $radius and $diff > - 0.7 and $diff < 1.6){
			return true;
		}
		return false;
	}

	public function resetSpeed()
	{
		$this->speedMeasure = array(
			0,
			0,
			0,
			0,
			0,
			0,
			0
		);
	}

	public function getSpeed()
	{
		return $this->speed;
	}

	public function getSpeedMeasure()
	{
		return array_sum($this->speedMeasure) / count($this->speedMeasure);
	}

	public function calculateVelocity()
	{
		$diffTime = max(0.05, abs(microtime(true) - $this->last[5]));
		$origin = new Vector2($this->last[0], $this->last[2]);
		$final = new Vector2($this->x, $this->z);
		$speedX = ($this->last[0] - $this->x) / $diffTime;
		$speedY = ($this->last[1] - $this->y) / $diffTime;
		$speedZ = ($this->last[2] - $this->z) / $diffTime;
		if($this->speedX != $speedX or $this->speedY != $speedY or $this->speedZ != $speedZ){
			$this->speedX = $speedX;
			$this->speedY = $speedY;
			$this->speedZ = $speedZ;
			$this->server->api->handle("entity.motion", $this);
		}
		$this->speed = $origin->distance($final) / $diffTime;
		unset($this->speedMeasure[key($this->speedMeasure)]);
		$this->speedMeasure[] = $this->speed;
	}
	/**
	 * @return array
	 */
	public function createSaveData(){
		$data = [
			"id" => $this->type,
			"Health" => $this->health,
			"Pos" => [
				0 => $this->x,
				1 => $this->y,
				2 => $this->z,
			],
			"Rotation" => [
				0 => $this->yaw,
				1 => $this->pitch,
			],
			
		];
		if($this->class === ENTITY_OBJECT){
			$data["TileX"] = $this->x;
			$data["TileY"] = $this->y;
			$data["TileZ"] = $this->z;
		}
		if($this->class === ENTITY_FALLING){
			$data["Tile"] = $this->data["Tile"];
			$data["Metadata"] = $this->data["Metadata"];
		}
		if($this->class === ENTITY_ITEM){
			$data["Item"] = [
				"id" => $this->type,
				"Damage" => $this->meta,
				"Count" => $this->stack,
			];
		}
		return $data;
	}
 
	public function updateLast()
	{
		$this->last[0] = $this->x;
		$this->last[1] = $this->y;
		$this->last[2] = $this->z;
		$this->last[3] = $this->yaw;
		$this->last[4] = $this->pitch;
		$this->last[5] = microtime(true);
		$this->lastHeadYaw = $this->headYaw;
	}

	public function getPosition($round = false)
	{
		return ! isset($this->position) ? false : ($round === true ? array_map("floor", $this->position) : $this->position);
	}

	public function harm($dmg, $cause = "generic", $force = false)
	{
		if (! $this->canBeAttacked) {
			return false;
		}
		$dmg = $this->applyArmor($dmg, $cause); //TODO HURTCAM
		$ret = $this->setHealth(max(- 128, $this->getHealth() - ((int) $dmg)), $cause, $force);

		if ($ret != false && $this->hasKnockback && is_numeric($cause) && ($entity = $this->server->api->entity->get($cause)) != false) {
			$d = $entity->x - $this->x;

			for ($d1 = $entity->z - $this->z; $d * $d + $d1 * $d1 < 0.0001; $d1 = (lcg_value() - lcg_value()) * 0.01) {
				$d = (lcg_value() - lcg_value()) * 0.01;
			}
			if($this->isPlayer()){
				//force reset speed
				$this->speedX = $this->speedY = $this->speedZ = 0;
			}
			// attackedAtYaw = (float)((Math.atan2($d1, $d) * 180D) / 3.1415927410125732D) >
			$this->knockBack($d, $d1);
			if($this->isPlayer()){
				$pk = new SetEntityMotionPacket();
				$pk->entities = [[0, $this->speedX, $this->speedY, $this->speedZ]];
				$this->player->directDataPacket($pk, 0);
			}
			$this->knockbackTime = 10;
			$this->sendMotion();
			
		}

		return $ret;
	}

	public function setState($v)
	{
		$this->state = $v;
		$this->data["State"] = $v;
		$this->updateMetadata();
	}

	public function getState()
	{
		return $this->state;
	}

	public function heal($health, $cause = "generic")
	{
		return $this->setHealth(min(20, $this->getHealth() + ((int) $health)), $cause);
	}

	public function sendMotion()
	{
		$pk = new SetEntityMotionPacket();
		$pk->entities = [[$this->eid, $this->speedX, $this->speedY, $this->speedZ]];
		$this->server->api->player->broadcastPacket($this->level->players, $pk);
	}

	public function linkEntity(Entity $e, $type)
	{
		//if($e->isPlayer()){
		$this->server->api->dhandle("entity.link", ["rider" => $e->eid, "riding" => $this->eid, "type" => 0]);
		//}
	}

	public function isPlayer()
	{
		return isset($this->player) && $this->player instanceof Player;
	}

	public function sendMoveUpdate()
	{
		if($this->class === ENTITY_PLAYER){
			$this->player->teleport(new Vector3($this->x, $this->y, $this->z));
			return;
		}
		$pk = new MoveEntityPacket();
		$pk->entities = [[$this->eid, $this->x, $this->y, $this->z, $this->yaw, $this->pitch]];
		$this->server->api->player->broadcastPacket($this->level->players, $pk);
	}

	public function moveEntityWithOffset($oX, $oY, $oZ)
	{
		$oX = $oX === 0 ? $this->speedX : ($this->getSpeedModifer() * $oX * $this->getSpeed());
		$oY = $oY <= 0 ? $this->speedY : (0.42);
		$oZ = $oZ === 0 ? $this->speedZ : ($this->getSpeedModifer() * $oZ * $this->getSpeed());
		$this->setVelocity($oX, $oY, $oZ);
	}

	public function getSpeedModifer()
	{
		return $this->speedModifer;
	}
	public function getArmorValue(){
		$pnts = 0;
		if($this->isPlayer()){
			foreach($this->player->armor as $slot => $part){
				if($part instanceof ArmorItem){
					$pnts += $part->getDamageReduceAmount();
					$this->player->damageArmorPart($slot, $part);
				}
			}
			$this->player->sendArmor($this->player);
		}
		return $pnts;
	}
	public function applyArmor($damage, $cause){
		if(is_numeric($cause) || $cause === "explosion"){
			$var3 = 25 - $this->getArmorValue();
			$var4 = $damage * $var3 + $this->carryoverDamage;
			$damage = $var4 / 25;
			$this->carryoverDamage = $var4 % 25;
		}
		return $damage;
	}
	
	public function setHealth($health, $cause = "generic", $force = false)
	{
		$health = (int) $health;
		$harm = false;
		if($health < $this->health){
			$harm = true;
			$dmg = $this->health - $health;
			if(($this->class !== ENTITY_PLAYER or (($this->player instanceof Player) and (($this->player->gamemode & 0x01) === 0x00 or $force === true))) and ($this->dmgcounter[0] < microtime(true) or $this->dmgcounter[1] < $dmg) and ! $this->dead){
				$this->dmgcounter[0] = microtime(true) + 0.5;
				$this->dmgcounter[1] = $dmg;
			} else{
				return false; // Entity inmunity
			}
		} elseif($health === $this->health and ! $this->dead){
			return false;
		}
		if($this->server->api->dhandle("entity.health.change", array(
			"entity" => $this,
			"eid" => $this->eid,
			"health" => $health,
			"cause" => $cause
		)) !== false or $force === true){
			$this->health = min(127, max(- 127, $health));
			$this->server->query("UPDATE entities SET health = " . $this->health . " WHERE EID = " . $this->eid . ";");
			if($harm === true){
				$pk = new EntityEventPacket;
				$pk->eid = $this->eid;
				$pk->event = EntityEventPacket::ENTITY_DAMAGE;
				$this->server->api->player->broadcastPacket($this->level->players, $pk);
			}
			if($this->player instanceof Player){
				$pk = new SetHealthPacket();
				$pk->health = $this->health;
				$this->player->dataPacket($pk);
			}
			if($this->health <= 0 and $this->dead === false){
				$this->makeDead($cause);
			} elseif($this->health > 0){
				$this->dead = false;
			}
			return true;
		}
		return false;
	}

	public function setSize($w, $h)
	{
		$this->width = $w;
		$this->height = $h;
		$this->radius = $w / 2;
	}
	
	public function makeDead($cause){
		if($this->server->api->dhandle("entity.death", ["entity" => $this, "cause" => $cause]) === false) return false;
		$this->spawnDrops();
		$this->air = 200;
		$this->fire = 0;
		$this->crouched = false;
		$this->fallY = false;
		$this->fallStart = false;
		$this->updateMetadata();
		$this->dead = true;
		if($this->player instanceof Player){
			$pk = new MoveEntityPacket_PosRot();
			$pk->eid = $this->eid;
			$pk->x = - 256;
			$pk->y = 128;
			$pk->z = - 256;
			$pk->yaw = 0;
			$pk->pitch = 0;
			$this->server->api->player->broadcastPacket($this->level->players, $pk);
		}else{
			$pk = new EntityEventPacket;
			$pk->eid = $this->eid;
			$pk->event = EntityEventPacket::ENTITY_DEAD;
			$this->server->api->player->broadcastPacket($this->level->players, $pk);
		}
		if($this->player instanceof Player){
			$this->player->blocked = true;
			$this->server->api->dhandle("player.death", [
				"player" => $this->player,
				"cause" => $cause
			]);
			if($this->server->api->getProperty("hardcore") == 1){ //poor player =<
				$this->server->api->ban->ban($this->player->username);
			}
		} else{
			$this->server->api->schedule(40, [$this, "close"], []);
		}
	}
	
	public function getAttackDamage(){
		return 0;
	}
	
	public function setSpeed($s)
	{
		$this->speed = $s;
	}

	public function knockBack($d, $d1)
	{
		$f = sqrt($d * $d + $d1 * $d1);
		$f1 = 0.4;
		$this->speedX /= 2;
		$this->speedY /= 2;
		$this->speedZ /= 2;
		$this->speedX -= ($d / $f) * $f1;
		$this->speedY += 0.4;
		$this->speedZ -= ($d1 / $f) * $f1;
		if($this->speedY > 0.40000000596046448){
			$this->speedY = 0.40000000596046448;
		}
	}

	public function getHealth()
	{
		return $this->health;
	}

	public function __toString()
	{
		return "Entity(x={$this->x},y={$this->y},z={$this->z},level=" . $this->level->getName() . ",class={$this->class},type={$this->type})";
	}
	
	/**
	 * Debug
	 */
	public function printSpeed($add = ""){
		ConsoleAPI::debug("$add {$this->speedX}:{$this->speedY}:{$this->speedZ}");
	}
	
	/*
	 * Deprecated methods.
	 * Those methods were left only for compability with older plugins
	 */
	/**
	 *
	 * @deprecated Use {@link getHeightOf} or {@link getWidthOf} instead
	 * @throws Exception
	 */
	public static function getSizeOf($e)
	{
		throw new Exception("Use getHeightOf or getWidthOf method instead of this.");
	}
	
	
	/**
	 *
	 * @deprecated Use {@link getHeight} or {@link getWidth} instead
	 * @throws Exception
	 */
	public function getSize()
	{
		throw new Exception("Use getHeight or getWidth method instead of this.");
	}
}
