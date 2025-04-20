<?php

class Level{
	/**
	 * A generator type.
	 * 0 - Old
	 * 1 - Infinite
	 * @var integer
	 */
	public $generatorType = 1;
	
	/**
	 * @var Config
	 */
	public $entities;
	/**
	 * This is an array of entities in this world. 
	 * @var Entity[]
	 */
	public $entityList;
	public $tiles, $blockUpdates, $nextSave, $players = [], $level, $mobSpawner;
	public $time, $startCheck, $startTime, $server, $name, $usedChunks, $changedBlocks, $changedCount, $stopTime;
	public $resendBlocksToPlayers = [];
	public $generator;
	public function __construct(PMFLevel $level, Config $entities, Config $tiles, Config $blockUpdates, $name){
		$this->server = ServerAPI::request();
		$this->level = $level;
		$this->level->level = $this;
		$this->entityList = [];
		$this->entities = $entities;
		$this->tiles = $tiles; 
		$this->blockUpdates = $blockUpdates;
		$this->startTime = $this->time = (int) $this->level->getData("time");
		$this->nextSave = $this->startCheck = microtime(true);
		$this->nextSave += 90;
		$this->stopTime = false;
		$this->server->schedule(15, [$this, "checkThings"], [], true);
		$this->server->schedule(20 * 7, [$this, "checkTime"], [], true);
		$this->name = $name;
		$this->usedChunks = [];
		$this->changedBlocks = [];
		$this->changedCount = [];
		$this->mobSpawner = new MobSpawner($this);
		$gen = $this->level->levelData["generator"];
		$this->generator = new $gen([]);
		$this->generator->init($this, new Random($this->getSeed()));
	}

	public function close(){
		$this->__destruct();
	}
	
	
	/**
	 * @param AxisAlignedBB $aABB
	 * @return AxisAlignedBB[]
	 */
	public function getCubes(AxisAlignedBB $aABB) {
		$aABBs = [];
		$x0 = floor($aABB->minX);
		$x1 = ceil($aABB->maxX);
		$y0 = floor($aABB->minY);
		$y1 = round($aABB->maxY);
		$z0 = floor($aABB->minZ);
		$z1 = ceil($aABB->maxZ);
		$x0 = $x0 < 0 ? 0 : $x0;
		$y0 = $y0 < 0 ? 0 : $y0;
		$z0 = $z0 < 0 ? 0 : $z0;
		$x1 = $x1 > 256 ? 256 : $x1;
		$y1 = $y1 > 128 ? 128 : $y1;
		$z1 = $z1 > 256 ? 256 : $z1;
		
		for($x = $x0; $x < $x1; ++$x) {
			for($y = $y0; $y < $y1; ++$y) {
				for($z = $z0; $z < $z1; ++$z) {
					$b = $this->getBlockWithoutVector($x, $y, $z);
					if($b != false && $b->boundingBox->intersectsWith($aABB) && $b->isSolid) {
						$aABBs[] = $b->boundingBox;
					}
				}
			}
		}
		
		return $aABBs;
	}
	
	public function fastSetBlockUpdate($x, $y, $z, $id, $meta, $updateBlocksAround = false){
		$x = (int)$x;
		$y = (int)$y;
		$z = (int)$z;
		$this->level->setBlock($x, $y, $z, $id, $meta);
		$this->sendBlockToAll($x, $y, $z);
		if($updateBlocksAround){
			$this->server->api->block->blockUpdateAround(new Position($x, $y, $z, $this), BLOCK_UPDATE_NORMAL, 1);
		}
	}
	
	public function sendBlockToAll($x, $y, $z){
		foreach($this->players as $p){
			$this->resendBlocksToPlayers[$p->CID]["$x.$y.$z"] = true;
		}
	}
	
	public function __destruct(){
		if(isset($this->level)){
			$this->save(false, false);
			$this->level->close();
			unset($this->level);
		}
		unset($this->mobSpawner->level);
	}
	
