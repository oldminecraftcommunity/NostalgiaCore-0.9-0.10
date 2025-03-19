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
		
		if($this->generator instanceof ThreadedGenerator){
			ConsoleAPI::notice("Pregenerating spawn area...");
			$this->level->setSpawn($this->generator->getSpawn());
			for($Z = 0; $Z < $this->width; ++$Z){
				for($X = 0; $X < $this->width; ++$X){
					$this->generator->preGenerateChunk($X, $Z);
				}
			}
			
			while(count($this->generator->getDataProvider()->requested) > 0){
				$t = count($this->generator->getDataProvider()->requested);
				console("[NOTICE] Preparing level " . ceil(((256-$t) / 256) * 100) . "%");
				$this->generator->getDataProvider()->tick($this->generator);
				usleep(500000);
			}
			
		}
		
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