<?php

class LevelAPI{
	/**
	 * @var Level[]
	 */
	public $levels;
	private $server, $default;
	
	public static $defaultLevelType = "DEFAULT";
	
	public function __construct(){
		$this->server = ServerAPI::request();
		$this->levels = [];
	}

	public function init(){
		$this->server->api->console->register("seed", "[world]", [$this, "commandHandler"]);
		$this->server->api->console->register("save-all", "", [$this, "commandHandler"]);
		$this->server->api->console->register("save-on", "", [$this, "commandHandler"]);
		$this->server->api->console->register("save-off", "", [$this, "commandHandler"]);
		$this->server->api->console->register("setwspawn", "Set the spawn position for your current world. ", [$this, "commandHandler"]);
		$this->server->api->console->register("place", "", [$this, "commandHandler"]);
		$this->default = $this->server->api->getProperty("level-name");
		if($this->loadLevel($this->default) === false){
			$this->generateLevel($this->default, $this->server->seed);
			$this->loadLevel($this->default);
		}
		$this->server->spawn = $this->getDefault()->getSafeSpawn();
	}

	public function loadLevel($name){
		if($this->get($name) !== false){
			return true;
		}elseif($this->levelExists($name) === false){
			console("[NOTICE] Level \"" . $name . "\" not found");
			return false;
		}
		$path = DATA_PATH . "worlds/" . $name . "/";
		console("[INFO] Preparing level \"" . $name . "\"");
		$level = new PMFLevel($path . "level.pmf");
		if(!$level->isLoaded){
			console("[ERROR] Could not load level \"" . $name . "\"");
			return false;
		}
		$entities = new Config($path . "entities.yml", CONFIG_YAML);
		if(file_exists($path . "tileEntities.yml")){
			@rename($path . "tileEntities.yml", $path . "tiles.yml");
		}
		$tiles = new Config($path . "tiles.yml", CONFIG_YAML);
		$blockUpdates = new Config($path . "bupdates.yml", CONFIG_YAML);
		$this->levels[$name] = new Level($level, $entities, $tiles, $blockUpdates, $name);
		foreach($entities->getAll() as $entity){
			if(!isset($entity["id"])){
				break;
			}
			
			$entity["x"] = $entity["Pos"][0];
			$entity["y"] = $entity["Pos"][1];
			$entity["z"] = $entity["Pos"][2];
			$entity["yaw"] = $entity["Rotation"][0];
			$entity["pitch"] = $entity["Rotation"][1];
			
			if($entity["id"] === 64){ //Item Drop
				$e = $this->server->api->entity->add($this->levels[$name], ENTITY_ITEM, $entity["Item"]["id"], [
					"meta" => $entity["Item"]["Damage"],
					"stack" => $entity["Item"]["Count"],
					"x" => $entity["Pos"][0],
					"y" => $entity["Pos"][1],
					"z" => $entity["Pos"][2],
					"yaw" => $entity["Rotation"][0],
					"pitch" => $entity["Rotation"][1],
				]);
			}elseif($entity["id"] === FALLING_SAND){
				$e = $this->server->api->entity->add($this->levels[$name], ENTITY_FALLING, $entity["id"], $entity);
				//$e->setPosition(new Vector3($entity["Pos"][0], $entity["Pos"][1], $entity["Pos"][2]), $entity["Rotation"][0], $entity["Rotation"][1]);
				$e->setHealth($entity["Health"]);
			}elseif(Utils::getEntityTypeByID($entity["id"]) === ENTITY_OBJECT){ //Object
				$e = $this->server->api->entity->add($this->levels[$name], ENTITY_OBJECT, $entity["id"], $entity);
				//$e->setPosition(new Vector3($entity["Pos"][0], $entity["Pos"][1], $entity["Pos"][2]), $entity["Rotation"][0], $entity["Rotation"][1]);
				$e->setHealth(1);
			}else{
				$e = $this->server->api->entity->add($this->levels[$name], ENTITY_MOB, $entity["id"], $entity);
				//$e->setPosition(new Vector3($entity["Pos"][0], $entity["Pos"][1], $entity["Pos"][2]), $entity["Rotation"][0], $entity["Rotation"][1]);
				$e->setHealth($entity["Health"]);
			}
		}

		foreach($tiles->getAll() as $tile){
			if(!isset($tile["id"])){
				break;
			}
			$t = $this->server->api->tile->add($this->levels[$name], $tile["id"], $tile["x"], $tile["y"], $tile["z"], $tile);
		}

		$timeu = microtime(true);
		foreach($blockUpdates->getAll() as $bupdate){
			$this->server->api->block->scheduleBlockUpdate(new Position((int) $bupdate["x"], (int) $bupdate["y"], (int) $bupdate["z"], $this->levels[$name]), (float) $bupdate["delay"], (int) $bupdate["type"]);
		}
		return true;
	}