	public function checkSleep(){ //TODO events?
		if(count($this->players) == 0) return false;
		if($this->server->api->time->getPhase($this->level) === "night"){ //TODO vanilla
			foreach($this->players as $p){
				if($p->isSleeping == false || $p->sleepingTime < 100){
					return false;
				}
			}
			$this->server->api->time->set("day", $this->level);
		}
		foreach($this->players as $p){
			$p->stopSleep();
		}
	}
	
	public function getBiomeId($x, $z){
		$X = $x >> 4;
		$Z = $z >> 4;
		
		if(!$this->level->isChunkLoaded($X, $Z) && !$this->level->loadChunk($X, $Z, false)){
			$this->level->createUnpopulatedChunk($X, $Z);
		}
		return $this->level->getBiomeId($x, $z);
	}
	
	public function fastSetBlockUpdateMeta($x, $y, $z, $meta, $updateBlock = false){
		$this->level->setBlockDamage($x, $y, $z, $meta);
		$pk = new UpdateBlockPacket;
		$pk->x = $x;
		$pk->y = $y;
		$pk->z = $z;
		$pk->block = $this->level->getBlockID($x, $y, $z);
		$pk->meta = $meta;
		$this->server->api->player->broadcastPacket($this->players, $pk);
		if($updateBlock){
			$this->server->api->block->blockUpdateAround(new Position($x, $y, $z, $this), BLOCK_UPDATE_NORMAL, 1);
		}
	}
	
	public function getOrderedFullChunk($X, $Z){
		$X = (int)$X;
		$Z = (int)$Z;
        //todo: make asynchronous
		if(!isset($this->level)) return false;
		
		$gen = $this->generatorType === 1 || !($X > 15 || $X < 0 || $Z > 15 || $Z < 0);
		$orderedIds = "";
		$orderedData = "";
		$orderedSkyLight = "";
		$orderedLight = "";
		$orderedBiomeIds = "";
		$orderedBiomeColors = "";
		$tileEntities = "";
		
		if($gen) $this->level->generateChunk($X, $Z, $this->generator);
		
		if(!$this->level->isChunkPopulated($X, $Z)) $this->level->unloadChunk($X, $Z);
		if(!$this->level->isChunkLoaded($X, $Z)) {
			if(!$this->level->loadChunk($X, $Z, true)) return false;
		}

		$ci = $this->level->getIndex($X, $Z);
		$orderedBiomeIds = $this->level->biomeInfo[$ci];
		$orderedBiomeColors = $this->level->biomeColorInfo[$ci];
		
		$orderedIds = $this->level->blockIds[$ci]; 
		$orderedData = $this->level->blockMetas[$ci];
		$orderedLight = $this->level->blockLight[$ci];
		$orderedSkyLight = $this->level->skyLight[$ci];
		
		$chunkTiles = [];
		$tiles = $this->server->query("SELECT ID FROM tiles WHERE spawnable = 1 AND level = '".$this->getName()."' AND x >= ".($X * 16 - 1)." AND x < ".($X * 16 + 17)." AND z >= ".($Z * 16 - 1)." AND z < ".($Z * 16 + 17).";");
		if($tiles !== false and $tiles !== true){
			while(($tile = $tiles->fetchArray(SQLITE3_ASSOC)) !== false){
				$tile = $this->server->api->tile->getByID($tile["ID"]);
				if($tile instanceof Tile){
					$chunkTiles[] = $tile;
				}
			}
		}

		$nbt = new NBT_new(NBT_new::LITTLE_ENDIAN);
		foreach($chunkTiles as $tile){ //TODO rewrite TileEntity system
			switch($tile->class){
				case TILE_SIGN:
					$text = $tile->getText();
					$nbt->setData(new Compound("", array(
						new StringTag("Text1", $text[0]),
						new StringTag("Text2", $text[1]),
						new StringTag("Text3", $text[2]),
						new StringTag("Text4", $text[3]),
						new StringTag("id", TILE_SIGN),
						new IntTag("x", (int) $tile->x),
						new IntTag("y", (int) $tile->y),
						new IntTag("z", (int) $tile->z)
					)));
					$tileEntities .= $nbt->write();
					break;
				case TILE_FURNACE:
					//nutting, not spawnable :D
					break;
				case TILE_CHEST:
					if($tile->isPaired()){
						$nbt->setData(new Compound("", array(
							new StringTag("id", "Chest"),
							new IntTag("x", (int) $tile->x),
							new IntTag("y", (int) $tile->y),
							new IntTag("z", (int) $tile->z),
							new IntTag("pairx", (int) $tile->getPair()->x),
							new IntTag("pairz", (int) $tile->getPair()->z)
						)));
					}else{
						$nbt->setData(new Compound("", array(
							new StringTag("id", TILE_CHEST),
							new IntTag("x", (int) $tile->x),
							new IntTag("y", (int) $tile->y),
							new IntTag("z", (int) $tile->z),
						)));
					}
					$tileEntities .= $nbt->write();
					break;
				case TILE_MOB_SPAWNER:
					$nbt->setData(new Compound("", array(
						new StringTag("id", TILE_MOB_SPAWNER),
						new IntTag("x", (int) $tile->x),
						new IntTag("y", (int) $tile->y),
						new IntTag("z", (int) $tile->z),
						new IntTag("EntityId", (int) $tile->data["EntityId"]),
					)));
					$tileEntities .= $nbt->write();
					break;
			}
		}
		$orderedUncompressed = Utils::writeLInt($X) . Utils::writeLInt($Z) .
		$orderedIds . $orderedData . $orderedSkyLight . $orderedLight .
		$orderedBiomeIds . $orderedBiomeColors . $tileEntities;
		$ordered = zlib_encode($orderedUncompressed, ZLIB_ENCODING_DEFLATE, 6);
		return $ordered;
	}
	
