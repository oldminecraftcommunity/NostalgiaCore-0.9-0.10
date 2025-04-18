<?php


class Player{

	public $data;
	/** @var Entity */
	public $entity = false;
	public $is0105Client = false;
	private $reload = true;
	public $auth = false;
	public $CID;
	public $MTU;
	public $spawned = false;
	public $inventory;
	public $slot;
	public $hotbar;
	public $armor = [];
	public $loggedIn = false;
	public $gamemode;
	public $lastBreak;
	public $windowCnt = 2;
	public $windows = [];
	public $blocked = true;
	public $achievements = [];
	public $chunksLoaded = [];
	public $lastCorrect;
	public $craftingItems = [];
	public $toCraft = [];
	public $lastCraft = 0;
	public $loginData = [];
	/** @var Level */
	public $level;
	private $server;
	private $recoveryQueue = [];
	private $receiveQueue = [];
	private $resendQueue = [];
	private $ackQueue = [];
	private $receiveCount = -1;
	private $buffer;
	private $bufferLen = 0;
	private $nextBuffer = 0;
	private $evid = [];
	private $lastMovement = 0;
	private $forceMovement = false;
	private $timeout;
	private $connected = true;
	private $clientID;
	private $ip;
	private $port;
	private $counter = [0, 0, 0, 0];
	public $username;
	public $iusername;
	private $eid = false;
	private $startAction = false;
	public $isSleeping = false;
	private $chunksOrder = [];
	private $lastMeasure = 0;
	private $bandwidthRaw = 0;
	private $bandwidthStats = [0, 0, 0];
	private $lag = [];
	private $lagStat = 0;
	private $spawnPosition;
	private $packetLoss = 0;
	private $bigCnt;
	private $packetStats;
	private $chunkCount = [];
	private $received = [];
	public $cratingItems;
	public $sleepingTime = 0;
	
	public $prevChunkX = false, $prevChunkZ = false;
	
	public $blockSendQueue;
	public $blockSendQueueLength = 0;
	/**
	 * @param integer $clientID
	 * @param string $ip
	 * @param integer $port
	 * @param integer $MTU
	 */
	public function __construct($clientID, $ip, $port, $MTU){
		$this->bigCnt = 0;
		$this->MTU = $MTU;
		$this->server = ServerAPI::request();
		$this->lastBreak = microtime(true);
		$this->clientID = $clientID;
		$this->CID = PocketMinecraftServer::clientID($ip, $port);
		$this->ip = $ip;
		$this->port = $port;
		$this->spawnPosition = $this->server->spawn;
		$this->timeout = microtime(true) + 20;
		$this->inventory = [];
		$this->armor = [];
		$this->gamemode = $this->server->gamemode;
		$this->level = $this->server->api->level->getDefault();
		$this->slot = 0;
		$this->hotbar = [0, -1, -1, -1, -1, -1, -1, -1, -1];
		$this->packetStats = [0, 0];
		$this->buffer = new RakNetPacket(RakNetInfo::DATA_PACKET_0);
		$this->buffer->data = [];
		$this->blockSendQueue = new RakNetPacket(RakNetInfo::DATA_PACKET_0);
		$this->blockSendQueue->data = [];
		$this->server->schedule(1, [$this, "handlePacketQueues"], [], true);
		$this->server->schedule(20 * 60, [$this, "clearQueue"], [], true);
		$this->evid[] = $this->server->event("server.close", [$this, "close"]);
		console("[DEBUG] New Session started with " . $ip . ":" . $port . ". MTU " . $this->MTU . ", Client ID " . $this->clientID, true, true, 2);
	}

	public function __get($name){
		if(isset($this->{$name})){
			return ($this->{$name});
		}
		return null;
	}

	public function getSpawn(){
		return $this->spawnPosition;
	}

	/**
	 * @param Vector3 $pos
	 *
	 * @return boolean
	 */
	public function sleepOn(Vector3 $pos){
		foreach($this->server->api->player->getAll($this->level) as $p){
			if($p->isSleeping instanceof Vector3){
				if($pos->distance($p->isSleeping) <= 0.1){
					return false;
				}
			}
		}
		$this->isSleeping = $pos;
		$this->teleport(new Position($pos->x + 0.5, $pos->y + 1, $pos->z + 0.5, $this->level), false, false, false, false);
		if($this->entity instanceof Entity){
			$this->entity->updateMetadata();
		}
		$this->setSpawn($pos);
	
		return true;
	}

	/**
	 * @param Vector3 $pos
	 * @param float|boolean $yaw
	 * @param float|boolean $pitch
	 * @param float|boolean $terrain
	 * @param float|boolean $force
	 *
	 * @return boolean
	 */
	public function teleport(Vector3 $pos, $yaw = false, $pitch = false, $terrain = true, $force = true){
		if($this->entity instanceof Entity and $this->level instanceof Level){
			$this->entity->check = false;
			if($yaw === false){
				$yaw = $this->entity->yaw;
			}
			if($pitch === false){
				$pitch = $this->entity->pitch;
			}
			if($this->server->api->dhandle("player.teleport", ["player" => $this, "target" => $pos]) === false){
				$this->entity->check = true;
				return false;
			}
			
			if($pos instanceof Position and $pos->level instanceof Level and $pos->level !== $this->level){
				if($this->server->api->dhandle("player.teleport.level", ["player" => $this, "origin" => $this->level, "target" => $pos->level]) === false){
					$this->entity->check = true;
					return false;
				}

				foreach($this->level->entityList as $e){
					if($e !== $this->entity){
						if($e->isPlayer()){
							$pk = new MoveEntityPacket;
							$pk->entities = [[$this->entity->eid, -256, 128, -256, 0, 0]];
							$e->player->dataPacket($pk);
							
							$pk = new MoveEntityPacket;
							$pk->entities = [[$e->eid, -256, 128, -256, 0, 0]];
							$this->dataPacket($pk);
						}else{
							$pk = new RemoveEntityPacket;
							$pk->eid = $e->eid;
							$this->dataPacket($pk);
						}
					}
				}
				$this->level->freeAllChunks($this);
				$this->level = $pos->level;
				$this->chunksLoaded = [];
				$this->server->api->entity->spawnToAll($this->entity);
				$this->server->api->entity->spawnAll($this);

				$pk = new SetTimePacket;
				$pk->time = $this->level->getTime();
				$this->dataPacket($pk);
				$terrain = true;
				foreach($this->level->players as $player){
					if($player !== $this and $player->entity instanceof Entity){
						$pk = new MovePlayerPacket();
						$pk->entities = [[$player->entity->eid, $player->entity->x, $player->entity->y, $player->entity->z, $player->entity->yaw, $player->entity->pitch]];
						$this->dataPacket($pk);

						$pk = new PlayerEquipmentPacket;
						$pk->eid = $this->eid;
						$pk->item = $this->getSlot($this->slot)->getID();
						$pk->meta = $this->getSlot($this->slot)->getMetadata();
						$pk->slot = 0;
						$player->dataPacket($pk);
						$this->sendArmor($player);

						$pk = new PlayerEquipmentPacket;
						$pk->eid = $player->eid;
						$pk->item = $player->getSlot($player->slot)->getID();
						$pk->meta = $player->getSlot($player->slot)->getMetadata();
						$pk->slot = 0;
						$this->dataPacket($pk);
						$player->sendArmor($this);
					}
				}
				$resyncpos = new Position($pos->x, $pos->y, $pos->z, $pos->level);
			}else{
				$resyncpos = new Vector3($pos->x, $pos->y, $pos->z);
			}

			$this->lastCorrect = $resyncpos;
			$this->entity->fallY = false;
			$this->entity->fallStart = false;
			//if($terrain === true){
				
				//$this->getNextChunk($this->level);
			//}*/
			$this->entity->check = true;
			if($force === true){
				$this->forceMovement = $resyncpos;
			}
		}
		if($this->spawned){
			$this->entity->setPosition($pos, $yaw, $pitch);
			$this->entity->resetSpeed();
			$this->entity->updateLast();
			$this->entity->calculateVelocity();
		}
		
		$this->orderChunks(true);
		$this->getNextChunk($this->level);
		$pk = new MovePlayerPacket;
		$pk->eid = 0;
		$pk->x = $pos->x;
		$pk->y = $pos->y + 1.62;
		$pk->z = $pos->z;
		$pk->bodyYaw = $yaw;
		$pk->pitch = $pitch;
		$pk->yaw = $yaw;
		$pk->teleport = true;
		$this->dataPacket($pk);
		
		$pk = new SetEntityMotionPacket();
		$pk->entities = [[0, 0, 0, 0]];
		$this->dataPacket($pk);
	}
	
	public function addToBlockSendQueue(RakNetDataPacket $packet){
		if($this->connected === false) return false;
		
		$packet->encode();
		$len = strlen($packet->buffer) + 1;
		$MTU = $this->MTU - 24;
		
		
		if(($this->blockSendQueueLength + $len) >= $MTU){
			$this->sendBlockUpdateQueue();
		}
		
		$packet->messageIndex = $this->counter[3]++;
		$packet->reliability = 2;
		@$this->blockSendQueue->data[] = $packet;
		$this->blockSendQueueLength += 6 + $len;
	}
	/**
	 * @param integer $id
	 * @param array $data
	 *
	 * @return array|bool
	 */
	public function dataPacket(RakNetDataPacket $packet){
		if($this->connected === false){
			return false;
		}

		if(EventHandler::callEvent(new DataPacketSendEvent($this, $packet)) === BaseEvent::DENY){
			return;
		}

		$packet->encode();
		$len = strlen($packet->buffer) + 1;
		$MTU = $this->MTU - 24;
		if($len > $MTU){
			return $this->directBigRawPacket($packet);
		}

		if(($this->bufferLen + $len) >= $MTU){
			$this->sendBuffer();
		}

		$packet->messageIndex = $this->counter[3]++;
		$packet->reliability = 2;
		@$this->buffer->data[] = $packet;
		$this->bufferLen += 6 + $len;
		return [];
	}