	public function get($name){
		if(isset($this->levels[$name])){
			return $this->levels[$name];
		}
		return false;
	}

	public function levelExists($name){
		if($name === ""){
			return false;
		}
		$path = DATA_PATH . "worlds/" . $name . "/";
		if($this->get($name) === false and !file_exists($path . "level.pmf")){
			$level = new LevelImport($path);
			if($level->import() === false){
				return false;
			}
		}
		return true;
	}
	
	public static function createGenerator($type, $options = []){
		return match($type){
			"FLAT" => new SuperflatGenerator(),
			"EXPERIMENTAL" => new ExperimentalGenerator($options),
			"HELL", "NETHER" => new HellGenerator($options),
			"END" => new EndGenerator($options),
			"THREAD" => new TestGenerator($options),
			default => new NormalGenerator($options)
		};
	}
	
	public function generateLevel($name, $seed = false, $generator = false){
		if($this->levelExists($name)){
			return false;
		}
		$options = [];
		if($this->server->api->getProperty("generator-settings") !== false and trim($this->server->api->getProperty("generator-settings")) != ""){
			$options["preset"] = $this->server->api->getProperty("generator-settings");
		}

		if($generator !== false and class_exists($generator)){
			$generator = new $generator($options);
		}else{
			$type = strtoupper($this->server->api->getProperty("level-type"));
			$generator = $this->createGenerator($type, $options);
		}
		$gen = new WorldGenerator($generator, $name, $seed === false ? Utils::readInt(Utils::getRandomBytes(4, false)) : (int) $seed);
		$gen->generate();
		$gen->close();
		return true;
	}

	public function getDefault(){
		return $this->levels[$this->default];
	}

	public function commandHandler($cmd, $params, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "place":
				if(!isset($params[0]) or $params[0] == "") return "/$cmd <feature class>";
				$class = $params[0];
				(new $class())->build($issuer->entity->level, $issuer->entity->x, $issuer->entity->y, $issuer->entity->z);
				break;
			case "setwspawn":
				if(!($issuer instanceof Player)){
					return ("Please run this command in-game. ");
				}
				$issuer->entity->level->setSpawn(new Vector3($issuer->entity->x, $issuer->entity->y, $issuer->entity->z));
				return ("Spawn set!");
				break;
			case "save-all":
				$output .= "Saving...\n";
				$save = $this->server->saveEnabled;
				$this->server->saveEnabled = true;
				$this->saveAll();
				$this->server->saveEnabled = $save;
				$output .= "Saved the world";
				break;
			case "save-on":
				$this->server->saveEnabled = true;
				break;
			case "save-off":
				$this->server->saveEnabled = false;
				break;
			case "seed":
				if(!isset($params[0]) and ($issuer instanceof Player)){
					$output .= "Seed: " . $issuer->level->getSeed() . "\n";
				}elseif(isset($params[0])){
					if(($lv = $this->server->api->level->get(trim(implode(" ", $params)))) !== false){
						$output .= "Seed: " . $lv->getSeed() . "\n";
					}
				}else{
					$output .= "Seed: " . $this->server->api->level->getDefault()->getSeed() . "\n";
				}
		}
		return $output;
	}

	public function saveAll(){
		foreach($this->levels as $level){
			$level->save();
		}
	}

	public function handle($data, $event){
		switch($event){
		}
	}

	public function __destruct(){
		$this->saveAll();
		foreach($this->levels as $level){
			$this->unloadLevel($level, true);
		}
	}

	public function unloadLevel(Level $level, $force = false){
		$name = $level->getName();
		if($name === $this->default and $force !== true){
			return false;
		}
		console("[INFO] Unloading level \"" . $name . "\"");
		$level->nextSave = PHP_INT_MAX;
		$level->save();
		foreach($this->server->api->player->getAll($level) as $player){
			$player->teleport($this->server->spawn);
		}
		foreach($this->server->api->entity->getAll($level) as $entity){
			if($entity->class !== ENTITY_PLAYER){
				$entity->close();
			}
		}
		foreach($this->server->api->tile->getAll($level) as $tile){
			$tile->close();
		}
		$level->close();
		unset($this->levels[$name]);
		return true;
	}

	public function getSpawn(){
		return $this->server->spawn;
	}

	public function loadMap(){
		if($this->mapName !== false and trim($this->mapName) !== ""){
			if(!file_exists($this->mapDir . "level.pmf")){
				$level = new LevelImport($this->mapDir);
				$level->import();
			}
			$this->level = new PMFLevel($this->mapDir . "level.pmf");
			console("[INFO] Preparing level \"" . $this->level->getData("name") . "\"");
			$this->time = (int) $this->level->getData("time");
			$this->seed = (int) $this->level->getData("seed");
			$this->spawn = $this->level->getSpawn();
		}
	}

	public function getAll(){
		return $this->levels;
	}

}