	public function isDay(){
		return $this->getTime() % 19200 < TimeAPI::$phases["sunset"];
	}
	public function isNight(){
		$t = $this->getTime() % 19200;
		return $t < TimeAPI::$phases["sunrise"] && $t > TimeAPI::$phases["sunset"];
	}
	public function save($force = false, $extra = true){
		if(!isset($this->level)){
			return false;
		}
		if($this->server->saveEnabled === false and $force === false){
			return;
		}

		if($extra !== false){
			$entities = [];
			foreach($this->entityList as $entity){
				if($entity instanceof Entity){
					$entities[] = $entity->createSaveData();
				}
			}
			$this->entities->setAll($entities);
			$this->entities->save();
			$tiles = [];
			foreach($this->server->api->tile->getAll($this) as $tile){
				$tiles[] = $tile->data;
			}
			$this->tiles->setAll($tiles);
			$this->tiles->save();

			$blockUpdates = [];
			$updates = $this->server->query("SELECT x,y,z,type,delay FROM blockUpdates WHERE level = '" . $this->getName() . "';");
			if($updates !== false and $updates !== true){
				$timeu = microtime(true);
				while(($bupdate = $updates->fetchArray(SQLITE3_ASSOC)) !== false){
					$bupdate["delay"] = max(1, ($bupdate["delay"] - $timeu) * 20);
					$blockUpdates[] = $bupdate;
				}
			}

			$this->blockUpdates->setAll($blockUpdates);
			$this->blockUpdates->save();

		}

		$this->level->setData("time", (int) $this->time);
		$this->level->doSaveRound();
		$this->level->saveData();
		$this->nextSave = microtime(true) + 45;
	}

	public function getName(){
		return $this->name;//return $this->level->getData("name");
	}

	public function useChunk($X, $Z, Player $player){
		//ConsoleAPI::debug("$player uses $X $Z");
		$ind = "$X.$Z";
		if(!isset($this->usedChunks[$ind])){
			$this->usedChunks[$ind] = [];
		}
		$this->usedChunks[$ind][$player->CID] = true;
		if(isset($this->level)){
			$this->level->loadChunk($X, $Z);
		}
	}

	public function freeAllChunks(Player $player){
		foreach($player->chunksLoaded as $chunk => $c){
			$xz = explode(":", $chunk);
			$x = $xz[0];
			$z = $xz[1];
			$player->stopUsingChunk($x, $z);
		}
	}

	public function freeChunk($X, $Z, Player $player){
		unset($this->usedChunks[$X . "." . $Z][$player->CID]);
	}
	