	private function directBigRawPacket(RakNetDataPacket $packet){
		if($this->connected === false){
			return false;
		}

		$sendtime = microtime(true);

		$size = $this->MTU - 34;
		$buffer = str_split($packet->buffer, $size);
		$bigCnt = $this->bigCnt;
		$this->bigCnt = ($this->bigCnt + 1) % 0x10000;
		$cnts = [];
		$bufCount = count($buffer);
		foreach($buffer as $i => $buf){
			$cnts[] = $count = $this->counter[0]++;

			$pk = new UnknownPacket;
			$pk->packetID = $packet->pid();
			$pk->reliability = 2;
			$pk->hasSplit = true;
			$pk->splitCount = $bufCount;
			$pk->splitID = $bigCnt;
			$pk->splitIndex = $i;
			$pk->buffer = $buf;
			$pk->messageIndex = $this->counter[3]++;

			$rk = new RakNetPacket(RakNetInfo::DATA_PACKET_0);
			$rk->data[] = $pk;
			$rk->seqNumber = $count;
			$rk->sendtime = $sendtime;
			$this->recoveryQueue[$count] = $rk;
			$this->send($rk);
		}
		return $cnts;
	}

	public function send(RakNetPacket $packet){
		if($this->connected === true){
			$packet->ip = $this->ip;
			$packet->port = $this->port;
			$this->bandwidthRaw += $this->server->send($packet);
		}
	}
	
	
	public function sendBlockUpdateQueue(){
		if($this->blockSendQueueLength > 0 and $this->blockSendQueue instanceof RakNetPacket){
			$this->blockSendQueue->seqNumber = $this->counter[0]++;
			$this->send($this->blockSendQueue);
		}
		$this->blockSendQueueLength = 0;
		$this->blockSendQueue = new RakNetPacket(RakNetInfo::DATA_PACKET_0);
		$this->blockSendQueue->data = [];
	}
	public function sendBuffer(){
		if($this->bufferLen > 0 and $this->buffer instanceof RakNetPacket){
			$this->buffer->seqNumber = $this->counter[0]++;
			$this->send($this->buffer);
		}
		$this->bufferLen = 0;
		$this->buffer = new RakNetPacket(RakNetInfo::DATA_PACKET_0);
		$this->buffer->data = [];
		$this->nextBuffer = microtime(true) + 0.05;
	}

	/**
	 * @param integer $slot
	 *
	 * @return Item
	 */
	public function getSlot($slot){
		if(isset($this->inventory[(int) $slot])){
			return $this->inventory[(int) $slot];
		}else{
			return BlockAPI::getItem(AIR, 0, 0);
		}
	}

	/**
	 * @param integer $slot
	 * @param Item $item
	 * @param boolean $send
	 *
	 * @return boolean
	 */
	public function setSlot($slot, Item $item, $send = true){
		$this->inventory[(int) $slot] = $item;
		if($send === true){
			$this->sendInventorySlot((int) $slot);
		}
		return true;
	}

	/**
	 * @param Player|string|boolean|void $player
	 */
	public function sendArmor($player = false){
		$data = [
			"player" => $this,
			"eid" => $this->eid,
			"slots" => []
		];
		for($i = 0; $i < 4; ++$i){
			if(isset($this->armor[$i]) and ($this->armor[$i] instanceof Item) and $this->armor[$i]->getID() > AIR){
				$data["slots"][$i] = $this->armor[$i]->getID() !== AIR ? $this->armor[$i]->getID() - 256 : 0;
			}else{
				$this->armor[$i] = BlockAPI::getItem(AIR, 0, 0);
				$data["slots"][$i] = 255;
			}
		}
		if($player instanceof Player){
			if($player === $this){
				$pk = new ContainerSetContentPacket;
				$pk->windowid = 0x78; //Armor window id
				$pk->slots = $this->armor;
				$this->dataPacket($pk);
			}else{
				$pk = new PlayerArmorEquipmentPacket;
				$pk->eid = $this->eid;
				$pk->slots = $data["slots"];
				$player->dataPacket($pk);
			}
		}else{
			$this->server->api->dhandle("player.armor", $data);
		}
	}
	public function stopUsingChunk($X, $Z){
		$ind = "$X:$Z";
		if(isset($this->chunksLoaded[$ind])) unset($this->chunksLoaded[$ind]);
		if(isset($this->chunksOrder[$ind])) unset($this->chunksOrder[$ind]);
		
		$this->dataPacket(new UnloadChunkPacket($X, $Z));
	}
	public function orderChunks($force = false){
		if(!($this->entity instanceof Entity) or $this->connected === false){
			return false;
		}
		$X = ((int)$this->entity->x) >> 4;
		$Z = ((int)$this->entity->z) >> 4;
		
		if($force === false && $X === $this->prevChunkX && $Z === $this->prevChunkZ){
			return; //doesnt need reorder
		}
		$this->prevChunkX = $X;
		$this->prevChunkZ = $Z;
		$this->chunksOrder = [];
		
		$startX = $this->level->generatorType === 1 ? $X - PocketMinecraftServer::$chunkLoadingRadius : 0;
		$stopX = $this->level->generatorType === 1 ? $X + PocketMinecraftServer::$chunkLoadingRadius : 15;
		$startZ = $this->level->generatorType === 1 ? $Z - PocketMinecraftServer::$chunkLoadingRadius : 0;
		$stopZ = $this->level->generatorType === 1 ? $Z + PocketMinecraftServer::$chunkLoadingRadius : 15;
		for($x = $startX; $x <= $stopX; ++$x){
			for($z = $startZ; $z <= $stopZ; ++$z){
				$d = $x . ":" . $z;
				if(!isset($this->chunksLoaded[$d])){
					$this->chunksOrder[$d] = abs($x - $X) + abs($z - $Z);
				}
			}
		}
		asort($this->chunksOrder);
		
		$this->reload = true;
		
	}
	
	public $chunkTicker = 0;
	public function entityTick(){
		//ConsoleAPI::debug("{$this->username}, cl: ".count($this->chunksLoaded).", oc: ".count($this->chunksOrder));
		if(count($this->chunksOrder) <= 0 && $this->level->generatorType != 0){
			$this->orderChunks();
		}
		//console("biome: ".$this->entity->level->level->getBiomeId((int)$this->entity->x, (int)$this->entity->z));
		if($this->isSleeping) ++$this->sleepingTime;
		if($this->chunkTicker++ > PocketMinecraftServer::$chukSendDelay){
			$this->getNextChunk($this->level);
			$this->chunkTicker = 0;
		}
	}
	
	public function getNextChunk($world){
		if($this->connected === false or $world != $this->level){
			return false;
		}
		
		foreach($this->chunkCount as $count => $t){
			if(isset($this->recoveryQueue[$count]) or isset($this->resendQueue[$count])){
				return;
			}else{
				unset($this->chunkCount[$count]);
			}
		}
		
		$c = key($this->chunksOrder);
		$d = $c != null ? $this->chunksOrder[$c] : null;
		if($c === null or $d === null) return false;
		
		unset($this->chunksOrder[$c]);
		$this->chunksLoaded[$c] = true;
		$id = explode(":", $c);
		$X = $id[0];
		$Z = $id[1];
		$this->level->useChunk($X, $Z, $this);
		$this->chunksLoaded["$X:$Z"] = true;
		
		$pk = new FullChunkDataPacket;
		$pk->chunkX = $X;
		$pk->chunkZ = $Z;
		$pk->data = $this->level->getOrderedFullChunk($X, $Z);
		$cnt = $this->dataPacket($pk);
		$this->chunkCount = [];
		foreach($cnt as $i => $count){
			$this->chunkCount[$count] = true;
		}
	}

	/**
	 * @param Vector3 $pos
	 */
	public function setSpawn(Vector3 $pos){
		if(!($pos instanceof Position)){
			$level = $this->level;
		}else{
			$level = $pos->level;
		}
		$this->spawnPosition = new Position($pos->x, $pos->y, $pos->z, $level);
		$pk = new SetSpawnPositionPacket;
		$pk->x = (int) $this->spawnPosition->x;
		$pk->y = (int) $this->spawnPosition->y;
		$pk->z = (int) $this->spawnPosition->z;
		$this->dataPacket($pk);
	}

	public function sendInventorySlot($s){
		$this->sendInventory();
		return;
		$s = (int) $s;
		if(!isset($this->inventory[$s])){
			$pk = new ContainerSetSlotPacket;
			$pk->windowid = 0;
			$pk->slot = (int) $s;
			$pk->item = BlockAPI::getItem(AIR, 0, 0);
			$this->dataPacket($pk);
		}

		$slot = $this->inventory[$s];
		$pk = new ContainerSetSlotPacket;
		$pk->windowid = 0;
		$pk->slot = (int) $s;
		$pk->item = $slot;
		$this->dataPacket($pk);
		return true;
	}

	public function sendInventory(){
		if(($this->gamemode & 0x01) === CREATIVE){
			return;
		}
		$hotbar = [];
		foreach($this->hotbar as $slot){
			$hotbar[] = $slot <= -1 ? -1 : $slot + 9;
		}

		$pk = new ContainerSetContentPacket;
		$pk->windowid = 0;
		$pk->slots = $this->inventory;
		$pk->hotbar = $hotbar;
		$this->dataPacket($pk);
	}

	/**
	 * @param $type
	 * @param $damage
	 * @param $count
	 *
	 * @return boolean
	 */
	public function hasSpace($type, $damage, $count){
		$inv = $this->inventory;
		while($count > 0){
			$add = 0;
			foreach($inv as $s => $item){
				if($item->getID() === AIR){
					$add = min($item->getMaxStackSize(), $count);
					$inv[$s] = BlockAPI::getItem($type, $damage, $add);
					break;
				}elseif($item->getID() === $type and $item->getMetadata() === $damage){
					$add = min($item->getMaxStackSize() - $item->count, $count);
					if($add <= 0){
						continue;
					}
					$inv[$s] = BlockAPI::getItem($type, $damage, $item->count + $add);
					break;
				}
			}
			if($add <= 0){
				return false;
			}
			$count -= $add;
		}
		return true;
	}

	/**
	 * @param integer $slot
	 *
	 * @return Item
	 */
	public function getArmor($slot){
		if(isset($this->armor[(int) $slot])){
			return $this->armor[(int) $slot];
		}else{
			return BlockAPI::getItem(AIR, 0, 0);
		}
	}

	public function setArmor($slot, Item $armor, $send = true){
		$this->armor[(int) $slot] = $armor;
		if($send === true){
			$this->sendArmor($this);
		}
		return true;
	}

