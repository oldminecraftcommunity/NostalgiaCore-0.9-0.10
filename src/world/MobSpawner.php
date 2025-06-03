<?php

class MobSpawner{
	public static $spawnAnimals = false, $spawnMobs = false;
	private $server;
	public $level;
	public static $MOB_LIMIT = 50;
	public function __construct(Level $level){
		$this->server = ServerAPI::request();
		$this->level = $level;
	}

	public function countEntities(){
		$ents = 0;
		foreach($this->level->entityList as $e){
			if(!$e->isPlayer() && $e->class === ENTITY_MOB) ++$ents;
		}
		return $ents;
	}

	public function handle(){
		if($this->countEntities() > self::$MOB_LIMIT || count($this->level->players) <= 0){
			return false; //not spawning
		}
		return $this->spawnMobs();
	}

	public function spawnMobs(){
		$phase = $this->server->api->time->getPhase($this->level);
		$isMonster = false;
		if(self::$spawnAnimals && $phase == "day"){ //Animal
			$type = mt_rand(10, 13);
			$baby = false; //TODO baby
			$grassOnly = true;
			$isMonster = false;
		}elseif(self::$spawnMobs && $phase == "night"){ //Monster, true night
			$type = mt_rand(32, 35);
			$isMonster = true;
			$grassOnly = false;
			$baby = 2;
		}else{
			return false;
		}
		if(empty($this->level->usedChunks)) return false;
		$chunk = explode(".", array_rand($this->level->usedChunks, 1));
		$x = $chunk[0] * 16;
		$z = $chunk[1] * 16;
		$y = $this->getSafeY($x, $z, $grassOnly, $type >= 32 && $type <= 36 && $type != 35, isMonster: $isMonster);
		if(!$y || $y < 0){
			return false;
		}
		$data = $this->genPosData($x, $y + 0.5, $z);
		if($baby != 2) $data["IsBaby"] = $baby;
		
		$e = $this->server->api->entity->add($this->level, 2, $type, $data);
		
		if($e instanceof Entity){
			$this->server->api->entity->spawnToAll($e);
			//console("[DEBUG] $type spawned at $x, $y, $z");
		}
		return true;
	}
	
	private function genPosData($x, $y, $z){
		return [
			"x" => $x + 0.5,
			"y" => $y,
			"z" => $z + 0.5
		];
	}
	
	protected function getSafeY($x, $z, $grassOnly = false, $highMob = false, $isMonster=false){ //first safe block //TODO check boundingbox
		$allowed = [];
		for($y = 0; $y < 128; ++$y){
			$b = $this->level->level->getBlockID($x, $y, $z);
			$b2 = $this->level->level->getBlockID($x, $y + 1, $z);
			$b1 = $this->level->level->getBlockID($x, $y - 1, $z);
			if(!StaticBlock::getIsSolid($b) && !StaticBlock::getIsLiquid($b) && StaticBlock::getIsSolid($b1)){
				if($grassOnly && $b1 != GRASS){
					continue;
				}
				if($highMob && (StaticBlock::getIsSolid($b2) || StaticBlock::getIsLiquid($b2))){
					continue;
				}
				if($isMonster && ($rb = $this->level->getRawBrightness($x, $y, $z)) > 8){ //dont spawn if too bright
					ConsoleAPI::info("dont use $x $y $z: $rb");
					continue;
				}
				
				$allowed[] = $y;
			}
		}
		
		return empty($allowed) ? -1 : $allowed[mt_rand(0, count($allowed) - 1)];
	}
}