	public function checkCollisionsFor(Entity $e){
		if($e->level->getName() != $this->getName()){
			return false; //not the same world
		}
		foreach($this->entityList as $e1){
			if($e->boundingBox->intersectsWith($e1->boundingBox) && $e1->isCollidable){
				$e->onCollideWith($e1);
				$e1->onCollideWith($e);
			}
		}
	}
	public function isObstructed($e){
		
	}
	
	public function checkThings(){
		if(!isset($this->level)){
			return false;
		}
		$now = microtime(true);
		$this->players = $this->server->api->player->getAll($this);
		$this->checkSleep();
		if(count($this->changedCount) > 0){
			arsort($this->changedCount);
			$resendChunks = [];
			foreach($this->changedCount as $index => $count){
				if($count < 582){//Optimal value, calculated using the relation between minichunks and single packets
					break;
				}
				foreach($this->players as $p){
					unset($p->chunksLoaded[$index]);
				}
				unset($this->changedBlocks[$index]);
			}
			$this->changedCount = [];

			if(count($this->changedBlocks) > 0){
				foreach($this->changedBlocks as $blocks){
					foreach($blocks as $b){
						$pk = new UpdateBlockPacket;
						$pk->x = $b->x;
						$pk->y = $b->y;
						$pk->z = $b->z;
						$pk->block = $b->getID();
						$pk->meta = $b->getMetadata();
						$this->server->api->player->broadcastPacket($this->players, $pk);
					}
				}
				$this->changedBlocks = [];
			}
		}

		if($this->nextSave < $now){
			$this->save(false, false);
		}
	}

	public function isSpawnChunk($X, $Z){
		$spawnX = $this->level->getData("spawnX") >> 4;
		$spawnZ = $this->level->getData("spawnZ") >> 4;
		return abs($X - $spawnX) <= 1 and abs($Z - $spawnZ) <= 1;
	}

	public function getBlockRaw(Vector3 $pos){
		$b = $this->level->getBlock($pos->x, $pos->y, $pos->z);
		return BlockAPI::get($b[0], $b[1], new Position($pos->x, $pos->y, $pos->z, $this));
	}

	public function setBlockRaw(Vector3 $pos, Block $block, $direct = true, $send = true){
		if(($ret = $this->level->setBlock($pos->x, $pos->y, $pos->z, $block->getID(), $block->getMetadata())) === true and $send !== false){
			if($direct === true){
				$pk = new UpdateBlockPacket;
				$pk->x = $pos->x;
				$pk->y = $pos->y;
				$pk->z = $pos->z;
				$pk->block = $block->getID();
				$pk->meta = $block->getMetadata();
				$this->server->api->player->broadcastPacket($this->players, $pk);
			}elseif($direct === false){
				if(!($pos instanceof Position)){
					$pos = new Position($pos->x, $pos->y, $pos->z, $this);
				}
				$block->position($pos);
				$i = ($pos->x >> 4) . ":" . ($pos->y >> 4) . ":" . ($pos->z >> 4);
				
				if(!isset($this->changedBlocks[$i])){
					$this->changedBlocks[$i] = [];
					$this->changedCount[$i] = 0;
				}
				$this->changedBlocks[$i][] = clone $block;
				++$this->changedCount[$i];
			}
		}
		return $ret;
	}
	