	/**
	 * @param mixed $data
	 * @param string $event
	 */
	public function eventHandler($data, $event){
		switch($event){
			case "entity.link":
				$pk = new SetEntityLinkPacket();
				if($data["rider"] === $this->eid){
					$pk->rider = 0;
				}else{
					$pk->rider = $data["rider"];
				}
				$pk->riding = $data["riding"];
				$pk->type = 0; //TODO;
				$this->dataPacket($pk);
				break;
			case "tile.update":
				if($data->level === $this->level){
					if($data->class === TILE_FURNACE){
						foreach($this->windows as $id => $w){
							if($w === $data){
								$pk = new ContainerSetDataPacket;
								$pk->windowid = $id;
								$pk->property = 0; //Smelting
								$pk->value = floor($data->data["CookTime"]);
								$this->dataPacket($pk);

								$pk = new ContainerSetDataPacket;
								$pk->windowid = $id;
								$pk->property = 1; //Fire icon
								$pk->value = $data->data["BurnTicks"];
								$this->dataPacket($pk);
							}
						}
					}
				}
				break;
			case "tile.container.slot":
				if($data["tile"]->level === $this->level){
					foreach($this->windows as $id => $w){
						if($w === $data["tile"]){
							$pk = new ContainerSetSlotPacket;
							$pk->windowid = $id;
							$pk->slot = $data["slot"] + (isset($data["offset"]) ? $data["offset"] : 0);
							$pk->item = $data["slotdata"];
							$this->dataPacket($pk);
						}
					}
				}
				break;
			case "player.armor":
				if($data["player"]->level === $this->level){
					if($data["eid"] === $this->eid){
						$this->sendArmor($this);
						break;
					}
					$pk = new PlayerArmorEquipmentPacket;
					$pk->eid = $data["eid"];
					$pk->slots = $data["slots"];
					$this->dataPacket($pk);
				}
				break;
			case "player.pickup":
				if($data["eid"] === $this->eid){
					$data["eid"] = 0;
					$pk = new TakeItemEntityPacket;
					$pk->eid = 0;
					$pk->target = $data["entity"]->eid;
					$this->dataPacket($pk);
					if(($this->gamemode & 0x01) === 0x00){
						$this->addItem($data["entity"]->type, $data["entity"]->meta, $data["entity"]->stack, false);
					}
					switch($data["entity"]->type){
						case WOOD:
							AchievementAPI::grantAchievement($this, "mineWood");
							break;
						case DIAMOND:
							AchievementAPI::grantAchievement($this, "diamond");
							break;
						case LEATHER:
							AchievementAPI::grantAchievement($this, "leather");
							break;
					}
				}elseif($data["entity"]->level === $this->level){
					$pk = new TakeItemEntityPacket;
					$pk->eid = $data["eid"];
					$pk->target = $data["entity"]->eid;
					$this->dataPacket($pk);
				}
				break;
			case "player.equipment.change":
				if($data["eid"] === $this->eid or $data["player"]->level !== $this->level){
					break;
				}
				$data["slot"] = 0;

				$pk = new PlayerEquipmentPacket;
				$pk->eid = $data["eid"];
				$pk->item = $data["item"]->getID();
				$pk->meta = $data["item"]->getMetadata();
				$pk->slot = $data["slot"];
				$this->dataPacket($pk);

				break;
			case "entity.motion":
				if($data->eid === $this->eid or $data->level !== $this->level){
					break;
				}
				if(($data->speedX === 0 && $data->speedY === 0 && $data->speedZ === 0) || ($data->speedX === $data->lastSpeedX && $data->speedY === $data->lastSpeedY && $data->lastSpeedZ === $data->speedZ)){ //causer of packet flood is eliminated.
					break;
				}
				$pk = new SetEntityMotionPacket; //TODO one packet for all entities if possible
				$pk->entities = [[$data->eid, $data->speedX, $data->speedY, $data->speedZ]];
				$this->dataPacket($pk);
				break;
			case "entity.animate":

				$pk = new AnimatePacket;
				$pk->eid = $data["eid"];
				$pk->action = $data["action"]; //1 swing arm,
				$this->dataPacket($pk);
				break;
			case "entity.metadata":
				if($data->eid === $this->eid){
					$eid = 0;
				}else{
					$eid = $data->eid;
				}
				if($data->level === $this->level){
					$pk = new SetEntityDataPacket;
					$pk->eid = $eid;
					$pk->metadata = $data->getMetadata();
					$this->dataPacket($pk);
				}
				break;
			case "entity.event":
				if($data["entity"]->eid === $this->eid){
					$eid = 0;
				}else{
					$eid = $data["entity"]->eid;
				}
				if($data["entity"]->level === $this->level){
					$pk = new EntityEventPacket;
					$pk->eid = $eid;
					$pk->event = $data["event"];
					$this->dataPacket($pk);
				}
				break;
			case "server.chat":
				if(($data instanceof Container) === true){
					if(!$data->check($this->username) and !$data->check($this->iusername)){
						return;
					}else{
						$message = $data->get();
						$this->sendChat(preg_replace('/\x1b\[[0-9;]*m/', "", $message["message"]), $message["player"]); //Remove ANSI codes from chat
					}
				}else{
					$message = (string) $data;
					$this->sendChat(preg_replace('/\x1b\[[0-9;]*m/', "", (string) $data)); //Remove ANSI codes from chat
				}
				break;
		}
	}

	/**
	 * @param $type
	 * @param $damage
	 * @param integer $count
	 * @param boolean $send
	 *
	 * @return boolean
	 */
	public function addItem($type, $damage, $count, $send = true){
		while($count > 0){
			$add = 0;
			foreach($this->inventory as $s => $item){
				if($item->getID() === AIR){
					$add = min($item->getMaxStackSize(), $count);
					$this->inventory[$s] = BlockAPI::getItem($type, $damage, $add);
					if($send === true){
						$this->sendInventorySlot($s);
					}
					break;
				}elseif($item->getID() === $type and $item->getMetadata() === $damage){
					$add = min($item->getMaxStackSize() - $item->count, $count);
					if($add <= 0){
						continue;
					}
					$item->count += $add;
					if($send === true){
						$this->sendInventorySlot($s);
					}
					break;
				}
			}
			if($add <= 0){
				return false;
			}
			$count -= $add;
		}
		return true;
	}

	/**
	 * @param string $message
	 * @param string $author
	 */
	public function sendChat($message, $author = ""){
		$mes = explode("\n", $message);
		foreach($mes as $m){
			if(preg_match_all('#@([@A-Za-z_]{1,})#', $m, $matches, PREG_OFFSET_CAPTURE) > 0){
				$offsetshift = 0;
				foreach($matches[1] as $selector){
					if($selector[0][0] === "@"){ //Escape!
						$m = substr_replace($m, $selector[0], $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1);
						--$offsetshift;
						continue;
					}
					switch(strtolower($selector[0])){
						case "player":
						case "username":
							$m = substr_replace($m, $this->username, $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1);
							$offsetshift += strlen($selector[0]) - strlen($this->username) + 1;
							break;
					}
				}
			}

			if($m !== ""){
				$pk = new MessagePacket;
				$pk->source = ($author instanceof Player) ? $author->username : $author;
				$pk->message = TextFormat::clean($m); //Colors not implemented :(
				$this->dataPacket($pk);
			}
		}
	}
	public function makeInvisibleForOnePlayer(Player $player){
		$pk = new RemoveEntityPacket;
		$pk->eid = $this->entity->eid;
		$player->dataPacket($pk);
	}
	public function makeInvisibleForAllPlayers(){
		$pk = new RemoveEntityPacket;
		$pk->eid = $this->entity->eid;
		$this->server->api->player->broadcastPacket($this->server->api->player->getAll($this->level), $pk);
	}
	public function getGamemode(){
		switch($this->gamemode){
			case SURVIVAL:
				return "survival";
			case CREATIVE:
				return "creative";
			case ADVENTURE:
				return "adventure";
			case VIEW:
				return "view";
		}
	}

	public function setGamemode($gm){
		if($gm < 0 or $gm > 3 or $this->gamemode === $gm){
			return false;
		}
		
		if($this->server->api->dhandle("player.gamemode.change", ["player" => $this, "gamemode" => $gm]) === false){
			return false;
		}
		
		$inv =& $this->inventory;
		if($gm === VIEW){
			$this->armor = [];
			$this->sendArmor();
		}
		if(($this->gamemode & 0x01) === ($gm & 0x01)){
			if(($gm & 0x01) === 0x01 and ($gm & 0x02) === 0x02){
				$inv = [];
				foreach(BlockAPI::$creative as $item){
					$inv[] = BlockAPI::getItem(0, 0, 1);
				}
			}elseif(($gm & 0x01) === 0x01){
				$inv = [];
				foreach(BlockAPI::$creative as $item){
					$inv[] = BlockAPI::getItem($item[0], $item[1], 1);
				}
			}
			$this->gamemode = $gm;
			$this->sendChat("Your gamemode has been changed to " . $this->getGamemode() . ".\n");
		}else{
			foreach($this->inventory as $slot => $item){
				$inv[$slot] = BlockAPI::getItem(AIR, 0, 0);
			}
			$this->blocked = true;
			$this->gamemode = $gm;
			$this->sendChat("Your gamemode has been changed to " . $this->getGamemode() . ", you've to do a forced reconnect.\n");
			$this->server->schedule(30, [$this, "close"], "gamemode change", false, true); //Forces a kick
		}
		
		if($this->gamemode === SPECTATOR){
			$this->makeInvisibleForAllPlayers();
		}
		if($this->gamemode === CREATIVE){
			$this->server->api->player->spawnToAllPlayers($this);
		}
		
		$this->inventory = $inv;
		$this->sendSettings();
		$this->sendInventory();
		return true;
	}

	public function sendSettings($nametags = true){
		/*
		 bit mask | flag name
		0x00000001 world_inmutable
		0x00000010 -
		0x00000100 -
		0x00001000 - (autojump)
		0x00010000 -
		0x00100000 - (nametags_visible)
		0x01000000 ?
		0x10000000 ?
		*/
		$flags = 0;
		if(($this->gamemode & 0x02) === 0x02){
			$flags |= 0x01; //Do not allow placing/breaking blocks, adventure mode
		}

		if($nametags !== false){
			$flags |= 0x20; //Show Nametags
		}
		$pk = new AdventureSettingsPacket;
		$pk->flags = $flags;
		$this->dataPacket($pk);
	}

