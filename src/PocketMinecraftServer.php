<?php

class PocketMinecraftServer{
	public static $generateCaves = false;
	public static $chukSendDelay = 5, $chunkLoadingRadius = 4;
	public static $is0105 = false, $crossplay0105 = false;
	public $tCnt, $ticks;
	public $extraprops, $serverID, $interface, $database, $version, $invisible, $tickMeasure, $preparedSQL, $seed, $gamemode, $name, $maxClients, $clients, $eidCnt, $custom, $description, $motd, $port, $saveEnabled;
	/**
	 * @var ServerAPI
	 */
	public $api;
	private $serverip, $evCnt, $handCnt, $events, $eventsID, $handlers, $serverType, $lastTick, $memoryStats, $async = [], $asyncID = 0;
	
	public static $PACKET_READING_LIMIT = 100;
	function __construct($name, $gamemode = SURVIVAL, $seed = false, $port = 19132, $serverip = "0.0.0.0"){
		$this->port = (int) $port;
		$this->doTick = true;
		$this->gamemode = (int) $gamemode;
		$this->name = $name;
		$this->motd = "Welcome to " . $name;
		$this->serverID = false;
		$this->seed = $seed;
		$this->serverip = $serverip;
		$this->load();
	}
	
	private function load(){
		global $dolog;
		
		$this->version = new VersionString();
		/*if(defined("DEBUG") and DEBUG >= 0){
			@cli_set_process_title("NostalgiaCore ".MAJOR_VERSION);
		}*/
		
		console("[INFO] Starting Minecraft PE server on " . ($this->serverip === "0.0.0.0" ? "*" : $this->serverip) . ":" . $this->port);
		
		if(PocketMinecraftServer::$is0105){
			Block::$class[SPRUCE_FENCE_GATE] = "SpruceFenceGateBlock";
			Block::$class[BIRCH_FENCE_GATE] = "BirchFenceGateBlock";
			Block::$class[JUNGLE_FENCE_GATE] = "JungleFenceGateBlock";
			Block::$class[ACACIA_FENCE_GATE] = "AcaciaFenceGateBlock";
			Block::$class[DARK_OAK_FENCE_GATE] = "DarkOakFenceGateBlock";
		}

		GrassColor::init();
		EntityRegistry::registerEntities();
		Structures::initialize();
		Feature::init();
		StaticBlock::init();
		define("BOOTUP_RANDOM", Utils::getRandomBytes(16));
		$this->serverID = $this->serverID === false ? Utils::readLong(substr(Utils::getUniqueID(true, $this->serverip . $this->port), 8)) : $this->serverID;
		$this->seed = $this->seed === false ? Utils::readInt(Utils::getRandomBytes(4, false)) : $this->seed;
		$this->startDatabase();
		$this->api = false;
		$this->tCnt = 1;
		$this->events = [];
		$this->eventsID = [];
		$this->handlers = [];
		$this->invisible = false;
		$this->levelData = false;
		$this->difficulty = 1;
		$this->tiles = [];
		$this->entities = [];
		$this->custom = [];
		$this->evCnt = 1;
		$this->handCnt = 1;
		$this->eidCnt = 1;
		$this->maxClients = 20;
		$this->schedule = [];
		$this->scheduleCnt = 1;
		$this->description = "";
		$this->whitelist = false;
		$this->memoryStats = [];
		$this->clients = [];
		$this->spawn = false;
		$this->saveEnabled = true;
		$this->tickMeasure = array_fill(0, 40, 0);
		$this->setType("normal");
		$this->interface = new MinecraftInterface("255.255.255.255", $this->port, $this->serverip);
		$this->stop = false;
		$this->ticks = 0;
		if(!defined("NO_THREADS")){
			$this->asyncThread = new AsyncMultipleQueue();
		}
		
		console("[INFO] Loading extra.properties...");
		$this->extraprops = new Config(DATA_PATH . "extra.properties", CONFIG_PROPERTIES, [
			"version" => "5",
			"experemental-mob-features" => true,
			"enable-mob-ai" => false,
			"enable-nether-reactor" => true,
			"enable-explosions" => true,
			"enable-rail-connection" => true,
			"save-player-data" => true,
			"save-console-data" => true,
			"query-plugins" => false,
			"discord-msg" => false,
			"discord-ru-smiles" => false,
			"discord-webhook-url" => "none",
			"discord-bot-name" => "NostalgiaCore Logger",
			"despawn-mobs" => true, 
			"mob-despawn-ticks" => 18000,
		]);
		
		Living::$despawnMobs = $this->extraprops->get("despawn-mobs");
		Living::$despawnTimer = $this->extraprops->get("mob-despawn-ticks");
		Entity::$allowedAI = $this->extraprops->get("enable-mob-ai");
		Entity::$updateOnTick = $this->extraprops->get("experemental-mob-features");
		if(Entity::$updateOnTick){
			console("[WARNING] Experemental mob features are enabled. Unpredictable behavior.");
		}
		Explosion::$enableExplosions = $this->extraprops->get("enable-explosions");
		RailBlock::$shouldconnectrails = $this->extraprops->get("enable-rail-connection"); //Rail connection in config
		NetherReactorBlock::$enableReactor = $this->extraprops->get("enable-nether-reactor");
		if($this->extraprops->get("discord-msg") == true){
			if($this->extraprops->get("discord-webhook-url") !== "none"){
				console("[INFO] Discord Logger is enabled.");
			}else{
				console("[WARNING] Discord Logger is enabled in extra.properties,");
				console("[WARNING] but you didn't put the webhook url, so it won't work.");
			}
		}elseif($this->extraprops->get("version") == null){
			console("[WARNING] Your extra.properties file is corrupted!");
			console("[WARNING] To fix it - just remove it! Server will generate it again automatically.");
		}
		$dolog = $this->extraprops->get("save-console-data");
	}