	public function onTick(PocketMinecraftServer $server){
		//$ents = $server->api->entity->getAll($this);
		if(!$this->stopTime) $this->time+=2;
		foreach($this->entityList as $k => $e){
			if(!($e instanceof Entity)){
				unset($this->entityList[$k]);
				continue;
			}
			if($e->needsUpdate){
				$e->update();
			}
		}
		if(Entity::$updateOnTick && $server->ticks % 40 === 0){ //40 ticks delay
			$this->mobSpawner->handle();
		}
		
		if($this->generatorType !== 0){ //chunk unloading
			foreach($this->usedChunks as $c => $eids){
				$xz = explode(".", $c); //bad idea, TODO use better indexes
				foreach($eids as $cid => $_){
					$p = $this->server->clients[$cid] ?? false;
					if($p instanceof Player){
						$dist = abs($xz[0] - (((int)$p->entity->x) >> 4)) + abs($xz[1] - (((int)$p->entity->z) >> 4));
						if($dist > PocketMinecraftServer::$chunkLoadingRadius*2){
							//ConsoleAPI::debug("{$p->player} freeing chunk: {$xz[0]} {$xz[1]}");
							$this->freeChunk($xz[0], $xz[1], $p);
							$p->stopUsingChunk($xz[0], $xz[1]);
						}
					}else{
						unset($eids[$cid]);
						unset($this->usedChunks[$c][$cid]);
						ConsoleAPI::debug("Unloaded $c because $cid is not Player");
					}
				}
				if(count($eids) <= 0){
					$outcome = $this->unloadChunk($xz[0], $xz[1], true);
					unset($this->usedChunks[$c]);
					//ConsoleAPI::debug("Unloading: {$xz[0]} {$xz[1]}, status: $outcome");
				}
			}
		}
		
		foreach($this->level->fakeLoaded as $ind => $_){
			$xz = explode(".", $ind);
			$this->level->unloadChunk($xz[0], $xz[1]);
			unset($this->level->fakeLoaded[$ind]);
		}
		foreach($this->resendBlocksToPlayers as $playerCID => $blockz){
			/**
			 * @var Player $player
			 */
			$player = $this->server->clients[$playerCID] ?? false;
			if($player instanceof Player){
				foreach($blockz as $xyz => $_){
					$xyzz = explode(".", $xyz);
					if(count($xyzz) == 3){
						$x = (int) $xyzz[0];
						$y = (int) $xyzz[1];
						$z = (int) $xyzz[2];
						$idm = $this->level->getBlock($x, $y, $z);
						//$player->addToBlockSendQueue(new UpdateBlockPacket($x, $y, $z, $idm[0], $idm[1]));
						$pk = new UpdateBlockPacket;
						$pk->x = $x;
						$pk->y = $y;
						$pk->z = $z;
						$pk->block = $idm[0] & 0xff;
						$pk->meta = $idm[1] & 0xff;
						$player->addToBlockSendQueue($pk);
					}
					unset($blockz[$xyz]);
				}
				$player->sendBlockUpdateQueue();
			}
			unset($this->resendBlocksToPlayers[$playerCID]);
		}
	}
	
	public function setBlock(Vector3 $pos, Block $block, $update = true, $tiles = false, $direct = false){
		$pos->x = (int)$pos->x;
		$pos->y = (int)$pos->y;
		$pos->z = (int)$pos->z;
		if(!isset($this->level) || (($pos instanceof Position) && $pos->level !== $this) || $pos->y < 0){
			return false;
		}
		$ret = $this->level->setBlock($pos->x, $pos->y, $pos->z, $block->getID(), $block->getMetadata());
		if($ret){
			if(!($pos instanceof Position)){
				$pos = new Position($pos->x, $pos->y, $pos->z, $this);
			}
			$block->position($pos);
			if($direct === true){
				$this->sendBlockToAll($pos->x, $pos->y, $pos->z);
			}else{
				$i = ($pos->x >> 4) . ":" . ($pos->y >> 4) . ":" . ($pos->z >> 4);
				if(!isset($this->changedBlocks[$i])){
					$this->changedBlocks[$i] = [];
					$this->changedCount[$i] = 0;
				}
				
				$this->changedBlocks[$i][] = clone $block;
				++$this->changedCount[$i];
			}

			if($update === true){
				$this->server->api->block->blockUpdateAround($pos, BLOCK_UPDATE_NORMAL, 1);
				$this->server->api->entity->updateRadius($pos, 3);
			}
			if($tiles === true){
				if(($t = $this->server->api->tile->get($pos)) !== false){
					$t->close();
				}
			}
		}
		return $ret;
	}

	public function loadChunk($X, $Z){
		if(!isset($this->level)){
			return false;
		}
		return $this->level->loadChunk($X, $Z);
	}

	public function unloadChunk($X, $Z, $force = false){
		if(!isset($this->level)){
			return false;
		}

		if($force !== true and $this->isSpawnChunk($X, $Z)){
			return false;
		}
		
		return $this->level->unloadChunk($X, $Z, $this->server->saveEnabled);
	}