	public function measureLag(){
		if($this->connected === false){
			return false;
		}
		if($this->packetStats[1] > 2){
			$this->packetLoss = $this->packetStats[1] / max(1, $this->packetStats[0] + $this->packetStats[1]);
		}else{
			$this->packetLoss = 0;
		}
		$this->packetStats = [0, 0];
		array_shift($this->bandwidthStats);
		$this->bandwidthStats[] = $this->bandwidthRaw / max(0.00001, microtime(true) - $this->lastMeasure);
		$this->bandwidthRaw = 0;
		$this->lagStat = array_sum($this->lag) / max(1, count($this->lag));
		$this->lag = [];
		$this->sendBuffer();
		$this->lastMeasure = microtime(true);
	}

	public function getLag(){
		return $this->lagStat * 1000;
	}

	public function getPacketLoss(){
		return $this->packetLoss;
	}

	public function getBandwidth(){
		return array_sum($this->bandwidthStats) / max(1, count($this->bandwidthStats));
	}

	public function clearQueue(){
		if($this->connected === false){
			return false;
		}
		ksort($this->received);
		if(($cnt = count($this->received)) > PLAYER_MAX_QUEUE){
			foreach($this->received as $c => $t){
				unset($this->received[$c]);
				--$cnt;
				if($cnt <= PLAYER_MAX_QUEUE){
					break;
				}
			}
		}
	}

	public function handlePacketQueues(){
		if($this->connected === false){
			return false;
		}
		$time = microtime(true);
		if($time > $this->timeout){
			$this->close("timeout");
			return false;
		}

		if(($ackCnt = count($this->ackQueue)) > 0){
			rsort($this->ackQueue);
			$safeCount = (int) (($this->MTU - 1) / 4);
			$packetCnt = (int) ($ackCnt / $safeCount + 1);
			for($p = 0; $p < $packetCnt; ++$p){
				$pk = new RakNetPacket(RakNetInfo::ACK);
				$pk->packets = [];
				for($c = 0; $c < $safeCount; ++$c){
					if(($k = array_pop($this->ackQueue)) === null){
						break;
					}
					$pk->packets[] = $k;
				}
				$this->send($pk);
			}
			$this->ackQueue = [];
		}

		if(($receiveCnt = count($this->receiveQueue)) > 0){
			ksort($this->receiveQueue);
			foreach($this->receiveQueue as $count => $packets){
				unset($this->receiveQueue[$count]);
				foreach($packets as $p){
					if($p instanceof RakNetDataPacket and $p->hasSplit === false){
						if(isset($p->messageIndex) and $p->messageIndex !== false){
							if($p->messageIndex > $this->receiveCount){
								$this->receiveCount = $p->messageIndex;
							}elseif($p->messageIndex !== 0){
								if(isset($this->received[$p->messageIndex])){
									continue;
								}
							}
							$this->received[$p->messageIndex] = true;
						}
						if($p instanceof UseItemPacket && $this->is0105Client){
							$p->decode0105();
						}else{
							$p->decode();
						}
						$this->handleDataPacket($p);
					}
				}
			}
		}

		if($this->nextBuffer <= $time and $this->bufferLen > 0){
			$this->sendBuffer();
		}

		$limit = $time - 5; //max lag
		foreach($this->recoveryQueue as $count => $data){
			if($data->sendtime > $limit){
				break;
			}
			unset($this->recoveryQueue[$count]);
			$this->resendQueue[$count] = $data;
		}

		if(($resendCnt = count($this->resendQueue)) > 0){
			foreach($this->resendQueue as $count => $data){
				unset($this->resendQueue[$count]);
				$this->packetStats[1]++;
				$this->lag[] = microtime(true) - $data->sendtime;
				$data->sendtime = microtime(true);
				$cnt = $this->send($data);
				if(isset($this->chunkCount[$count])){
					unset($this->chunkCount[$count]);
					if(!is_null($cnt) and !is_null($cnt[0]))
						$this->chunkCount[$cnt[0]] = true;
				}
			}
		}
	}

	/**
	 * @param string $reason Reason for closing connection
	 * @param boolean $msg Set to false to silently disconnect player. No broadcast.
	 */
	public function close($reason = "", $msg = true){
		if($this->connected === true){
			foreach($this->evid as $ev){
				$this->server->deleteEvent($ev);
			}
			if($this->username != ""){
				$this->server->api->handle("player.quit", $this);
				$this->save();
			}
			$reason = $reason == "" ? "server stop" : $reason;
			$this->sendChat("You have been kicked. Reason: " . $reason . "\n");
			$this->sendBuffer();
			$this->directDataPacket(new DisconnectPacket);
			$this->connected = false;
			$this->level->freeAllChunks($this);
			$this->loggedIn = false;
			$this->buffer = null;
			unset($this->buffer);
			$this->recoveryQueue = [];
			$this->receiveQueue = [];
			$this->resendQueue = [];
			$this->ackQueue = [];
			$this->server->api->player->remove($this->CID);
			if($msg === true and $this->username != "" and $this->spawned !== false){
				$this->server->api->chat->broadcast($this->username . " left the game: " . $reason);
			}
			$this->spawned = false;
			console("[INFO] " . FORMAT_AQUA . $this->username . FORMAT_RESET . "[/" . $this->ip . ":" . $this->port . "] logged out due to " . $reason);
			$this->windows = [];
			$this->armor = [];
			$this->inventory = [];
			$this->chunksLoaded = [];
			$this->chunksOrder = [];
			$this->chunkCount = [];
			$this->cratingItems = [];
			$this->received = [];
		}
	}

	public function save(){
		if($this->entity instanceof Entity){
			$this->data->set("achievements", $this->achievements);
			$this->data->set("position", [
				"level" => $this->entity->level->getName(),
				"x" => (float) $this->entity->x,
				"y" => (float) $this->entity->y,
				"z" => (float) $this->entity->z,
				"yaw" => (float) $this->entity->yaw,
				"pitch" => (float) $this->entity->pitch
			]);
			$this->data->set("spawn", [
				"level" => $this->spawnPosition->level->getName(),
				"x" => $this->spawnPosition->x,
				"y" => $this->spawnPosition->y,
				"z" => $this->spawnPosition->z
			]);
			$inv = [];
			foreach($this->inventory as $slot => $item){
				if($item instanceof Item){
					if($slot < (($this->gamemode & 0x01) === 0 ? PLAYER_SURVIVAL_SLOTS : PLAYER_CREATIVE_SLOTS)){
						$inv[$slot] = [$item->getID(), $item->getMetadata(), $item->count];
					}
				}
			}
			$this->data->set("inventory", $inv);
			$this->data->set("hotbar", $this->hotbar);

			$armor = [];
			foreach($this->armor as $slot => $item){
				if($item instanceof Item){
					$armor[$slot] = [$item->getID(), $item->getMetadata()];
				}
			}
			$this->data->set("armor", $armor);
			if($this->entity instanceof Entity){
				$this->data->set("health", $this->entity->getHealth());
			}
			$this->data->set("gamemode", $this->gamemode);
		}
	}

	public function directDataPacket(RakNetDataPacket $packet, $reliability = 0, $recover = true){
		if($this->connected === false){
			return false;
		}

		if(EventHandler::callEvent(new DataPacketSendEvent($this, $packet)) === BaseEvent::DENY){
			return [];
		}

		$packet->encode();
		$pk = new RakNetPacket(RakNetInfo::DATA_PACKET_0);
		$pk->data[] = $packet;
		$pk->seqNumber = $this->counter[0]++;
		$pk->sendtime = microtime(true);
		if($recover !== false){
			$this->recoveryQueue[$pk->seqNumber] = $pk;
		}

		$this->send($pk);
		return [$pk->seqNumber];
	}
	