	public function onShutdown(){
		console("[INFO] Saving...");
		$save = $this->saveEnabled;
		$this->saveEnabled = true;
		$this->api->level->saveAll();
		$this->saveEnabled = $save;
		//$this->send2Discord('[INFO] Server stopped!');
	}

	public function startDatabase(){
		$this->preparedSQL = new stdClass();
		$this->preparedSQL->entity = new stdClass();
		$this->preparedSQL->player = new stdClass();
		
		$this->database = new SQLite3(":memory:");
		$this->query("PRAGMA journal_mode = OFF;");
		$this->query("PRAGMA encoding = \"UTF-8\";");
		$this->query("PRAGMA secure_delete = OFF;");
		$this->query("CREATE TABLE players (CID INTEGER PRIMARY KEY, EID NUMERIC, ip TEXT, port NUMERIC, name TEXT UNIQUE COLLATE NOCASE);");
		$this->query("CREATE TABLE entities (EID INTEGER PRIMARY KEY, level TEXT, type NUMERIC, class NUMERIC, hasUpdate NUMERIC, name TEXT, x NUMERIC, y NUMERIC, z NUMERIC, yaw NUMERIC, pitch NUMERIC, health NUMERIC);");
		$this->query("CREATE TABLE tiles (ID INTEGER PRIMARY KEY, level TEXT, class TEXT, x NUMERIC, y NUMERIC, z NUMERIC, spawnable NUMERIC);");
		$this->query("CREATE TABLE actions (ID INTEGER PRIMARY KEY, interval NUMERIC, last NUMERIC, code TEXT, repeat NUMERIC);");
		$this->query("CREATE TABLE handlers (ID INTEGER PRIMARY KEY, name TEXT, priority NUMERIC);");
		$this->query("CREATE TABLE blockUpdates (level TEXT, x INTEGER, y INTEGER, z INTEGER, type INTEGER, delay NUMERIC);");
		$this->query("CREATE TABLE recipes (id INTEGER PRIMARY KEY, type NUMERIC, recipe TEXT);");
		$this->query("PRAGMA synchronous = OFF;");
		$this->preparedSQL->selectHandlers = $this->database->prepare("SELECT DISTINCT ID FROM handlers WHERE name = :name ORDER BY priority DESC;");
		$this->preparedSQL->selectActions = $this->database->prepare("SELECT ID,code,repeat FROM actions WHERE last <= (:time - interval);");
		$this->preparedSQL->updateAction = $this->database->prepare("UPDATE actions SET last = :time WHERE ID = :id;");
		$this->preparedSQL->entity->setPosition = $this->database->prepare("UPDATE entities SET x = :x, y = :y, z = :z, pitch = :pitch, yaw = :yaw WHERE EID = :eid ;");
		$this->preparedSQL->entity->setLevel = $this->database->prepare("UPDATE entities SET level = :level WHERE EID = :eid ;");
		
		$this->preparedSQL->player->deleteCID = $this->database->prepare("DELETE FROM players WHERE CID = :CID;");
		$this->preparedSQL->player->getEq = $this->database->prepare("SELECT ip,port,name FROM players WHERE name = :name;"); //'$name'
		$this->preparedSQL->player->getLike = $this->database->prepare("SELECT ip,port,name FROM players WHERE name LIKE :name;"); //'$name'
	}