	public function getSafeSpawn($spawn = false){
		if($spawn === false){
			$spawn = $this->getSpawn();
		}
		if($spawn instanceof Vector3){
			$x = (int) round($spawn->x);
			$y = (int) round($spawn->y);
			$z = (int) round($spawn->z);
			if($x < 0 || $x > 255 || $z < 0 || $z > 255){
				return new Position($x, 128, $z, $this);
			}
			for(; $y > 0; --$y){
				$v = new Vector3($x, $y, $z);
				$b = $this->getBlock($v->getSide(0));
				if($b === false){
					return $spawn;
				}elseif(!($b instanceof AirBlock)){
					break;
				}
			}
			for(; $y < 128; ++$y){
				$v = new Vector3($x, $y, $z);
				if($this->getBlock($v->getSide(1)) instanceof AirBlock){
					if($this->getBlock($v) instanceof AirBlock){
						return new Position($x, $y, $z, $this);
					}
				}else{
					++$y;
				}
			}
			return new Position($x, $y, $z, $this);
		}
		return false;
	}

	public function getSpawn(){
		if(!isset($this->level)){
			return false;
		}
		return new Position($this->level->getData("spawnX"), $this->level->getData("spawnY"), $this->level->getData("spawnZ"), $this);
	}
	
	/**
	 * @param number $x
	 * @param number $y
	 * @param number $z
	 * @param boolean $positionfy assign coordinates to block or not
	 * @return GenericBlock | false if failed
	 */
	
	public function getBlockWithoutVector($x, $y, $z, $positionfy = true){
		$b = $this->level->getBlock((int)$x, (int)$y, (int)$z);
		return BlockAPI::get($b[0], $b[1], $positionfy ? new Position($x, $y, $z, $this) : false);
	}
	
	/**
	 * Recommended to use {@link getBlockWithoutVector()} if you dont have the vector
	 * @param Vector3 $pos
	 * @return Block|false if failed
	 */
	public function getBlock(Vector3 $pos){
		if(!isset($this->level) or ($pos instanceof Position) and $pos->level !== $this){
			return false;
		}
		return $this->getBlockWithoutVector($pos->x, $pos->y, $pos->z);
	}

	public function setSpawn(Vector3 $pos){
		if(!isset($this->level)){
			return false;
		}
		$this->level->setData("spawnX", $pos->x);
		$this->level->setData("spawnY", $pos->y);
		$this->level->setData("spawnZ", $pos->z);
	}

	public function getTime(){
		return (int) ($this->time);
	}

	public function setTime($time){
		$this->startTime = $this->time = (int) $time;
		$this->startCheck = microtime(true);
		$this->checkTime();
	}

	public function checkTime(){
		$pk = new SetTimePacket;
		$pk->time = (int) $this->time;
		$pk->started = $this->stopTime == false;
		$this->server->api->player->broadcastPacket($this->players, $pk);
	}
	
	public function isTimeStopped(){
		return $this->stopTime;
	}
	
	public function stopTime(){
		$this->stopTime = true;
		$this->startCheck = 0;
		$this->checkTime();
	}

	public function startTime(){
		$this->stopTime = false;
		$this->startCheck = microtime(true);
		$this->checkTime();
	}

	public function getSeed(){
		if(!isset($this->level)){
			return false;
		}
		return (int) $this->level->getSeed();
	}

	public function setSeed($seed){
		if(!isset($this->level)){
			return false;
		}
		$this->level->setData("seed", (int) $seed);
	}

	public function scheduleBlockUpdate(Position $pos, $delay, $type = BLOCK_UPDATE_SCHEDULED){
		if(!isset($this->level)){
			return false;
		}
		return $this->server->api->block->scheduleBlockUpdate($pos, $delay, $type);
	}
	
	
	/**
	 * @deprecated minichunks were removed
	 */
	public function getMiniChunk($X, $Z, $Y){
		return false;
	}
	/**
	 * @deprecated minichunks were removed
	 */
	public function getOrderedMiniChunk($X, $Z, $Y){
		return false;
	}
	/**
	 * @deprecated minichunks were removed
	 */
	public function setMiniChunk($X, $Z, $Y, $data){
		return false;
	}
}