	public function handleDataPacket(RakNetDataPacket $packet){
		if($this->connected === false){
			return;
		}

		if(EventHandler::callEvent(new DataPacketReceiveEvent($this, $packet)) === BaseEvent::DENY){
			return;
		}
		switch($packet->pid()){
			case 0x01:
				break;
			case ProtocolInfo::PONG_PACKET:
				break;
			case ProtocolInfo::PING_PACKET:
				$pk = new PongPacket;
				$pk->ptime = $packet->time;
				$pk->time = abs(microtime(true) * 1000);
				$this->directDataPacket($pk);
				break;
			case ProtocolInfo::DISCONNECT_PACKET:
				$this->close("client disconnect");
				break;
			case ProtocolInfo::CLIENT_CONNECT_PACKET:
				if($this->loggedIn === true){
					break;
				}
				$pk = new ServerHandshakePacket;
				$pk->port = $this->port;
				$pk->session = $packet->session;
				$pk->session2 = Utils::readLong("\x00\x00\x00\x00\x04\x44\x0b\xa9");
				$this->dataPacket($pk);
				break;
			case ProtocolInfo::CLIENT_HANDSHAKE_PACKET:
				if($this->loggedIn === true){
					break;
				}
				break;
			case ProtocolInfo::LOGIN_PACKET:
				if($this->loggedIn === true){
					break;
				}
				$this->blocked = true;
				$this->username = $packet->username;
				$this->iusername = strtolower($this->username);
				$this->loginData = ["clientId" => $packet->clientId, "loginData" => $packet->loginData];
				if(count($this->server->clients) > $this->server->maxClients and !$this->server->api->ban->isOp($this->iusername)){
					$this->close("server is full!", false);
					return;
				}
				
				if((PocketMinecraftServer::$crossplay0105 || PocketMinecraftServer::$is0105) && $packet->protocol1 == 20) {
					$this->is0105Client = true;
					goto next;
				}
				if(!PocketMinecraftServer::$is0105 && ($packet->protocol1 === 17 || $packet->protocol1 === ProtocolInfo::CURRENT_PROTOCOL)){
					$this->is0105Client = false;
					goto next;
				}
				if(PocketMinecraftServer::$crossplay0105 && ($packet->protocol1 === 17 || $packet->protocol1 === ProtocolInfo::CURRENT_PROTOCOL)){
					
					goto next;
				}

				if($packet->protocol1 < ProtocolInfo::CURRENT_PROTOCOL){
					$pk = new LoginStatusPacket;
					$pk->status = 1;
					$this->directDataPacket($pk);
				}else{
					$pk = new LoginStatusPacket;
					$pk->status = 2;
					$this->directDataPacket($pk);
				}
				$this->close("Incorrect protocol #" . $packet->protocol1, false);
				return;
				next:

				if(preg_match('#[^a-zA-Z0-9_]#', $this->username) > 0 or $this->username === "" or $this->iusername === "rcon" or $this->iusername === "console" or $this->iusername === "server"){
					$this->close("Bad username", false);
					return;
				}
				if($this->server->api->handle("player.connect", $this) === false){
					$this->close("Unknown reason", false);
					return;
				}

				if($this->server->whitelist === true and !$this->server->api->ban->inWhitelist($this->iusername)){
					$this->close("Server is white-listed", false);
					return;
				}elseif($this->server->api->ban->isBanned($this->iusername) or $this->server->api->ban->isIPBanned($this->ip)){
					$this->close("You are banned!", false);
					return;
				}
				$this->loggedIn = true;

				if(!isset($this->CID) or $this->CID == null){
					console("[DEBUG] Player " . $this->username . " does not have a CID", true, true, 2);
					$this->CID = Utils::readLong(Utils::getRandomBytes(8, false));
				}
				$u = $this->server->api->player->get($this->iusername, false);
				if($u !== false){
					$u = $this->server->clients[$this->CID];
					$u->close("this player already in game");
				}

				$this->server->api->player->add($this->CID);
				if($this->server->api->handle("player.join", $this) === false){
					$this->close("join cancelled", false);
					return;
				}

				if(!($this->data instanceof Config)){
					$this->close("no config created", false);
					return;
				}

				$this->auth = true;
				if(!$this->data->exists("inventory") or ($this->gamemode & 0x01) === 0x01){
					if(($this->gamemode & 0x01) === 0x01){
						$inv = [];
						if(($this->gamemode & 0x02) === 0x02){
							foreach(BlockAPI::$creative as $item){
								$inv[] = [0, 0, 1];
							}
						}else{
							foreach(BlockAPI::$creative as $item){
								$inv[] = [$item[0], $item[1], 1];
							}
						}
					}
					$this->data->set("inventory", $inv);
				}
				$this->achievements = $this->data->get("achievements");
				$this->data->set("caseusername", $this->username);
				$this->inventory = [];
				foreach($this->data->get("inventory") as $slot => $item){
					if(!is_array($item) or count($item) < 3){
						$item = [AIR, 0, 0];
					}
					$this->inventory[$slot] = BlockAPI::getItem($item[0], $item[1], $item[2]);
				}

				$this->armor = [];
				foreach($this->data->get("armor") as $slot => $item){
					$this->armor[$slot] = BlockAPI::getItem($item[0], $item[1], $item[0] === 0 ? 0 : 1);
				}

				$this->data->set("lastIP", $this->ip);
				$this->data->set("lastID", $this->clientID);

				$this->server->api->player->saveOffline($this->data);


				$pk = new LoginStatusPacket;
				$pk->status = 0;
				$this->dataPacket($pk);
				$spawnPos = $this->getSpawn();
				
				

				if(($this->gamemode & 0x01) === 0x01){
					$this->slot = 0;
					$this->hotbar = [];
				}elseif($this->data->exists("hotbar")){
					$this->hotbar = $this->data->get("hotbar");
					$this->slot = $this->hotbar[0];
				}else{
					$this->slot = -1;//0
					$this->hotbar = [-1, -1, -1, -1, -1, -1, -1, -1, -1];
				}
				$this->entity = $this->server->api->entity->add($this->level, ENTITY_PLAYER, 0, ["player" => $this]);
				$this->eid = $this->entity->eid;
				$this->server->query("UPDATE players SET EID = " . $this->eid . " WHERE CID = " . $this->CID . ";");
				$this->entity->x = $this->data->get("position")["x"];
				$this->entity->y = $this->data->get("position")["y"];
				$this->entity->z = $this->data->get("position")["z"];
				$pk = new StartGamePacket;
				$pk->seed = $this->level->getSeed();
				$pk->spawnX = (int) $this->spawnPosition->x;
				$pk->spawnY = (int) $this->spawnPosition->y;
				$pk->spawnZ = (int) $this->spawnPosition->z;
				$pk->x = $this->entity->x;
				$pk->y = $this->entity->y;
				$pk->z = $this->entity->z;
				$pk->generator = $this->level->generatorType; //1 - inf, 0 - old, 2 - flat
				$pk->gamemode = $this->gamemode & 0x01;
				$pk->eid = 0;
				$this->dataPacket($pk);
				$this->lastCorrect = new Vector3($this->entity->x, $this->entity->y, $this->entity->z);
				if(($level = $this->server->api->level->get($this->data->get("spawn")["level"])) !== false){
					$this->spawnPosition = new Position($this->data->get("spawn")["x"], $this->data->get("spawn")["y"], $this->data->get("spawn")["z"], $level);
					
					$pk = new SetSpawnPositionPacket;
					$pk->x = (int) $this->spawnPosition->x;
					$pk->y = (int) $this->spawnPosition->y;
					$pk->z = (int) $this->spawnPosition->z;
					$this->dataPacket($pk);
				}
				
				//$this->entity->check = false; whaT?
				$this->entity->setName($this->username);
				$this->entity->data["CID"] = $this->CID;
				$this->evid[] = $this->server->event("server.chat", [$this, "eventHandler"]);
				$this->evid[] = $this->server->event("entity.motion", [$this, "eventHandler"]);
				$this->evid[] = $this->server->event("entity.animate", [$this, "eventHandler"]);
				$this->evid[] = $this->server->event("entity.event", [$this, "eventHandler"]);
				$this->evid[] = $this->server->event("entity.metadata", [$this, "eventHandler"]);
				$this->evid[] = $this->server->event("entity.link", [$this, "eventHandler"]);
				$this->evid[] = $this->server->event("player.equipment.change", [$this, "eventHandler"]);
				$this->evid[] = $this->server->event("player.armor", [$this, "eventHandler"]);
				$this->evid[] = $this->server->event("player.pickup", [$this, "eventHandler"]);
				$this->evid[] = $this->server->event("tile.container.slot", [$this, "eventHandler"]);
				$this->evid[] = $this->server->event("tile.update", [$this, "eventHandler"]);
				$this->lastMeasure = microtime(true);
				$this->server->schedule(50, [$this, "measureLag"], [], true);
				
				$pk = new SetTimePacket;
				$pk->time = (int) $this->level->getTime();
				$pk->started = !$this->level->isTimeStopped();
				$this->dataPacket($pk);
				
				
				console("[INFO] " . FORMAT_AQUA . $this->username . FORMAT_RESET . "[/" . $this->ip . ":" . $this->port . "] logged in with entity id " . $this->eid . " at (" . $this->entity->level->getName() . ", " . round($this->entity->x, 2) . ", " . round($this->entity->y, 2) . ", " . round($this->entity->z, 2) . ")");
				$this->entity->setHealth($this->data->get("health"), "spawn", true);

				$this->server->api->entity->spawnAll($this);
				$this->server->api->entity->spawnToAll($this->entity);

				$array = explode("@n", (string)$this->server->motd);
				foreach($array as $msg){
					$this->sendChat($msg."\n");
				}
				$this->sendSettings();
				$this->sendInventory();
				
				$this->blocked = false;
				break;
			case ProtocolInfo::ROTATE_HEAD_PACKET:
				if($this->spawned === false){
					break;
				}
				if(($this->entity instanceof Entity)){
					if($this->blocked === true or $this->server->api->handle("player.move", $this->entity) === false){
						if($this->lastCorrect instanceof Vector3){
							$this->teleport($this->lastCorrect, $this->entity->yaw, $this->entity->pitch, false);
						}
					}else{
						$this->entity->setPosition($this->entity, $packet->yaw, $this->entity->pitch);
					}
				}
				break;
			case ProtocolInfo::MOVE_PLAYER_PACKET:
				if($this->spawned === false){
					$this->spawned = true;
					$this->blocked = false;
					$this->server->handle("player.spawn", $this);
					$this->server->api->chat->broadcast($this->username." joined the game");
					//console("Current position: {$this->entity}");
					$this->server->api->player->spawnAllPlayers($this);
					$this->server->api->player->spawnToAllPlayers($this);
					$this->teleport($this->entity);
					return;
				}
				if($this->isSleeping) break;
				if(($this->entity instanceof Entity) and $packet->messageIndex > $this->lastMovement){
					$this->lastMovement = $packet->messageIndex;
					$newPos = new Vector3($packet->x, $packet->y, $packet->z);
					
					if($this->forceMovement instanceof Vector3){
						if($this->forceMovement->distance($newPos) <= 0.7){
							$this->forceMovement = false;
						}else{
							$this->teleport($this->forceMovement, $this->entity->yaw, $this->entity->pitch, false);
						}
					}
					$speed = $this->entity->getSpeedMeasure();
					if($this->blocked === true or ($this->server->api->getProperty("allow-flight") !== true and (($speed > 9 and ($this->gamemode & 0x01) === 0x00) or $speed > 20 or $this->entity->distance($newPos) > 7)) or $this->server->api->handle("player.move", $this->entity) === false){
						if($this->lastCorrect instanceof Vector3){
							//console("last");
							$this->teleport($this->lastCorrect, $this->entity->yaw, $this->entity->pitch, false);
						}
					}else{
						//console("New Pos: $newPos");
						$this->entity->setPosition($newPos, $packet->yaw, $packet->pitch);
					}
					$this->entity->updateAABB();
				}
				break;
			case 0xa7:
				//TODO 0xa7
				break;
			case ProtocolInfo::PLAYER_EQUIPMENT_PACKET:
				if($this->spawned === false){
					break;
				}
				$packet->eid = $this->eid;

				$data = [];
				$data["eid"] = $packet->eid;
				$data["player"] = $this;

				if($packet->slot === 0x28 or $packet->slot === 0){ //0 for 0.8.0 compatibility
					$data["slot"] = -1;
					$data["item"] = BlockAPI::getItem(AIR, 0, 0);
					if($this->server->handle("player.equipment.change", $data) !== false){
						$this->slot = -1;
					}
					break;
				}else{
					$packet->slot -= 9;
				}


				if(($this->gamemode & 0x01) === SURVIVAL){
					$data["item"] = $this->getSlot($packet->slot);
					if(!($data["item"] instanceof Item)){
						break;
					}
				}elseif(($this->gamemode & 0x01) === CREATIVE){
					$packet->slot = false;
					foreach(BlockAPI::$creative as $i => $d){
						if($d[0] === $packet->item and $d[1] === $packet->meta){
							$packet->slot = $i;
						}
					}
					if($packet->slot !== false){
						$data["item"] = $this->getSlot($packet->slot);
					}else{
						break;
					}
				}else{
					break;//?????
				}

				$data["slot"] = $packet->slot;

				if($this->server->handle("player.equipment.change", $data) !== false){
					$this->slot = $packet->slot;
					if(($this->gamemode & 0x01) === SURVIVAL){
						if(!in_array($this->slot, $this->hotbar)){
							array_pop($this->hotbar);
							array_unshift($this->hotbar, $this->slot);
						}
					}
				}else{
					//$this->sendInventorySlot($packet->slot);
					$this->sendInventory();
				}
				if($this->entity->inAction === true){
					$this->entity->inAction = false;
					$this->entity->updateMetadata();
				}
				break;
			case ProtocolInfo::REQUEST_CHUNK_PACKET:
				break;
			case ProtocolInfo::UPDATE_BLOCK_PACKET:
				$this->level->resendBlocksToPlayers[$this->CID]["{$packet->x}.{$packet->y}.{$packet->z}"] = true;
				break;
			case ProtocolInfo::USE_ITEM_PACKET:
				if(!($this->entity instanceof Entity)){
					break;
				}

				$blockVector = new Vector3($packet->x, $packet->y, $packet->z);

				if(($this->spawned === false or $this->blocked === true) and $packet->face >= 0 and $packet->face <= 5){
					$target = $this->level->getBlock($blockVector);
					$block = $target->getSide($packet->face);

					$pk = new UpdateBlockPacket;
					$pk->x = $target->x;
					$pk->y = $target->y;
					$pk->z = $target->z;
					$pk->block = $target->getID();
					$pk->meta = $target->getMetadata();
					$this->dataPacket($pk);

					$pk = new UpdateBlockPacket;
					$pk->x = $block->x;
					$pk->y = $block->y;
					$pk->z = $block->z;
					$pk->block = $block->getID();
					$pk->meta = $block->getMetadata();
					$this->dataPacket($pk);
					break;
				}
				$this->craftingItems = [];
				$this->toCraft = [];
				$packet->eid = $this->eid;
				$data = [];
				$data["eid"] = $packet->eid;
				$data["player"] = $this;
				$data["face"] = $packet->face;
				$data["x"] = $packet->x;
				$data["y"] = $packet->y;
				$data["z"] = $packet->z;
				$data["item"] = $packet->item;
				$data["meta"] = $packet->meta;
				$data["fx"] = $packet->fx;
				$data["fy"] = $packet->fy;
				$data["fz"] = $packet->fz;
				$data["posX"] = $packet->posX;
				$data["posY"] = $packet->posY;
				$data["posZ"] = $packet->posZ;
				if($packet->face >= 0 and $packet->face <= 5){ //Use Block, place
					if($this->entity->inAction === true){
						$this->entity->inAction = false;
						$this->entity->updateMetadata();
					}

					if($this->blocked === true or ($this->entity->position instanceof Vector3 and $blockVector->distance($this->entity->position) > 10)){

					}elseif($this->getSlot($this->slot)->getID() !== $packet->item or ($this->getSlot($this->slot)->isTool() === false and $this->getSlot($this->slot)->getMetadata() !== $packet->meta)){
						$this->sendInventorySlot($this->slot);
					}else{
						$this->server->api->block->playerBlockAction($this, $blockVector, $packet->face, $packet->fx, $packet->fy, $packet->fz);
						break;
					}
					$target = $this->level->getBlock($blockVector);
					$block = $target->getSide($packet->face);

					$pk = new UpdateBlockPacket;
					$pk->x = $target->x;
					$pk->y = $target->y;
					$pk->z = $target->z;
					$pk->block = $target->getID();
					$pk->meta = $target->getMetadata();
					$this->dataPacket($pk);

					$pk = new UpdateBlockPacket;
					$pk->x = $block->x;
					$pk->y = $block->y;
					$pk->z = $block->z;
					$pk->block = $block->getID();
					$pk->meta = $block->getMetadata();
					$this->dataPacket($pk);
					break;
				}elseif($packet->face === 0xff and $this->server->handle("player.action", $data) !== false){
					$this->entity->inAction = true;
					$this->startAction = microtime(true);
					$this->entity->updateMetadata();
				}
				break;
			case ProtocolInfo::PLAYER_ACTION_PACKET:
				if($this->spawned === false or $this->blocked === true){
					break;
				}
				$packet->eid = $this->eid;
				$this->craftingItems = [];
				$this->toCraft = [];

				switch($packet->action){
					case 5: //Shot arrow
						if($this->entity->inAction === true){
							if($this->getSlot($this->slot)->getID() === BOW){ // and player had arrow){
								if($this->startAction !== false){
									$time = microtime(true) - $this->startAction;
									$d = [
										"x" => $this->entity->x,
										"y" => $this->entity->y + 1.6,
										"z" => $this->entity->z,
										"yaw" => $this->entity->yaw,
										"pitch" => $this->entity->pitch
									];
									$e = $this->server->api->entity->add($this->level, ENTITY_OBJECT, OBJECT_ARROW, $d);
									$e->speedX = -sin(($e->yaw / 180) * M_PI) * cos(($e->pitch / 180) * M_PI);
									$e->speedZ = cos(($e->yaw / 180) * M_PI) * cos(($e->pitch / 180) * M_PI);
									$e->speedY = -sin(($e->pitch / 180) * M_PI);
									$e->shoot($e->speedX, $e->speedY, $e->speedZ, 1.5, 1.0);
									//$this->server->api->entity->spawnToAll($e);
									$e->spawn($this);
									//$this->getSlot($this->slot)->useOn("shooting", true);
									$this->removeItem(ARROW, 0, 1);
								}
							}
						}
						$this->startAction = false;
						$this->entity->inAction = false;
						$this->entity->updateMetadata();
						break;
					case 6: //get out of the bed
						$this->stopSleep();
				}
				break;
			case ProtocolInfo::REMOVE_BLOCK_PACKET:
				$blockVector = new Vector3($packet->x, $packet->y, $packet->z);
				if($this->spawned === false or $this->blocked === true or $this->entity->distance($blockVector) > 8){
					$target = $this->level->getBlock($blockVector);

					$pk = new UpdateBlockPacket;
					$pk->x = $target->x;
					$pk->y = $target->y;
					$pk->z = $target->z;
					$pk->block = $target->getID();
					$pk->meta = $target->getMetadata();
					$this->dataPacket($pk);
					break;
				}
				$this->craftingItems = [];
				$this->toCraft = [];
				$this->server->api->block->playerBlockBreak($this, $blockVector);
				break;
			case ProtocolInfo::PLAYER_ARMOR_EQUIPMENT_PACKET:
				if($this->spawned === false or $this->blocked === true){
					break;
				}
				$this->craftingItems = [];
				$this->toCraft = [];

				$packet->eid = $this->eid;
				for($i = 0; $i < 4; ++$i){
					$s = $packet->slots[$i];
					if($s === 0 or $s === 255){
						$s = BlockAPI::getItem(AIR, 0, 0);
					}else{
						$s = BlockAPI::getItem($s + 256, 0, 1);
					}
					$slot = $this->armor[$i];
					if($slot->getID() !== AIR and $s->getID() === AIR){
						$this->addItem($slot->getID(), $slot->getMetadata(), 1, false);
						$this->armor[$i] = BlockAPI::getItem(AIR, 0, 0);
						$packet->slots[$i] = 255;
					}elseif($s->getID() !== AIR and $slot->getID() === AIR and ($sl = $this->hasItem($s->getID())) !== false){
						$this->armor[$i] = $this->getSlot($sl);
						$this->setSlot($sl, BlockAPI::getItem(AIR, 0, 0), false);
					}elseif($s->getID() !== AIR and $slot->getID() !== AIR and ($slot->getID() !== $s->getID() or $slot->getMetadata() !== $s->getMetadata()) and ($sl = $this->hasItem($s->getID())) !== false){
						$item = $this->armor[$i];
						$this->armor[$i] = $this->getSlot($sl);
						$this->setSlot($sl, $item, false);
					}else{
						$packet->slots[$i] = 255;
					}

				}
				$this->sendArmor();
				if($this->entity->inAction === true){
					$this->entity->inAction = false;
					$this->entity->updateMetadata();
				}
				break;
			case ProtocolInfo::INTERACT_PACKET:
				if($this->spawned === false){
					break;
				}
				$packet->eid = $this->eid;
				$data = [];
				$data["target"] = $packet->target;
				$data["eid"] = $packet->eid;
				$data["action"] = $packet->action;
				$this->craftingItems = [];
				$this->toCraft = [];
				$target = $this->server->api->entity->get($packet->target);
				if($target instanceof Entity and $this->entity instanceof Entity and $this->gamemode !== VIEW and $this->blocked === false and ($target instanceof Entity) and $this->entity->distance($target) <= 8){
					$data["targetentity"] = $packet->target;
					$data["entity"] = $this->entity;
					$data["player"] = $this;
					if($this->server->handle("player.interact", $data) !== false){
						$target->interactWith($this->entity, $packet->action);
					}
				}

				break;
			case ProtocolInfo::ANIMATE_PACKET:
				if($this->spawned === false){
					break;
				}
				$packet->eid = $this->eid;
				$this->server->api->dhandle("entity.animate", ["eid" => $packet->eid, "entity" => $this->entity, "action" => $packet->action]);
				break;
			case ProtocolInfo::RESPAWN_PACKET:
				if($this->spawned === false){
					break;
				}
				if(@$this->entity->dead === false){
					break;
				}
				$this->craftingItems = [];
				$this->toCraft = [];
				$this->teleport($this->spawnPosition);
				if($this->entity instanceof Entity){
					$this->entity->fire = 0;
					$this->entity->air = 300;
					$this->entity->setHealth(20, "respawn", true);
					$this->entity->updateMetadata();
				}else{
					break;
				}
				$this->sendInventory();
				$this->blocked = false;
				$this->server->handle("player.respawn", $this);
				break;
			case ProtocolInfo::SET_HEALTH_PACKET: //Not used
				break;
			case ProtocolInfo::ENTITY_EVENT_PACKET:
				if($this->spawned === false or $this->blocked === true){
					break;
				}
				$this->craftingItems = [];
				$this->toCraft = [];
				$packet->eid = $this->eid;
				if($this->entity->inAction === true){
					$this->entity->inAction = false;
					$this->entity->updateMetadata();
				}
				switch($packet->event){
					case 9: //Eating
						$items = [ //TODO rewrite
							APPLE => 4,
							MUSHROOM_STEW => 10,
							BEETROOT_SOUP => 10,
							BREAD => 5,
							RAW_PORKCHOP => 3,
							COOKED_PORKCHOP => 8,
							BEEF => 3,
							STEAK => 8,
							COOKED_CHICKEN => 6,
							RAW_CHICKEN => 2,
							MELON_SLICE => 2,
							GOLDEN_APPLE => 10,
							PUMPKIN_PIE => 8,
							CARROT => 4,
							POTATO => 1,
							BAKED_POTATO => 6,
							BEETROOT => 1
						];
						$slot = $this->getSlot($this->slot);
						if($this->entity->getHealth() < 20 and isset($items[$slot->getID()])){
							$pk = new EntityEventPacket;
							$pk->eid = 0;
							$pk->event = 9;
							$this->dataPacket($pk);

							$this->entity->heal($items[$slot->getID()], "eating");
							--$slot->count;
							if($slot->count <= 0){
								$this->setSlot($this->slot, BlockAPI::getItem(AIR, 0, 0), false);
							}
							if($slot->getID() === MUSHROOM_STEW or $slot->getID() === BEETROOT_SOUP){
								$this->addItem(BOWL, 0, 1, false);
							}
						}
						break;
				}
				break;
			case ProtocolInfo::DROP_ITEM_PACKET:
				if($this->spawned === false or $this->blocked === true){
					break;
				}
				$packet->eid = $this->eid;
				$packet->item = $this->getSlot($this->slot);
				$this->craftingItems = [];
				$this->toCraft = [];
				$data["eid"] = $packet->eid;
				$data["unknown"] = $packet->unknown;
				$data["item"] = $packet->item;
				$data["player"] = $this;
				if($this->blocked === false and $this->server->handle("player.drop", $data) !== false){
					$f1 = 0.3;
					$sX = -sin(($this->entity->yaw / 180) * M_PI) * cos(($this->entity->pitch / 180) * M_PI) * $f1;
					$sZ = cos(($this->entity->yaw / 180) * M_PI) * cos(($this->entity->pitch / 180) * M_PI) * $f1;
					$sY = -sin(($this->entity->pitch / 180) * M_PI) * $f1 + 0.1;
					$f1 = 0.02;
					$f3 = $this->entity->random->nextFloat() * M_PI * 2.0;
					$f1 *= $this->entity->random->nextFloat();
					$sX += cos($f3) * $f1;
					$sY += ($this->entity->random->nextFloat() - $this->entity->random->nextFloat()) * 0.1;
					$sZ += sin($f3) * $f1;
					
					$this->server->api->entity->dropRawPos(new Position($this->entity->x, $this->entity->y - 0.3 + $this->entity->height - 0.12, $this->entity->z, $this->level), $packet->item, $sX, $sY, $sZ);
					$this->setSlot($this->slot, BlockAPI::getItem(AIR, 0, 0), false);
				}
				if($this->entity->inAction === true){
					$this->entity->inAction = false;
					$this->entity->updateMetadata();
				}
				break;
			case ProtocolInfo::MESSAGE_PACKET:
				if($this->spawned === false){
					break;
				}
				$this->craftingItems = [];
				$this->toCraft = [];
				if(trim($packet->message) != "" and strlen($packet->message) <= 255){
					$message = $packet->message;
					if($message[0] === "/"){ //Command
						if($this instanceof Player){
							console("[DEBUG] " . FORMAT_AQUA . $this->username . FORMAT_RESET . " issued server command: " . $message);
						}else{
							console("[DEBUG] " . FORMAT_YELLOW . "*" . $this . FORMAT_RESET . " issued server command: " . $message);
						}
						$this->server->api->console->run(substr($message, 1), $this);
					}else{
						$data = ["player" => $this, "message" => $message];
						if(Utils::hasEmoji($data["message"])){
							$this->sendChat("Your message contains illegal characters");
							break;
						}
						if($this->server->api->handle("player.chat", $data) !== false){
							$this->server->send2Discord("<" . $this->username . "> " . $message);
							if(isset($data["message"])){
								$this->server->api->chat->send($this, $data["message"]);
							}else{
								$this->server->api->chat->send($this, $message);
							}
						}
					}
				}
				break;
			case ProtocolInfo::CONTAINER_CLOSE_PACKET:
				if($this->spawned === false){
					break;
				}
				$this->craftingItems = [];
				$this->toCraft = [];
				if(isset($this->windows[$packet->windowid])){
					if(is_array($this->windows[$packet->windowid])){
						foreach($this->windows[$packet->windowid] as $ob){
							$pk = new TileEventPacket;
							$pk->x = $ob->x;
							$pk->y = $ob->y;
							$pk->z = $ob->z;
							$pk->case1 = 1;
							$pk->case2 = 0;
							$this->server->api->player->broadcastPacket($this->level->players, $pk);
						}
					}elseif($this->windows[$packet->windowid]->class === TILE_CHEST){
						$pk = new TileEventPacket;
						$pk->x = $this->windows[$packet->windowid]->x;
						$pk->y = $this->windows[$packet->windowid]->y;
						$pk->z = $this->windows[$packet->windowid]->z;
						$pk->case1 = 1;
						$pk->case2 = 0;
						$this->server->api->player->broadcastPacket($this->level->players, $pk);
					}
				}
				unset($this->windows[$packet->windowid]);

				$pk = new ContainerClosePacket;
				$pk->windowid = $packet->windowid;
				$this->dataPacket($pk);
				break;
			case ProtocolInfo::CONTAINER_SET_SLOT_PACKET:
				if($this->spawned === false or $this->blocked === true){
					break;
				}

				if($this->lastCraft <= (microtime(true) - 1)){
					if(isset($this->toCraft[-1])){
						$this->toCraft = [-1 => $this->toCraft[-1]];
					}else{
						$this->toCraft = [];
					}
					$this->craftingItems = [];
				}

				if($packet->windowid === 0){
					$craft = false;
					$slot = $this->getSlot($packet->slot);
					if($slot->count >= $packet->item->count and (($slot->getID() === $packet->item->getID() and $slot->getMetadata() === $packet->item->getMetadata()) or ($packet->item->getID() === AIR and $packet->item->count === 0)) and !isset($this->craftingItems[$packet->slot])){ //Crafting recipe
						$use = BlockAPI::getItem($slot->getID(), $slot->getMetadata(), $slot->count - $packet->item->count);
						$this->craftingItems[$packet->slot] = $use;
						$craft = true;
					}elseif($slot->count <= $packet->item->count and ($slot->getID() === AIR or ($slot->getID() === $packet->item->getID() and $slot->getMetadata() === $packet->item->getMetadata()))){ //Crafting final
						$craftItem = BlockAPI::getItem($packet->item->getID(), $packet->item->getMetadata(), $packet->item->count - $slot->count);
						if(count($this->toCraft) === 0){
							$this->toCraft[-1] = 0;
						}
						$this->toCraft[$packet->slot] = $craftItem;
						$craft = true;
					}elseif(((count($this->toCraft) === 1 and isset($this->toCraft[-1])) or count($this->toCraft) === 0) and $slot->count > 0 and $slot->getID() > AIR and ($slot->getID() !== $packet->item->getID() or $slot->getMetadata() !== $packet->item->getMetadata())){ //Crafting final
						$craftItem = BlockAPI::getItem($packet->item->getID(), $packet->item->getMetadata(), $packet->item->count);
						if(count($this->toCraft) === 0){
							$this->toCraft[-1] = 0;
						}
						$use = BlockAPI::getItem($slot->getID(), $slot->getMetadata(), $slot->count);
						$this->craftingItems[$packet->slot] = $use;
						$this->toCraft[$packet->slot] = $craftItem;
						$craft = true;
					}

					if($craft === true){
						$this->lastCraft = microtime(true);
					}

					if($craft === true and count($this->craftingItems) > 0 and count($this->toCraft) > 0 and ($recipe = $this->craftItems($this->toCraft, $this->craftingItems, $this->toCraft[-1])) !== true){
						if($recipe === false){
							$this->sendInventory();
							$this->toCraft = [];
						}else{
							$this->toCraft = [-1 => $this->toCraft[-1]];
						}
						$this->craftingItems = [];
					}
				}else{
					$this->toCraft = [];
					$this->craftingItems = [];
				}
				if(!isset($this->windows[$packet->windowid])){
					break;
				}

				if(is_array($this->windows[$packet->windowid])){
					$tiles = $this->windows[$packet->windowid];
					if($packet->slot >= 0 and $packet->slot < CHEST_SLOTS){
						$tile = $tiles[0];
						$slotn = $packet->slot;
						$offset = 0;
					}elseif($packet->slot >= CHEST_SLOTS and $packet->slot <= (CHEST_SLOTS << 1)){
						$tile = $tiles[1];
						$slotn = $packet->slot - CHEST_SLOTS;
						$offset = CHEST_SLOTS;
					}else{
						break;
					}

					$item = BlockAPI::getItem($packet->item->getID(), $packet->item->getMetadata(), $packet->item->count);

					$slot = $tile->getSlot($slotn);
					if($this->server->api->dhandle("player.container.slot", [
							"tile" => $tile,
							"slot" => $packet->slot,
							"offset" => $offset,
							"slotdata" => $slot,
							"itemdata" => $item,
							"player" => $this
						]) === false){
						$pk = new ContainerSetSlotPacket;
						$pk->windowid = $packet->windowid;
						$pk->slot = $packet->slot;
						$pk->item = $slot;
						$this->dataPacket($pk);
						break;
					}
					if($item->getID() !== AIR and $slot->getID() == $item->getID()){
						if($slot->count < $item->count){
							if($this->removeItem($item->getID(), $item->getMetadata(), $item->count - $slot->count, false) === false){
								break;
							}
						}elseif($slot->count > $item->count){
							$this->addItem($item->getID(), $item->getMetadata(), $slot->count - $item->count, false);
						}
					}else{
						if($this->removeItem($item->getID(), $item->getMetadata(), $item->count, false) === false){
							break;
						}
						$this->addItem($slot->getID(), $slot->getMetadata(), $slot->count, false);
					}
					$tile->setSlot($slotn, $item, true, $offset);
				}else{
					$tile = $this->windows[$packet->windowid];
					if(($tile->class !== TILE_CHEST and $tile->class !== TILE_FURNACE) or $packet->slot < 0 or ($tile->class === TILE_CHEST and $packet->slot >= CHEST_SLOTS) or ($tile->class === TILE_FURNACE and $packet->slot >= FURNACE_SLOTS)){
						break;
					}
					$item = BlockAPI::getItem($packet->item->getID(), $packet->item->getMetadata(), $packet->item->count);

					$slot = $tile->getSlot($packet->slot);
					if($this->server->api->dhandle("player.container.slot", [
							"tile" => $tile,
							"slot" => $packet->slot,
							"slotdata" => $slot,
							"itemdata" => $item,
							"player" => $this,
						]) === false){
						$pk = new ContainerSetSlotPacket;
						$pk->windowid = $packet->windowid;
						$pk->slot = $packet->slot;
						$pk->item = $slot;
						$this->dataPacket($pk);
						break;
					}

					if($tile->class === TILE_FURNACE and $packet->slot == 2){
						switch($slot->getID()){
							case IRON_INGOT:
								AchievementAPI::grantAchievement($this, "acquireIron");
								break;
						}
					}

					if($item->getID() !== AIR and $slot->getID() == $item->getID()){
						if($slot->count < $item->count){
							if($this->removeItem($item->getID(), $item->getMetadata(), $item->count - $slot->count, false) === false){
								break;
							}
						}elseif($slot->count > $item->count){
							$this->addItem($item->getID(), $item->getMetadata(), $slot->count - $item->count, false);
						}
					}else{
						if($this->removeItem($item->getID(), $item->getMetadata(), $item->count, false) === false){
							break;
						}
						$this->addItem($slot->getID(), $slot->getMetadata(), $slot->count, false);
					}
					$tile->setSlot($packet->slot, $item);
				}
				break;
			case ProtocolInfo::SEND_INVENTORY_PACKET: //TODO, Mojang, enable this ´^_^`
				if($this->spawned === false){
					break;
				}
				break;
			case ProtocolInfo::ENTITY_DATA_PACKET:
				if($this->spawned === false or $this->blocked === true){
					break;
				}
				$this->craftingItems = [];
				$this->toCraft = [];
				$t = $this->server->api->tile->get(new Position($packet->x, $packet->y, $packet->z, $this->level));
				if(($t instanceof Tile) and $t->class === TILE_SIGN){
					if($t->data["creator"] !== $this->username){
						$t->spawn($this);
					}else{
						$nbt = new NBT();
						$nbt->load($packet->namedtag);
						$d = array_shift($nbt->tree);
						if($d["id"] !== TILE_SIGN){
							$t->spawn($this);
						}else{
							$t->setText($d["Text1"], $d["Text2"], $d["Text3"], $d["Text4"]);
						}
					}
				}
				break;
			case ProtocolInfo::PLAYER_INPUT_PACKET:
				break; //TODO player input-
			default:
				console("[DEBUG] Unhandled 0x" . dechex($packet->pid()) . " data packet for " . $this->username . " (" . $this->clientID . "): " . print_r($packet, true), true, true, 2);
				break;
		}
	}
	
