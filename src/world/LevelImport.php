<?php

class LevelImport{

	private $path;

	public function __construct($path){
		$this->path = $path;
	}

	public function import(){
		if(file_exists("{$this->path}/tileEntities.dat")){ //OldPM
			$level = unserialize(file_get_contents($this->path . "level.dat"));
			console("[INFO] Importing OldPM level \"" . $level["LevelName"] . "\" to PMF format");
			$entities = new Config($this->path . "entities.yml", CONFIG_YAML, unserialize(file_get_contents($this->path . "entities.dat")));
			$entities->save();
			$tiles = new Config($this->path . "tiles.yml", CONFIG_YAML, unserialize(file_get_contents($this->path . "tileEntities.dat")));
			$tiles->save();
		}elseif(file_exists("{$this->path}/chunks.dat") && file_exists("{$this->path}/level.dat")){ //Pocket
			$nbt = new NBT();
			$nbt->load(substr(file_get_contents($this->path . "level.dat"), 8));
			$level = array_shift($nbt->tree);
			if($level["LevelName"] == ""){
				$level["LevelName"] = "world" . time();
			}
			console("[INFO] Importing Pocket level \"" . $level["LevelName"] . "\" to PMF format");
			unset($level["Player"]);
			$nbt->load(substr(file_get_contents($this->path . "entities.dat"), 12));
			$entities = array_shift($nbt->tree);
			if(!isset($entities["TileEntities"])){
				$entities["TileEntities"] = [];
			}
			$tiles = $entities["TileEntities"];
			$entities = $entities["Entities"];
			$entities = new Config($this->path . "entities.yml", CONFIG_YAML, $entities);
			$entities->save();
			$tiles = new Config($this->path . "tiles.yml", CONFIG_YAML, $tiles);
			$tiles->save();
		}else{
			return false;
		}

		$pmf = new PMFLevel($this->path . "level.pmf", [
			"name" => $level["LevelName"],
			"seed" => $level["RandomSeed"],
			"time" => $level["Time"],
			"spawnX" => $level["SpawnX"],
			"spawnY" => $level["SpawnY"],
			"spawnZ" => $level["SpawnZ"],
			"extra" => "",
			"width" => 16,
			"height" => 8,
			"generator" => get_class(LevelAPI::createGenerator(LevelAPI::$defaultLevelType))
		]);
		$chunks = new PocketChunkParser();
		$chunks->loadFile("{$this->path}/chunks.dat");
		$chunks->loadLocationTable();
		
		for($Z = 0; $Z < 16; ++$Z){
			for($X = 0; $X < 16; ++$X){
				$chunkraw = $chunks->getChunk($X, $Z);
				$ids = substr($chunkraw, 0, 16*16*128);
				$meta = substr($chunkraw, 16*16*128, 16*16*64);
				$sky = substr($chunkraw, 16*16*128+16*16*64, 16*16*64);
				$block = substr($chunkraw, 16*16*128+16*16*64+16*16*64, 16*16*64);
				$pmf->setChunkData($X, $Z, $ids, $meta, $block, $sky);
			}
			console("[NOTICE] Importing level " . ceil(($Z + 1) / 0.16) . "%");
		}
		
		$pmf->doSaveRound();
		$pmf->close();
		$chunks->map = null;
		$chunks = null;
		if(file_exists($this->path . "level.dat")) @unlink($this->path . "level.dat");
		if(file_exists($this->path . "level.dat_old")) @unlink($this->path . "level.dat_old");
		if(file_exists($this->path . "player.dat")) @unlink($this->path . "player.dat");
		if(file_exists($this->path . "entities.dat")) @unlink($this->path . "entities.dat");
		if(file_exists($this->path . "chunks.dat")) @unlink($this->path . "chunks.dat");
		if(file_exists($this->path . "chunks.dat.gz")) @unlink($this->path . "chunks.dat.gz");
		if(file_exists($this->path . "tiles.dat")) @unlink($this->path . "tiles.dat");
		unset($chunks, $level, $entities, $tiles, $nbt);
		return true;
	}

}