	public function query($sql, $fetch = false){
		$result = $this->database->query($sql) or console("[ERROR] [SQL Error] " . $this->database->lastErrorMsg() . ". Query: " . $sql, true, true, 0);
		if($fetch === true and ($result instanceof SQLite3Result)){
			$result = $result->fetchArray(SQLITE3_ASSOC);
		}
		return $result;
	}

	public function setType($type = "normal"){
		switch(trim(strtolower($type))){
			case "normal":
			case "demo":
				$this->serverType = "MCCPP;Demo;";
				break;
			case "minecon":
				$this->serverType = "MCCPP;MINECON;";
				break;
		}
	}

	public function titleTick(){
		$time = microtime(true);
		if(defined("DEBUG") and DEBUG >= 0){
			//echo "\x1b]0;NostalgiaCore " . MAJOR_VERSION . " | Online " . count($this->clients) . "/" . $this->maxClients . " | RAM " . round((memory_get_usage() / 1024) / 1024, 2) . "MB | U " . round(($this->interface->bandwidth[1] / max(1, $time - $this->interface->bandwidth[2])) / 1024, 2) . " D " . round(($this->interface->bandwidth[0] / max(1, $time - $this->interface->bandwidth[2])) / 1024, 2) . " kB/s | TPS " . $this->getTPS() . "\x07";
		}

		$this->interface->bandwidth = [0, 0, $time];
	}

	/**
	 * @return float
	 */
	public function getTPS(){
		$v = array_values($this->tickMeasure);
		$divval = ($v[39] - $v[0]);
		if($divval === 0){
			return 0;
		}
		$tps = 40 / $divval;
		return round($tps, 4);
	}

	public function checkTicks(){
		if($this->getTPS() < 12){
			console("[WARNING] Can't keep up! Is the server overloaded?");
		}
	}

	/**
	 * @param string $reason
	 */
	public function close($reason = "server stop"){
		usleep(2);
		$this->onShutdown();
		if($this->stop !== true){
			if(is_int($reason)){
				$reason = "signal stop";
			}
			if(($this->api instanceof ServerAPI) === true){
				if(($this->api->chat instanceof ChatAPI) === true){
					$this->api->chat->send(false, "Stopping server...");
					new StopMessageThread($this, "[INFO] Stopping server..."); //broadcast didnt want to send message to discord for some reason
				}
			}
			$this->stop = true;
			$this->trigger("server.close", $reason);
			$this->interface->close();

			if(!defined("NO_THREADS")){
				@$this->asyncThread->stop = true;
			}
		}
	}

	public function send2Discord($msg){
		if($this->extraprops->get("discord-msg") == true and $this->extraprops->get("discord-webhook-url") !== "none"){
			$url = $this->extraprops->get("discord-webhook-url");
			$name = $this->extraprops->get("discord-bot-name");
			$this->asyncOperation(ASYNC_CURL_POST, [
				"url" => $url,
				"data" => [
					"username" => $name,
					"content" => $this->extraprops->get("discord-ru-smiles") ? str_replace("@", " ", str_replace("�", "<:imp_cool:1151085500396998719>", str_replace("�", "<:imp_badphp5:1151085478410457120>", str_replace("�", "<:imp_gudjava:1151085431962742784>", str_replace("�", "<:imp_wut:1151085524241621012>", $msg))))) : str_replace("@", "", $msg)
				],
			], null);
		}
	}
	