	/**
	 * Get an Item which is currently held by player
	 * @return Item
	 */
	
	public function getHeldItem(){
		return $this->getSlot($this->slot);
	}
	
	public function stopSleep(){
		$this->isSleeping = false;
		$this->sleepingTime = 0;
		if($this->entity instanceof Entity){
			$this->entity->updateMetadata();
		}
	}

	public function hasItem($type, $damage = false){
		foreach($this->inventory as $s => $item){
			if($item->getID() === $type and ($item->getMetadata() === $damage or $damage === false) and $item->count > 0){
				return $s;
			}
		}
		return false;
	}

	public function removeItem($type, $damage, $count, $send = true){
		while($count > 0){
			$remove = 0;
			foreach($this->inventory as $s => $item){
				if($item->getID() === $type and $item->getMetadata() === $damage){
					$remove = min($count, $item->count);
					if($remove < $item->count){
						$item->count -= $remove;
					}else{
						$this->inventory[$s] = BlockAPI::getItem(AIR, 0, 0);
					}
					if($send === true){
						$this->sendInventorySlot($s);
					}
					break;
				}
			}
			if($remove <= 0){
				return false;
			}
			$count -= $remove;
		}
		return true;
	}

	/**
	 * @param array $craft
	 * @param array $recipe
	 * @param $type
	 *
	 * @return array|bool
	 */
	public function craftItems(array $craft, array $recipe, $type){
		$craftItem = [0, true, 0];
		unset($craft[-1]);
		
		foreach($craft as $slot => $item){
			if($item instanceof Item){
				$craftItem[0] = $item->getID();
				if($item->getMetadata() !== $craftItem[1] and $craftItem[1] !== true){
					$craftItem[1] = false;
				}else{
					$craftItem[1] = $item->getMetadata();
				}
				$craftItem[2] += $item->count;
			}

		}

		$recipeItems = [];
		foreach($recipe as $slot => $item){
			if(!isset($recipeItems[$item->getID()])){
				$recipeItems[$item->getID()] = [$item->getID(), $item->getMetadata(), $item->count];
			}else{
				if($item->getMetadata() !== $recipeItems[$item->getID()][1]){
					$recipeItems[$item->getID()][1] = false;
				}
				$recipeItems[$item->getID()][2] += $item->count;
			}
		}

		$res = CraftingRecipes::canCraft($craftItem, $recipeItems, $type);

		if(!is_array($res) and $type === 1){
			$res2 = CraftingRecipes::canCraft($craftItem, $recipeItems, 0);
			if(is_array($res2)){
				$res = $res2;
			}
		}

		if(is_array($res)){
			if($this->server->api->dhandle("player.craft", ["player" => $this, "recipe" => $recipe, "craft" => $craft, "type" => $type]) === false){
				return false;
			}
			foreach($recipe as $slot => $item){
				$s = $this->getSlot($slot);
				$s->count -= $item->count;
				if($s->count <= 0){
					$this->setSlot($slot, BlockAPI::getItem(AIR, 0, 0), false);
				}
			}
			foreach($craft as $slot => $item){
				$s = $this->getSlot($slot);
				if($s->count <= 0 or $s->getID() === AIR){
					$this->setSlot($slot, BlockAPI::getItem($item->getID(), $item->getMetadata(), $item->count), false);
				}else{
					$this->setSlot($slot, BlockAPI::getItem($item->getID(), $item->getMetadata(), $s->count + $item->count), false);
				}
				switch($item->getID()){
					case WORKBENCH:
						AchievementAPI::grantAchievement($this, "buildWorkBench");
						break;
					case WOODEN_PICKAXE:
						AchievementAPI::grantAchievement($this, "buildPickaxe");
						break;
					case FURNACE:
						AchievementAPI::grantAchievement($this, "buildFurnace");
						break;
					case WOODEN_HOE:
						AchievementAPI::grantAchievement($this, "buildHoe");
						break;
					case BREAD:
						AchievementAPI::grantAchievement($this, "makeBread");
						break;
					case CAKE:
						AchievementAPI::grantAchievement($this, "bakeCake");
						$this->addItem(BUCKET, 0, 3);
						break;
					case STONE_PICKAXE:
					case GOLDEN_PICKAXE:
					case IRON_PICKAXE:
					case DIAMOND_PICKAXE:
						AchievementAPI::grantAchievement($this, "buildBetterPickaxe");
						break;
					case WOODEN_SWORD:
						AchievementAPI::grantAchievement($this, "buildSword");
						break;
					case DIAMOND:
						AchievementAPI::grantAchievement($this, "diamond");
						break;

				}
			}
		}
		return $res;
	}

