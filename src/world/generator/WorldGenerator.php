<?php

class WorldGenerator{

	private $seed, $level, $path, $random, $generator, $width, $height;

	public function __construct(LevelGenerator $generator, $name, $seed = false, $width = 16, $height = 8){
		$this->seed = $seed !== false ? (int) $seed : Utils::readInt(Utils::getRandomBytes(4, false));
		$this->random = new Random($this->seed);
		$this->width = (int) $width;
		$this->height = (int) $height;
		$this->path = DATA_PATH . "worlds/" . $name . "/";
		$this->generator = $generator;
		$level = new PMFLevel($this->path . "level.pmf", [
			"name" => $name,
			"seed" => $this->seed,
			"time" => 0,
			"spawnX" => 128,
			"spawnY" => 128,
			"spawnZ" => 128,
			"extra" => "",
			"width" => $this->width,
			"height" => $this->height,
			"generator" => get_class($generator),
		]);
		$entities = new Config($this->path . "entities.yml", CONFIG_YAML);
		$tiles = new Config($this->path . "tiles.yml", CONFIG_YAML);
		$blockUpdates = new Config($this->path . "bupdates.yml", CONFIG_YAML);
		$this->level = new Level($level, $entities, $tiles, $blockUpdates, $name);
	}

	public function generate(){
		$this->generator->init($this->level, $this->random);
		for($Z = 0; $Z < $this->width; ++$Z){
			for($X = 0; $X < $this->width; ++$X){
				$this->generator->generateChunk($X, $Z);
			}
			console("[NOTICE] Generating level " . ceil((($Z + 1) / $this->width) * 100) . "%");
		}
		console("[NOTICE] Populating level");
		$this->generator->populateLevel();
		for($Z = 0; $Z < $this->width; ++$Z){
			for($X = 0; $X < $this->width; ++$X){
				$this->generator->populateChunk($X, $Z);
				//13267025
				//08388608
				$m = 0;
				while($this->level->updateLights($m));
				console("light update for $X $Z finished $m");
			}
			console("[NOTICE] Populating level " . ceil((($Z + 1) / $this->width) * 100) . "%");
		}

		$this->level->setSpawn($this->generator->getSpawn());
		$this->level->save(true, true);
	}

	public function close(){
		$this->level->close();
	}

}