	public function asyncOperation($type, array $data, callable $callable = null){
		if(defined("NO_THREADS")){
			return false;
		}
		$d = "";
		$type = (int) $type;
		switch($type){
			case ASYNC_CURL_GET:
				if(isset($data["headers"])){
					$jsstr = json_encode($data["headers"]);
				}
				$d .= Utils::writeShort(strlen($data["url"])) . $data["url"] . (isset($data["timeout"]) ? Utils::writeShort($data["timeout"]) : Utils::writeShort(10)) . (isset($data["headers"]) ? Utils::writeShort(strlen($jsstr)) . $jsstr : Utils::writeShort(1) . " ");
				break;
			case ASYNC_CURL_POST:
				$d .= Utils::writeShort(strlen($data["url"])) . $data["url"] . (isset($data["timeout"]) ? Utils::writeShort($data["timeout"]) : Utils::writeShort(10));
				$d .= Utils::writeShort(count($data["data"]));
				foreach($data["data"] as $key => $value){
					$d .= Utils::writeShort(strlen($key)) . $key . Utils::writeInt(strlen($value)) . $value;
				}
				break;
			default:
				return false;
		}
		$ID = $this->asyncID++;
		$this->async[$ID] = $callable;
		$this->asyncThread->input .= Utils::writeInt($ID) . Utils::writeShort($type) . $d;
		return $ID;
	}

	public function trigger($event, $data = ""){
		if(isset($this->events[$event])){
			foreach($this->events[$event] as $evid => $ev){
				if(!is_callable($ev)){
					$this->deleteEvent($evid);
					continue;
				}
				if(is_array($ev)){
					$method = $ev[1];
					$ev[0]->$method($data, $event);
				}else{
					$ev($data, $event);
				}
			}
		}elseif(isset(Deprecation::$events[$event])){
			$sub = "";
			if(Deprecation::$events[$event] !== false){
				$sub = " Substitute \"" . Deprecation::$events[$event] . "\" found.";
			}
			console("[ERROR] Event \"$event\" has been deprecated.$sub [Trigger]");
		}
	}

	public function deleteEvent($id){
		$id = (int) $id;
		if(isset($this->eventsID[$id])){
			$ev = $this->eventsID[$id];
			$this->eventsID[$id] = null;
			unset($this->eventsID[$id]);
			$this->events[$ev][$id] = null;
			unset($this->events[$ev][$id]);
			if(count($this->events[$ev]) === 0){
				unset($this->events[$ev]);
			}
		}
	}

	public function asyncOperationChecker(){
		if(defined("NO_THREADS")){
			return false;
		}
		if(isset($this->asyncThread->output[5])){
			$offset = 0;
			$ID = Utils::readInt(substr($this->asyncThread->output, $offset, 4));
			$offset += 4;
			$type = Utils::readShort(substr($this->asyncThread->output, $offset, 2));
			$offset += 2;
			$data = [];
			switch($type){
				case ASYNC_CURL_GET:
				case ASYNC_CURL_POST:
					$len = Utils::readInt(substr($this->asyncThread->output, $offset, 4));
					$offset += 4;
					$data["result"] = substr($this->asyncThread->output, $offset, $len);
					$this->dhandle("async.curl.get", ["response" => $data["result"]]);
					$offset += $len;
					break;
			}

			$this->asyncThread->output = substr($this->asyncThread->output, $offset);
			if(isset($this->async[$ID]) and $this->async[$ID] !== null and is_callable($this->async[$ID])){
				if(is_array($this->async[$ID])){
					$method = $this->async[$ID][1];
					$result = $this->async[$ID][0]->$method($data, $type, $ID);
				}else{
					$result = $this->async[$ID]($data, $type, $ID);
				}
			}
			unset($this->async[$ID]);
		}
	}

	/**
	 * @param string $event
	 * @param callable $callable
	 * @param integer $priority
	 *
	 * @return boolean
	 */
	public function addHandler($event, callable $callable, $priority = 5){
		if(!is_callable($callable)){
			return false;
		}elseif(isset(Deprecation::$events[$event])){
			$sub = "";
			if(Deprecation::$events[$event] !== false){
				$sub = " Substitute \"" . Deprecation::$events[$event] . "\" found.";
			}
			console("[ERROR] Event \"$event\" has been deprecated.$sub [Adding handle to " . (is_array($callable) ? get_class($callable[0]) . "::" . $callable[1] : $callable) . "]");
		}
		$priority = (int) $priority;
		$hnid = $this->handCnt++;
		$this->handlers[$hnid] = $callable;
		$this->query("INSERT INTO handlers (ID, name, priority) VALUES (" . $hnid . ", '" . str_replace("'", "\\'", $event) . "', " . $priority . ");");
		//console("[INTERNAL] New handler " . (is_array($callable) ? get_class($callable[0]) . "::" . $callable[1] : $callable) . " to special event " . $event . " (ID " . $hnid . ")", true, true, 3);
		return $hnid;
	}