	public function handlePacket(RakNetPacket $packet){
		if($this->connected === true){
			$this->timeout = microtime(true) + 20;
			switch($packet->pid()){
				case RakNetInfo::NACK:
					foreach($packet->packets as $count){
						if(isset($this->recoveryQueue[$count])){
							$this->resendQueue[$count] =& $this->recoveryQueue[$count];
							$this->lag[] = microtime(true) - $this->recoveryQueue[$count]->sendtime;
							unset($this->recoveryQueue[$count]);
						}
						++$this->packetStats[1];
					}
					break;

				case RakNetInfo::ACK:
					foreach($packet->packets as $count){
						if(isset($this->recoveryQueue[$count])){
							$this->lag[] = microtime(true) - $this->recoveryQueue[$count]->sendtime;
							unset($this->recoveryQueue[$count]);
							unset($this->resendQueue[$count]);
						}
						++$this->packetStats[0];
					}
					break;

				case RakNetInfo::DATA_PACKET_0:
				case RakNetInfo::DATA_PACKET_1:
				case RakNetInfo::DATA_PACKET_2:
				case RakNetInfo::DATA_PACKET_3:
				case RakNetInfo::DATA_PACKET_4:
				case RakNetInfo::DATA_PACKET_5:
				case RakNetInfo::DATA_PACKET_6:
				case RakNetInfo::DATA_PACKET_7:
				case RakNetInfo::DATA_PACKET_8:
				case RakNetInfo::DATA_PACKET_9:
				case RakNetInfo::DATA_PACKET_A:
				case RakNetInfo::DATA_PACKET_B:
				case RakNetInfo::DATA_PACKET_C:
				case RakNetInfo::DATA_PACKET_D:
				case RakNetInfo::DATA_PACKET_E:
				case RakNetInfo::DATA_PACKET_F:
					$this->ackQueue[] = $packet->seqNumber;
					$this->receiveQueue[$packet->seqNumber] = [];
					foreach($packet->data as $pk){
						$this->receiveQueue[$packet->seqNumber][] = $pk;
					}
					break;
			}
		}
	}

	public function damageArmorPart($slot, $part){
		$part->useOn($this->entity, true); //PocketMine is forced to do it =<. Even if PocketMine doesnt want, he have to damage your armor.
		if($part->getMetadata() >= $part->getMaxDurability()){
			$this->setArmor($slot, BlockAPI::getItem(AIR, 0, 0), false);
			return;
		}
		$this->setArmor($slot, $part, false);
	}

	/**
	 * @return string
	 */
	function __toString(){
		if($this->username != ""){
			return $this->username;
		}
		return $this->clientID;
	}

}

class PlayerNull extends Player{

	public function __construct(){

	}
}