	public function dhandle($e, $d){
		return $this->handle($e, $d);
	}

	public function handle($event, &$data){
		$this->preparedSQL->selectHandlers->reset();
		$this->preparedSQL->selectHandlers->clear();
		$this->preparedSQL->selectHandlers->bindValue(":name", $event, SQLITE3_TEXT);
		$handlers = $this->preparedSQL->selectHandlers->execute();
		$result = null;
		if($handlers instanceof SQLite3Result){
			$call = [];
			while(($hn = $handlers->fetchArray(SQLITE3_ASSOC)) !== false){
				$call[(int) $hn["ID"]] = true;
			}
			$handlers->finalize();
			foreach($call as $hnid => $boolean){
				if($result !== false and $result !== true){
					$called[$hnid] = true;
					$handler = $this->handlers[$hnid];
					if(is_array($handler)){
						$method = $handler[1];
						$result = $handler[0]->$method($data, $event);
					}else{
						$result = $handler($data, $event);
					}
				}else{
					break;
				}
			}
		}elseif(isset(Deprecation::$events[$event])){
			$sub = "";
			if(Deprecation::$events[$event] !== false){
				$sub = " Substitute \"" . Deprecation::$events[$event] . "\" found.";
			}
			console("[ERROR] Event \"$event\" has been deprecated.$sub [Handler]");
		}

		if($result !== false){
			$this->trigger($event, $data);
		}
		return $result;
	}

	public function eventHandler($data, $event){
		switch($event){

		}
	}

	public function init(){
		register_tick_function([$this, "tick"]);
		declare(ticks=5000); //Minimum TPS for main thread locks

		$this->loadEvents();
		register_shutdown_function([$this, "dumpError"]);
		register_shutdown_function([$this, "close"]);
		if(function_exists("pcntl_signal")){
			pcntl_signal(SIGTERM, [$this, "close"]);
			pcntl_signal(SIGINT, [$this, "close"]);
			pcntl_signal(SIGHUP, [$this, "close"]);
		}
		console("[INFO] Default game type: " . strtoupper($this->getGamemode()));
		$this->trigger("server.start", microtime(true));
		console('[INFO] Done (' . round(microtime(true) - START_TIME, 3) . 's)! For help, type "help" or "?"');
		$this->process();
	}

	public function loadEvents(){
		if(ENABLE_ANSI === true){
			$this->schedule(30, [$this, "titleTick"], [], true);
		}
		$this->schedule(20 * 15, [$this, "checkTicks"], [], true);
		$this->schedule(20 * 60, [$this, "checkMemory"], [], true);
		$this->schedule(20 * 45, "Cache::cleanup", [], true);
		$this->schedule(20, [$this, "asyncOperationChecker"], [], true);
	}

	public function schedule($ticks, callable $callback, $data = [], $repeat = false, $eventName = "server.schedule"){
		if(!is_callable($callback)){
			return false;
		}
		$chcnt = $this->scheduleCnt++;
		$this->schedule[$chcnt] = [$callback, $data, $eventName];
		$this->query("INSERT INTO actions (ID, interval, last, repeat) VALUES(" . $chcnt . ", " . ($ticks / 20) . ", " . microtime(true) . ", " . (((bool) $repeat) === true ? 1 : 0) . ");");
		return $chcnt;
	}

	/**
	 * @return string
	 */
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

	public function process()
	{
		$lastLoop = 0;
		while($this->stop === false){
			$packetcnt = 0;
			startReadingAgain:
			$packet = $this->interface->readPacket();
			if($packet instanceof Packet){
				$this->packetHandler($packet);
				$lastLoop = 0;
				if(++$packetcnt > self::$PACKET_READING_LIMIT){
					ConsoleAPI::warn("Reading more than ".self::$PACKET_READING_LIMIT." packets per tick! Forcing ticking!");
				}else{
					goto startReadingAgain;
				}
			}elseif($this->tick() > 0){
				$lastLoop = 0;
			} else{
				++ $lastLoop;
				if($lastLoop < 16){
					usleep(1);
				} elseif($lastLoop < 128){
					usleep(100);
				} elseif($lastLoop < 256){
					usleep(512);
				} else{
					usleep(10000);
				}
			}
			$this->tick();
		}
	}

	public function packetHandler(Packet $packet){
		$data =& $packet;
		$CID = PocketMinecraftServer::clientID($packet->ip, $packet->port);
		if(isset($this->clients[$CID])){
			$this->clients[$CID]->handlePacket($packet);
		}else{
			if($this->handle("server.noauthpacket." . $packet->pid(), $packet) === false){
				return;
			}
			switch($packet->pid()){
				case RakNetInfo::UNCONNECTED_PING:
				case RakNetInfo::UNCONNECTED_PING_OPEN_CONNECTIONS:
					if($this->invisible === true){
						$pk = new RakNetPacket(RakNetInfo::UNCONNECTED_PONG);
						$pk->pingID = $packet->pingID;
						$pk->serverID = $this->serverID;
						$pk->serverType = $this->serverType;
						$pk->ip = $packet->ip;
						$pk->port = $packet->port;
						$this->send($pk);
						break;
					}
					if(!isset($this->custom["times_" . $CID])){
						$this->custom["times_" . $CID] = 0;
					}
					$ln = 15;
					if($this->description == "" or substr($this->description, -1) != " "){
						$this->description .= " ";
					}
					$txt = substr($this->description, $this->custom["times_" . $CID], $ln);
					$txt .= substr($this->description, 0, $ln - strlen($txt));
					$pk = new RakNetPacket(RakNetInfo::UNCONNECTED_PONG);
					$pk->pingID = $packet->pingID;
					$pk->serverID = $this->serverID;
					$pk->serverType = $this->serverType . $this->name . " [" . count($this->clients) . "/" . $this->maxClients . "] " . $txt;
					$pk->ip = $packet->ip;
					$pk->port = $packet->port;
					$this->send($pk);
					$this->custom["times_" . $CID] = ($this->custom["times_" . $CID] + 1) % strlen($this->description);
					break;
				case RakNetInfo::OPEN_CONNECTION_REQUEST_1:
					if($packet->structure !== RakNetInfo::STRUCTURE){
						console("[DEBUG] Incorrect structure #" . $packet->structure . " from " . $packet->ip . ":" . $packet->port, true, true, 2);
						$pk = new RakNetPacket(RakNetInfo::INCOMPATIBLE_PROTOCOL_VERSION);
						$pk->serverID = $this->serverID;
						$pk->ip = $packet->ip;
						$pk->port = $packet->port;
						$this->send($pk);
					}else{
						$pk = new RakNetPacket(RakNetInfo::OPEN_CONNECTION_REPLY_1);
						$pk->serverID = $this->serverID;
						$pk->mtuSize = strlen($packet->buffer);
						$pk->ip = $packet->ip;
						$pk->port = $packet->port;
						$this->send($pk);
					}
					break;
				case RakNetInfo::OPEN_CONNECTION_REQUEST_2:
					if($this->invisible === true){
						break;
					}
					
					
					if($packet->mtuSize > 2048) $packet->mtuSize = 2048;
					if($packet->mtuSize <= 512) $packet->mtuSize = 512;
					
					$this->clients[$CID] = new Player($packet->clientID, $packet->ip, $packet->port, $packet->mtuSize); //New Session!
					$pk = new RakNetPacket(RakNetInfo::OPEN_CONNECTION_REPLY_2);
					$pk->serverID = $this->serverID;
					$pk->port = $this->port;
					$pk->mtuSize = $packet->mtuSize;
					$pk->ip = $packet->ip;
					$pk->port = $packet->port;
					$this->send($pk);
					break;
			}
		}
	}

	public static function clientID($ip, $port){
		return crc32($ip . $port) ^ crc32($port . $ip . BOOTUP_RANDOM);
		//return $ip . ":" . $port;
	}

	public function send(Packet $packet){
		return $this->interface->writePacket($packet);
	}

	public function tick(){
		$time = microtime(true);
		if($this->lastTick <= ($time - 0.05)){
			$this->tickMeasure[] = $this->lastTick = $time;
			unset($this->tickMeasure[key($this->tickMeasure)]);
			++$this->ticks;
			foreach($this->api->level->levels as $l){
				$l->onTick($this);
			}
			return $this->tickerFunction($time);
		}
		return 0;
	}

	public function tickerFunction($time){
		//actions that repeat every x time will go here
		$this->preparedSQL->selectActions->reset();
		$this->preparedSQL->selectActions->bindValue(":time", $time, SQLITE3_FLOAT);
		$actions = $this->preparedSQL->selectActions->execute();

		$actionCount = 0;
		if($actions instanceof SQLite3Result){
			while(($action = $actions->fetchArray(SQLITE3_ASSOC)) !== false){
				$cid = $action["ID"];
				$this->preparedSQL->updateAction->reset();
				$this->preparedSQL->updateAction->bindValue(":time", $time, SQLITE3_FLOAT);
				$this->preparedSQL->updateAction->bindValue(":id", $cid, SQLITE3_INTEGER);
				$this->preparedSQL->updateAction->execute();
				if(!isset($this->schedule[$cid]) || !isset($this->schedule[$cid][0]) || !@is_callable($this->schedule[$cid][0])){
					$return = false;
				}else{
					++$actionCount;
					try{
						$return = @call_user_func($this->schedule[$cid][0] ?? function(){}, $this->schedule[$cid][1], $this->schedule[$cid][2]); //somehow args can be null
					}catch(TypeError $e){
						$m = $e->getMessage()."\nStack trace:\n".$e->getTraceAsString();
						ConsoleAPI::error($m);
						$return = false;
					}
				}

				if($action["repeat"] == 0 or $return === false){
					$this->query("DELETE FROM actions WHERE ID = " . $action["ID"] . ";");
					$this->schedule[$cid] = null;
					unset($this->schedule[$cid]);
				}
			}
			$actions->finalize();
		}
		return $actionCount;
	}

	public function dumpError(){
		if($this->stop === true){
			return;
		}
		ini_set("memory_limit", "-1"); //Fix error dump not dumped on memory problems
		console("[SEVERE] An unrecovereable has ocurred and the server has crashed. Creating an error dump");
		$dump = "```\r\n# NostalgiaCore Error Dump " . date("D M j H:i:s T Y") . "\r\n";
		$er = error_get_last();
		$errorConversion = [
			E_ERROR => "E_ERROR",
			E_WARNING => "E_WARNING",
			E_PARSE => "E_PARSE",
			E_NOTICE => "E_NOTICE",
			E_CORE_ERROR => "E_CORE_ERROR",
			E_CORE_WARNING => "E_CORE_WARNING",
			E_COMPILE_ERROR => "E_COMPILE_ERROR",
			E_COMPILE_WARNING => "E_COMPILE_WARNING",
			E_USER_ERROR => "E_USER_ERROR",
			E_USER_WARNING => "E_USER_WARNING",
			E_USER_NOTICE => "E_USER_NOTICE",
			E_STRICT => "E_STRICT",
			E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
			E_DEPRECATED => "E_DEPRECATED",
			E_USER_DEPRECATED => "E_USER_DEPRECATED",
		];
		$er["type"] = isset($errorConversion[$er["type"]]) ? $errorConversion[$er["type"]] : $er["type"];
		$dump .= "Error: " . var_export($er, true) . "\r\n\r\n";
		if(stripos($er["file"], "plugin") !== false){
			$dump .= "THIS ERROR WAS CAUSED BY A PLUGIN. REPORT IT TO THE PLUGIN DEVELOPER.\r\n";
		}

		$dump .= "Code: \r\n";
		$file = @file($er["file"], FILE_IGNORE_NEW_LINES);
		for($l = max(0, $er["line"] - 10); $l < $er["line"] + 10; ++$l){
			$dump .= "[" . ($l + 1) . "] " . @$file[$l] . "\r\n";
		}
		$dump .= "\r\n\r\n";
		$dump .= "Backtrace: \r\n";
		foreach(getTrace() as $line){
			$dump .= "$line\r\n";
		}
		$dump .= "\r\n\r\n";
		$dump .= "NostalgiaCore version: " . MAJOR_VERSION . " [Protocol " . ProtocolInfo::CURRENT_PROTOCOL . "; API " . CURRENT_API_VERSION . "]\r\n";
		$dump .= "Git commit: " . GIT_COMMIT . "\r\n";
		$dump .= "Source SHA1 sum: " . SOURCE_SHA1SUM . "\r\n";
		$dump .= "uname -a: " . php_uname("a") . "\r\n";
		$dump .= "PHP Version: " . phpversion() . "\r\n";
		$dump .= "Zend version: " . zend_version() . "\r\n";
		$dump .= "OS : " . PHP_OS . ", " . Utils::getOS() . "\r\n";
		$dump .= "Debug Info: " . var_export($this->debugInfo(false), true) . "\r\n\r\n\r\n";
		global $arguments;
		$dump .= "Parameters: " . var_export($arguments, true) . "\r\n\r\n\r\n";
		$p = $this->api->getProperties();
		if($p["rcon.password"] != ""){
			$p["rcon.password"] = "******";
		}
		$dump .= "server.properties: " . var_export($p, true) . "\r\n\r\n\r\n";
		if($this->api->plugin instanceof PluginAPI){
			$plist = $this->api->plugin->getList();
			$dump .= "Loaded plugins:\r\n";
			foreach($plist as $p){
				$dump .= $p["name"] . " " . $p["version"] . " by " . $p["author"] . "\r\n";
			}
			$dump .= "\r\n\r\n";
		}

		$extensions = [];
		foreach(get_loaded_extensions() as $ext){
			$extensions[$ext] = phpversion($ext);
		}

		$dump .= "Loaded Modules: " . var_export($extensions, true) . "\r\n";
		$this->checkMemory();
		$dump .= "Memory Usage Tracking: \r\n" . chunk_split(base64_encode(gzdeflate(implode(";", $this->memoryStats), 9))) . "\r\n";
		ob_start();
		phpinfo();
		$dump .= "\r\nphpinfo(): \r\n" . chunk_split(base64_encode(gzdeflate(ob_get_contents(), 9))) . "\r\n";
		ob_end_clean();
		$dump .= "\r\n```";
		$name = "Error_Dump_" . date("D_M_j-H.i.s-T_Y");
		logg($dump, $name, true, 0, true);
		console("[SEVERE] Please submit the \"{$name}.log\" file to the Bug Reporting page. Give as much info as you can.", true, true, 0);
	}

	public function debugInfo($console = false){
		$info = [];
		$info["tps"] = $this->getTPS();
		$info["memory_usage"] = round((memory_get_usage() / 1024) / 1024, 2) . "MB";
		$info["memory_peak_usage"] = round((memory_get_peak_usage() / 1024) / 1024, 2) . "MB";
		$info["entities"] = $this->query("SELECT count(EID) as count FROM entities;", true);
		$info["entities"] = $info["entities"]["count"];
		$info["players"] = $this->query("SELECT count(CID) as count FROM players;", true);
		$info["players"] = $info["players"]["count"];
		$info["events"] = count($this->eventsID);
		$info["handlers"] = $this->query("SELECT count(ID) as count FROM handlers;", true);
		$info["handlers"] = $info["handlers"]["count"];
		$info["actions"] = $this->query("SELECT count(ID) as count FROM actions;", true);
		$info["actions"] = $info["actions"]["count"];
		$info["garbage"] = gc_collect_cycles();
		$this->handle("server.debug", $info);
		if($console === true){
			console("[DEBUG] TPS: " . $info["tps"] . ", Memory usage: " . $info["memory_usage"] . " (Peak " . $info["memory_peak_usage"] . "), Entities: " . $info["entities"] . ", Events: " . $info["events"] . ", Handlers: " . $info["handlers"] . ", Actions: " . $info["actions"] . ", Garbage: " . $info["garbage"], true, true, 2);
		}
		return $info;
	}

	public function checkMemory(){
		$info = $this->debugInfo();
		$data = $info["memory_usage"] . "," . $info["players"] . "," . $info["entities"];
		$i = count($this->memoryStats) - 1;
		if($i < 0 or $this->memoryStats[$i] !== $data){
			$this->memoryStats[] = $data;
		}
	}

	public function event($event, callable $func){
		if(!is_callable($func)){
			return false;
		}elseif(isset(Deprecation::$events[$event])){
			$sub = "";
			if(Deprecation::$events[$event] !== false){
				$sub = " Substitute \"" . Deprecation::$events[$event] . "\" found.";
			}
			console("[ERROR] Event \"$event\" has been deprecated.$sub [Attach to " . (is_array($func) ? get_class($func[0]) . "::" . $func[1] : $func) . "]");
		}
		$evid = $this->evCnt++;
		if(!isset($this->events[$event])){
			$this->events[$event] = [];
		}
		$this->events[$event][$evid] = $func;
		$this->eventsID[$evid] = $event;
		console("[INTERNAL] Attached " . (is_array($func) ? get_class($func[0]) . "::" . $func[1] : $func) . " to event " . $event . " (ID " . $evid . ")", true, true, 3);
		return $evid;
	}
}
