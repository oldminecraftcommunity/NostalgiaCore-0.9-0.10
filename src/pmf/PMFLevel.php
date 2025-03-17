<?php

define("PMF_CURRENT_LEVEL_VERSION", 0x03);

class PMFLevel extends PMF{

	public $isLoaded = true;
	/**
	 * @var $level Level
	 */
	public $level;
	public $levelData = [];
	private $locationTable = [];
	private $log = 4;
	private $payloadOffset = 0;
	private $chunks = [];
	private $chunkChange = [];
	public $chunkInfo = [];
	public $populated = [];
	public $fakeLoaded = [];
	public function __construct($file, $blank = false){
		if(is_array($blank)){
			$this->create($file, 0);
			$this->levelData = $blank;
			$this->createBlank();
			$this->isLoaded = true;
			$this->log = (int) ((string) log($this->levelData["width"], 2));
		}else{
			if($this->load($file) !== false){
				$this->parseInfo();
				if($this->parseLevel($file) === false){
					$this->isLoaded = false;
				}else{
					$this->isLoaded = true;
					$this->log = (int) ((string) log($this->levelData["width"], 2));
				}
			}else{
				$this->isLoaded = false;
			}
		}
	}
	
	public function setPopulated($X, $Z, $bool = true){
		$this->populated[self::getIndex($X, $Z)] = $bool;
	}
	
	private function createBlank(){
		$this->saveData(false);
		$this->locationTable = [];
		//$cnt = pow($this->levelData["width"], 2);
		$dirname = dirname($this->file) . "/chunks/";
		if(!is_dir($dirname)){
			@mkdir($dirname , 0755);
		}
		
		for($X = 0; $X < 16; ++$X){
			for($Z = 0; $Z < 16; ++$Z){
				$index = $this->getIndex($X, $Z);
				$this->initCleanChunk($X, $Z);
				//$this->chunks[$index] = false;
				//$this->chunkChange[$index] = false;
				//$this->locationTable[$index] = [0 => 0,];
				//$this->write(Utils::writeShort(0));
				//$X = $Z = null;
				//$this->getXZ($index, $X, $Z);
				@file_put_contents($this->getChunkPath($X, $Z), gzdeflate("", PMF_LEVEL_DEFLATE_LEVEL));
			}
		}
		if(!file_exists(dirname($this->file) . "/entities.yml")){
			$entities = new Config(dirname($this->file) . "/entities.yml", CONFIG_YAML);
			$entities->save();
		}
		if(!file_exists(dirname($this->file) . "/tiles.yml")){
			$tiles = new Config(dirname($this->file) . "/tiles.yml", CONFIG_YAML);
			$tiles->save();
		}
	}

	public function saveData($locationTable = true){
		$this->levelData["version"] = PMF_CURRENT_LEVEL_VERSION;
		@ftruncate($this->fp, 5);
		$this->seek(5);
		$this->write(chr($this->levelData["version"]));
		$this->write(Utils::writeShort(strlen($this->levelData["name"])) . $this->levelData["name"]);
		$this->write(Utils::writeInt($this->levelData["seed"]));
		$this->write(Utils::writeInt($this->levelData["time"]));
		$this->write(Utils::writeFloat($this->levelData["spawnX"]));
		$this->write(Utils::writeFloat($this->levelData["spawnY"]));
		$this->write(Utils::writeFloat($this->levelData["spawnZ"]));
		$this->write(chr($this->levelData["width"]));
		$this->write(chr($this->levelData["height"]));
		$this->write(Utils::writeShort(strlen($this->levelData["generator"])).$this->levelData["generator"]);
		$extra = gzdeflate($this->levelData["extra"], PMF_LEVEL_DEFLATE_LEVEL);
		$this->write(Utils::writeShort(strlen($extra)) . $extra);
		$this->payloadOffset = ftell($this->fp);

		if($locationTable !== false){
			//$this->writeLocationTable();
		}
	}

	private function writeLocationTable(){
		//$cnt = pow($this->levelData["width"], 2);
		@ftruncate($this->fp, $this->payloadOffset);
		$this->seek($this->payloadOffset);
		for($X = 0; $X < 16; ++$X){
			for($Z = 0; $Z < 16; ++$Z){
				$this->write(Utils::writeShort($this->locationTable[$this->getIndex($X, $Z)][0]));
			}
		}
	}

	public function getXZ($index, &$X = null, &$Z = null){
		$Z = $index >> 16;
		$X = ($index & 0x8000) === 0x8000 ? -($index & 0x7fff) : $index & 0x7fff;
		return array($X, $Z);
	}

	private function getChunkPath($X, $Z){
		return dirname($this->file) . "/chunks/" . $Z . "." . $X . ".pmc";
	}
	
	public function loadNCPMF0Chunk($X, $Z){
		$index = $this->getIndex($X, $Z);
		
		if($this->isChunkLoaded($X, $Z)){
			return true;
			
		}elseif(!isset($this->locationTable[$index])){
			return false;
		}
		
		$info = $this->locationTable[$index];
		//$this->seek($info[0]);
		
		$chunk = @gzopen($this->getChunkPath($X, $Z), "rb");
		if($chunk === false){
			return false;
		}
		$this->chunks[$index] = [];
		$this->chunkChange[$index] = [-1 => false];
		$this->chunkInfo[$index][0] = str_repeat(ord(BIOME_PLAINS), 256);
		$this->chunkInfo[$index][1] = ""; //biome color data, passing strlen==0 to force regenerate on next normal chunk load
		$this->setPopulated($X, $Z);
		
		for($Y = 0; $Y < $this->levelData["height"]; ++$Y){
			$t = 1 << $Y; 
			if(($info[0] & $t) === $t){
				// 4096 + 2048 + 2048, Block Data, Meta, Light
				if(strlen($chunkDataHere = gzread($chunk, 8192)) < 8192){
					console("[NOTICE] Empty corrupt chunk detected [$X,$Z,:$Y], recovering contents", true, true, 2);
					$this->fillMiniChunk($X, $Z, $Y);
				}else{
					
					$this->chunks[$index][$Y] = str_repeat("\x00", 16384);
					$this->chunkChange[$index][-1] = true;
					$this->chunkChange[$index][$Y] = 16384;

					//Convert id-meta to id-meta-light-light
					for($x = 0; $x < 16; ++$x){
						for($z = 0; $z < 16; ++$z){
							for($y = 0; $y < 16; ++$y){
								$id = $chunkDataHere[($y + ($x << 5) + ($z << 9))] ?? "\x00";
								$meta = $chunkDataHere[(($y >> 1) + 16 + ($x << 5) + ($z << 9))] ?? "\x00";
								
								$bindex = (int) ($y + ($x << 6) + ($z << 10));
								$mindex = (int) (($y >> 1) + 16 + ($x << 6) + ($z << 10));
								
								$this->chunks[$index][$Y][$bindex] = $id;
								$this->chunks[$index][$Y][$mindex] = $meta;
							}
						}
					}
				}
			}else{
				$this->chunks[$index][$Y] = false;
			}
		}
		@gzclose($chunk);
		return true;
	}
	
	public function loadNCPMF1Chunk($X, $Z){
		$index = $this->getIndex($X, $Z);
		
		if($this->isChunkLoaded($X, $Z)){
			return true;
		}
		
		$cp = $this->getChunkPath($X, $Z);
		if(!is_file($cp)) return false;
		$chunk = file_get_contents($cp);
		if($chunk === false){
			return false;
		}
		$chunk = zlib_decode($chunk);
		$offset = 0;
		if(strlen($chunk) === 0) return false;
		$info = [0 => Utils::readShort(substr($chunk, $offset, 2))];
		$offset+=2;
		$this->chunks[$index] = [];
		$this->chunkChange[$index] = [-1 => false];
		$this->chunkInfo[$index][0] = substr($chunk, $offset, 256); //Biome data
		$this->chunkInfo[$index][1] = ""; //biome color data, passing strlen==0 to force regenerate on next normal chunk load
		$offset += 256;
		for($Y = 0; $Y < $this->levelData["height"]; ++$Y){
			$t = 1 << $Y;
			if(($info[0] & $t) === $t){
				// 4096 + 4096 + 4096 + 4096, Id, Meta, BlockLight, Skylight
				if(strlen($this->chunks[$index][$Y] = substr($chunk, $offset, 16384)) < 16384){
					console("[NOTICE] Empty corrupt chunk detected [$X,$Z,:$Y], recovering contents", true, true, 2);
					$this->fillMiniChunk($X, $Z, $Y);
				}
				$offset += 16384;
			}else{
				$this->chunks[$index][$Y] = false;
			}
		}
		
		$this->setPopulated($X, $Z, true);
		
		$this->chunkChange[$index][-1] = true; //force save
		return true;
	}
	
	public function loadNCPMF2Chunk($X, $Z){
		$index = $this->getIndex($X, $Z);
		if($this->isChunkLoaded($X, $Z)) return true;

		$cp = $this->getChunkPath($X, $Z);
		if(!is_file($cp)) return false;

		$chunk = file_get_contents($cp);
		if($chunk === false) return false;

		$chunk = zlib_decode($chunk);
		$offset = 0;
		if(strlen($chunk) === 0) return false;
		$info = [0 => Utils::readShort(substr($chunk, $offset, 2))];
		$offset+=2;
		$populated = ord($chunk[$offset]) > 0;
		++$offset;

		$this->chunks[$index] = [];
		$this->chunkChange[$index] = [-1 => false];
		$this->chunkInfo[$index][0] = substr($chunk, $offset, 256); //Biome data
		$this->chunkInfo[$index][1] = ""; //biome color data, passing strlen==0 to force regenerate on next normal chunk load

		$offset += 256;
		for($Y = 0; $Y < $this->levelData["height"]; ++$Y){
			$t = 1 << $Y;
			if(($info[0] & $t) === $t){
				// 4096 + 4096 + 4096 + 4096, Id, Meta, BlockLight, Skylight
				if(strlen($this->chunks[$index][$Y] = substr($chunk, $offset, 16384)) < 16384){
					console("[NOTICE] Empty corrupt chunk detected [$X,$Z,:$Y], recovering contents", true, true, 2);
					$this->fillMiniChunk($X, $Z, $Y);
				}
				$offset += 16384;
			}else{
				$this->chunks[$index][$Y] = false;
			}
		}
		$this->setPopulated($X, $Z, $populated);

		$this->chunkChange[$index][-1] = true; //force save
		return true;
	}
	
	protected function parseLevel($worldFile){
		if($this->getType() !== 0x00){
			return false;
		}
		$this->seek(5);
		$this->levelData["version"] = ord($this->read(1));
		if($this->levelData["version"] != PMF_CURRENT_LEVEL_VERSION){
			$cv = PMF_CURRENT_LEVEL_VERSION;
			ConsoleAPI::warn("The level version does not match current. ({$this->levelData["version"]} != {$cv})");
			
			switch($this->levelData["version"]){
				case 0:
					ConsoleAPI::notice("Converting the world from NCPMF-{$this->levelData["version"]} to NCPMF-$cv...");
					$worldDir = substr($worldFile, 0, -strlen("/level.pmf"));
					$backupDir = "auto-world-backup-".microtime(true);
					ConsoleAPI::info("Creating backup in $backupDir...");
					copydir($worldDir, $backupDir);
					
					ConsoleAPI::info("Starting converting...");
					$this->levelData["name"] = $this->read(Utils::readShort($this->read(2), false));
					$this->levelData["seed"] = Utils::readInt($this->read(4));
					$this->levelData["time"] = Utils::readInt($this->read(4));
					$this->levelData["spawnX"] = Utils::readFloat($this->read(4));
					$this->levelData["spawnY"] = Utils::readFloat($this->read(4));
					$this->levelData["spawnZ"] = Utils::readFloat($this->read(4));
					$this->levelData["width"] = ord($this->read(1));
					$this->levelData["height"] = ord($this->read(1));
					
					ConsoleAPI::notice("Choosing ".LevelAPI::$defaultLevelType." generator.");
					$generator = LevelAPI::createGenerator(LevelAPI::$defaultLevelType);
					$this->levelData["generator"] = get_class($generator);
					
					$lastseek = ftell($this->fp);
					if(($len = $this->read(2)) === false or ($this->levelData["extra"] = @gzinflate($this->read(Utils::readShort($len, false)))) === false){ //Corruption protection
						console("[NOTICE] Empty/corrupt location table detected, forcing recovery");
						fseek($this->fp, $lastseek);
						$c = gzdeflate("");
						$this->write(Utils::writeShort(strlen($c)) . $c);
						$this->payloadOffset = ftell($this->fp);
						$this->levelData["extra"] = "";
						//$cnt = pow($this->levelData["width"], 2);
						for($X = 0; $X < 16; ++$X){
							for($Z = 0; $Z < 16; ++$Z){
								$this->write("\x00\xFF"); //Force index recreation
							}
						}
						fseek($this->fp, $this->payloadOffset);
					}else{
						$this->payloadOffset = ftell($this->fp);
					}
					$this->locationTable = [];
					$this->seek($this->payloadOffset);
					for($Z = 0; $Z < 16; ++$Z){
						for($X = 0; $X < 16; ++$X){
							$index = $this->getIndex($X, $Z);
							$this->chunks[$index] = false;
							$this->chunkChange[$index] = false;
							$this->locationTable[$index] = [
								0 => Utils::readShort($this->read(2)), //16 bit flags
							];
						}
					}
					
					foreach(scandir("$worldDir/chunks/") as $f){
						if($f != "." && $f != ".."){
							$xz = explode(".", $f);
							$X = (int) $xz[1];
							$Z = (int) $xz[0];
							ConsoleAPI::info("Converting $X-$Z...");
							$this->loadNCPMF0Chunk($X, $Z);
							$this->unloadChunk($X, $Z);
						}
					}
					
					ConsoleAPI::notice("Modifying level.pmf...");
					$this->saveData(false);
					ConsoleAPI::notice("World converted. Reloading...");
					break;
				case 1:
					ConsoleAPI::notice("Converting the world from NCPMF-{$this->levelData["version"]} to NCPMF-$cv...");
					$worldDir = substr($worldFile, 0, -strlen("/level.pmf"));
					$backupDir = "auto-world-backup-".microtime(true);
					ConsoleAPI::info("Creating backup in $backupDir...");
					copydir($worldDir, $backupDir);
					ConsoleAPI::info("Starting converting...");
					$this->levelData["name"] = $this->read(Utils::readShort($this->read(2), false));
					$this->levelData["seed"] = Utils::readInt($this->read(4));
					$this->levelData["time"] = Utils::readInt($this->read(4));
					$this->levelData["spawnX"] = Utils::readFloat($this->read(4));
					$this->levelData["spawnY"] = Utils::readFloat($this->read(4));
					$this->levelData["spawnZ"] = Utils::readFloat($this->read(4));
					$this->levelData["width"] = ord($this->read(1));
					$this->levelData["height"] = ord($this->read(1));
					$this->levelData["generator"] = $this->read(Utils::readShort($this->read(2), false));
					$lastseek = ftell($this->fp);
					if(($len = $this->read(2)) === false or ($this->levelData["extra"] = @gzinflate($this->read(Utils::readShort($len, false)))) === false){ //Corruption protection
						console("[NOTICE] Empty/corrupt location table detected, forcing recovery");
						fseek($this->fp, $lastseek);
						$c = gzdeflate("");
						$this->write(Utils::writeShort(strlen($c)) . $c);
						$this->payloadOffset = ftell($this->fp);
						$this->levelData["extra"] = "";
						for($Z = 0; $X < 16; ++$Z){
							for($X = 0; $X < 16; ++$X){
								$this->write("\x00\xFF"); //Force index recreation
							}
						}
						fseek($this->fp, $this->payloadOffset);
					}else{
						$this->payloadOffset = ftell($this->fp);
					}
					
					
					foreach(scandir("$worldDir/chunks/") as $f){
						if($f != "." && $f != ".."){
							$xz = explode(".", $f);
							$X = (int) $xz[1];
							$Z = (int) $xz[0];
							ConsoleAPI::info("Converting $X-$Z...");
							$this->loadNCPMF1Chunk($X, $Z);
							$this->unloadChunk($X, $Z);
						}
					}
					
					ConsoleAPI::notice("Modifying level.pmf...");
					$this->saveData(false);
					ConsoleAPI::notice("World converted. Reloading...");
					break;
				case 2:
					ConsoleAPI::notice("Converting the world from NCPMF-{$this->levelData["version"]} to NCPMF-$cv...");
					$worldDir = substr($worldFile, 0, -strlen("/level.pmf"));
					$backupDir = "auto-world-backup-".microtime(true);
					ConsoleAPI::info("Creating backup in $backupDir...");
					copydir($worldDir, $backupDir);
					ConsoleAPI::info("Starting converting...");
					$this->levelData["name"] = $this->read(Utils::readShort($this->read(2), false));
					$this->levelData["seed"] = Utils::readInt($this->read(4));
					$this->levelData["time"] = Utils::readInt($this->read(4));
					$this->levelData["spawnX"] = Utils::readFloat($this->read(4));
					$this->levelData["spawnY"] = Utils::readFloat($this->read(4));
					$this->levelData["spawnZ"] = Utils::readFloat($this->read(4));
					$this->levelData["width"] = ord($this->read(1));
					$this->levelData["height"] = ord($this->read(1));
					$this->levelData["generator"] = $this->read(Utils::readShort($this->read(2), false));
					$lastseek = ftell($this->fp);
					if(($len = $this->read(2)) === false or ($this->levelData["extra"] = @gzinflate($this->read(Utils::readShort($len, false)))) === false){ //Corruption protection
						console("[NOTICE] Empty/corrupt location table detected, forcing recovery");
						fseek($this->fp, $lastseek);
						$c = gzdeflate("");
						$this->write(Utils::writeShort(strlen($c)) . $c);
						$this->payloadOffset = ftell($this->fp);
						$this->levelData["extra"] = "";
						for($Z = 0; $X < 16; ++$Z){
							for($X = 0; $X < 16; ++$X){
								$this->write("\x00\xFF"); //Force index recreation
							}
						}
						fseek($this->fp, $this->payloadOffset);
					}else{
						$this->payloadOffset = ftell($this->fp);
					}
					
					
					foreach(scandir("$worldDir/chunks/") as $f){
						if($f != "." && $f != ".."){
							$xz = explode(".", $f);
							$X = (int) $xz[1];
							$Z = (int) $xz[0];
							ConsoleAPI::info("Converting $X-$Z...");
							$this->loadNCPMF2Chunk($X, $Z);
							$this->unloadChunk($X, $Z);
						}
					}
					
					ConsoleAPI::notice("Modifying level.pmf...");
					$this->saveData(false);
					ConsoleAPI::notice("World converted. Reloading...");
					break;
			}
			
			return false;
		}
		
		
		
		$this->levelData["name"] = $this->read(Utils::readShort($this->read(2), false));
		$this->levelData["seed"] = Utils::readInt($this->read(4));
		$this->levelData["time"] = Utils::readInt($this->read(4));
		$this->levelData["spawnX"] = Utils::readFloat($this->read(4));
		$this->levelData["spawnY"] = Utils::readFloat($this->read(4));
		$this->levelData["spawnZ"] = Utils::readFloat($this->read(4));
		$this->levelData["width"] = ord($this->read(1));
		$this->levelData["height"] = ord($this->read(1));
		$this->levelData["generator"] = $this->read(Utils::readShort($this->read(2), false));
		if(($this->levelData["width"] !== 16 and $this->levelData["width"] !== 32) or $this->levelData["height"] !== 8){
			return false;
		}
		$lastseek = ftell($this->fp);
		if(($len = $this->read(2)) === false or ($this->levelData["extra"] = @gzinflate($this->read(Utils::readShort($len, false)))) === false){ //Corruption protection
			console("[NOTICE] Empty/corrupt location table detected, forcing recovery");
			fseek($this->fp, $lastseek);
			$c = gzdeflate("");
			$this->write(Utils::writeShort(strlen($c)) . $c);
			$this->payloadOffset = ftell($this->fp);
			$this->levelData["extra"] = "";
			//$cnt = pow($this->levelData["width"], 2);
			for($X = 0; $X < 16; ++$X){
				for($Z = 0; $Z < 16; ++$Z){
					$this->write("\x00\xFF"); //Force index recreation
				}
			}
			fseek($this->fp, $this->payloadOffset);
		}else{
			$this->payloadOffset = ftell($this->fp);
		}
		return $this->readLocationTable();
	}

	private function readLocationTable(){
		//$this->locationTable = [];
		//$cnt = pow($this->levelData["width"], 2);
		//$this->seek($this->payloadOffset);
		for($Z = 0; $Z < 16; ++$Z){
			for($X = 0; $X < 16; ++$X){
				$index = $this->getIndex($X, $Z);
				$this->chunks[$index] = false;
				$this->chunkChange[$index] = false;
				//$this->locationTable[$index] = [
				//	0 => Utils::readShort($this->read(2)), //16 bit flags
				//];
			}
		}
		//var_dump($this->locationTable);
		return true;
	}
	
	public function getSeed(): int{
		return $this->levelData["seed"];
	}
	
	public function getData($index){
		if(!isset($this->levelData[$index])){
			return false;
		}
		return ($this->levelData[$index]);
	}

	public function setData($index, $data){
		if(!isset($this->levelData[$index])){
			return false;
		}
		$this->levelData[$index] = $data;
		return true;
	}

	public function close(){
		$chunks = null;
		unset($chunks, $chunkChange, $locationTable);
		parent::close();
	}
	
	public function getBiomeId($x, $z){
		$X = $x >> 4;
		$Z = $z >> 4;
		$index = $this->getIndex($X, $Z);
		if(!isset($this->chunkInfo[$index])){
			return 0;
		}
		$aX = $x & 15;
		$aZ = $z & 15;
		
		return ord($this->chunkInfo[$index][0][$aX + ($aZ << 4)]);
	}
	public function setGrassColorArrayForChunk($x, $z, $biomecols){
		$index = $this->getIndex($x, $z);
		$this->chunkChange[$index][-1] = true;
		$this->chunkInfo[$index][1] = $biomecols;
	}
	public function setBiomeIdArrayForChunk($x, $z, $biomeIds){
		$index = $this->getIndex($x, $z);
		$this->chunkChange[$index][-1] = true;
		$this->chunkInfo[$index][0] = $biomeIds;
	}
	public function setBiomeId($x, $z, $id){
		$X = $x >> 4;
		$Z = $z >> 4;
		$index = $this->getIndex($X, $Z);
		if(!isset($this->chunkInfo[$index])){
			return 0;
		}
		$aX = $x & 15;
		$aZ = $z & 15;
		$this->chunkInfo[$index][0][$aX + ($aZ << 4)] = chr($id);
	}
	public function forceUnloadChunk($X, $Z, $save = true){
		$X = (int) $X;
		$Z = (int) $Z;
		$index = $this->getIndex($X, $Z);
		$this->chunks[$index] = null;
		$this->chunkChange[$index] = null;
		unset($this->chunks[$index], $this->chunkChange[$index]);
	}
	public function unloadChunk($X, $Z, $save = true){
		$X = (int) $X;
		$Z = (int) $Z;
		if(!$this->isChunkLoaded($X, $Z)){
			return false;
		}elseif($save !== false){
			$this->saveChunk($X, $Z);
		}
		$index = $this->getIndex($X, $Z);
		$this->chunks[$index] = null;
		$this->chunkChange[$index] = null;
		unset($this->chunks[$index], $this->chunkChange[$index]);
		return true;
	}

	public function isChunkPopulated($X, $Z){
		return $this->populated[self::getIndex($X, $Z)] ?? false;
	}
	
	public function isChunkLoaded($X, $Z){
		$index = $this->getIndex($X, $Z);
		if(!isset($this->chunks[$index]) or $this->chunks[$index] === false){
			return false;
		}

		return true;
	}

	public function getIndex($X, $Z){
		return ($Z << 16) | ($X < 0 ? (~--$X & 0x7fff) | 0x8000 : $X & 0x7fff);
	}

	public function saveChunk($X, $Z){
		$X = (int) $X;
		$Z = (int) $Z;

		if(!$this->isChunkLoaded($X, $Z)){
			return false;
		}
		$index = $this->getIndex($X, $Z);
		if(!isset($this->chunkChange[$index]) or $this->chunkChange[$index][-1] === false){ //No changes in chunk
			return true;
		}

		$chunk = @gzopen($this->getChunkPath($X, $Z), "wb" . PMF_LEVEL_DEFLATE_LEVEL);
		$bitmap = 0;
		for($Y = 0; $Y < 8; ++$Y){
			$bitmap |= ($this->chunks[$index][$Y] !== false and ((isset($this->chunkChange[$index][$Y]) and $this->chunkChange[$index][$Y] === 0) or !$this->isMiniChunkEmpty($X, $Z, $Y))) << $Y;
		}

		$biomedata = $this->chunkInfo[$index][0];
		$biomecolordata = $this->chunkInfo[$index][1];
		
		gzwrite($chunk, Utils::writeShort($bitmap), 2); //2 bytes locmap(actually it should be only 1)
		gzwrite($chunk, chr($this->populated[$index]), 1); //isPopulated
		gzwrite($chunk, chr(strlen($biomecolordata) == 1024), 1); //has biome color data
		if(strlen($biomedata) < 256){
			$biomedata = str_repeat("\x01", 256);
		}
		if(strlen($biomecolordata) < 1024){
			$biomecolordata = str_repeat("\x00\x85\xb2\x4a", 256);
		}
		
		gzwrite($chunk, $biomedata);
		gzwrite($chunk, $biomecolordata);

		for($Y = 0; $Y < 8; ++$Y){
			if($this->chunks[$index][$Y] !== false and ((isset($this->chunkChange[$index][$Y]) and $this->chunkChange[$index][$Y] === 0) or !$this->isMiniChunkEmpty($X, $Z, $Y))){
				gzwrite($chunk, $this->chunks[$index][$Y]);
			}else{
				$this->chunks[$index][$Y] = false;
			}
			$this->chunkChange[$index][$Y] = 0;
		}
		$this->chunkChange[$index][-1] = false;
		return true;
	}

	protected function isMiniChunkEmpty($X, $Z, $Y){
		$index = $this->getIndex($X, $Z);
		if($this->chunks[$index][$Y] !== false){
			if(substr_count($this->chunks[$index][$Y], "\x00") < 16384){
				return false;
			}
		}
		return true;
	}

	public function getMiniChunk($X, $Z, $Y){
		if($this->loadChunk($X, $Z) === false){
			return str_repeat("\x00", 16384);
		}
		$index = $this->getIndex($X, $Z);
		if(!isset($this->chunks[$index][$Y]) or $this->chunks[$index][$Y] === false){
			return str_repeat("\x00", 16384);
		}
		return $this->chunks[$index][$Y];
	}
	
	public function generateChunk($X, $Z, LevelGenerator $generator){
		$index = $this->getIndex($X, $Z);
		if(isset($this->chunks[$index])){
			return false;
		}
		$this->initCleanChunk($X, $Z);
		$this->fillFullChunk($X, $Z);
		$generator->generateChunk($X, $Z);
		$generator->populateChunk($X, $Z);
	}
	
	public function fillFullChunk($X, $Z){
		for($Y = 0; $Y < 16; ++$Y){
			$this->fillMiniChunk($X, $Z, $Y);
		}
	}
	
	public function loadChunk($X, $Z, $populate = false){
		$index = $this->getIndex($X, $Z);

		if($this->isChunkLoaded($X, $Z)){
			return true;
		}

		$cp = $this->getChunkPath($X, $Z);
		if(!is_file($cp)) return false;
		$chunk = file_get_contents($cp);
		if($chunk === false){
			return false;
		}
		$chunk = zlib_decode($chunk);
		$offset = 0;
		if(strlen($chunk) === 0) return false;
		$info = [0 => Utils::readShort(substr($chunk, $offset, 2))];
		$offset+=2;
		$populated = ord($chunk[$offset]) > 0;
		++$offset;
		$hasbiomecolors = ord($chunk[$offset]) > 0;
		++$offset;
		$this->chunks[$index] = [];
		$this->chunkChange[$index] = [-1 => false];
		$this->chunkInfo[$index][0] = substr($chunk, $offset, 256); //Biome data
		$offset += 256;
		$this->chunkInfo[$index][1] = substr($chunk, $offset, 1024); //Biome colors
		$offset += 1024;
		for($Y = 0; $Y < $this->levelData["height"]; ++$Y){
			$t = 1 << $Y;
			if(($info[0] & $t) === $t){
				// 4096 + 4096 + 4096 + 4096, Id, Meta, BlockLight, Skylight
				if(strlen($this->chunks[$index][$Y] = substr($chunk, $offset, 16384)) < 16384){
					console("[NOTICE] Empty corrupt chunk detected [$X,$Z,:$Y], recovering contents", true, true, 2);
					$this->fillMiniChunk($X, $Z, $Y);
				}
				$offset += 16384;
			}else{
				$this->chunks[$index][$Y] = false;
			}
		}

		$this->setPopulated($X, $Z, $populated);
		if($populate && !$populated){
			$this->level->generator->populateChunk($X, $Z);
		}else if($populated && !$hasbiomecolors){
			$biomecolors = "";
			for($z = 0; $z < 16; ++$z){
				for($x = 0; $x < 16; ++$x){
					$color = GrassColor::getBlendedGrassColor($this->level, $X*16+$x, $Z*16+$z);
					$biomecolors .= $color;
				}
			}
			$this->setGrassColorArrayForChunk($X, $Z, $biomecolors);
		}
		return true;
	}
	
	protected function fillMiniChunk($X, $Z, $Y){
		if($this->isChunkLoaded($X, $Z) === false){
			return false;
		}
		$index = $this->getIndex($X, $Z);
		
		$this->chunks[$index][$Y] = str_repeat("\x00", 16384);
		$this->chunkChange[$index][-1] = true;
		$this->chunkChange[$index][$Y] = 16384;
		return true;
	}
	
	public function initCleanChunk($X, $Z){
		$index = $this->getIndex($X, $Z);
		if(!isset($this->chunks[$index])){
			$this->chunks[$index] = array(
				0 => false,
				1 => false,
				2 => false,
				3 => false,
				4 => false,
				5 => false,
				6 => false,
				7 => false,
			);
			$this->chunkChange[$index] = array(
				-1 => true,
				0 => 16384,
				1 => 16384,
				2 => 16384,
				3 => 16384,
				4 => 16384,
				5 => 16384,
				6 => 16384,
				7 => 16384,
			);
			$this->chunkInfo[$index] = array(
				0 => str_repeat("\x00", 256),
				1 => str_repeat("\x00\x85\xb2\x4a", 256)
			);
			$this->locationTable[$index] = array(0);
			$this->setPopulated($X, $Z, false);
		}
	}
	
	public function setMiniChunk($X, $Z, $Y, $data){
		if($this->isChunkLoaded($X, $Z) === false){
			$this->loadChunk($X, $Z);
		}
		if(strlen($data) !== 16384){
			return false;
		}
		$index = $this->getIndex($X, $Z);
		$this->chunks[$index][$Y] = (string) $data;
		$this->chunkChange[$index][-1] = true;
		$this->chunkChange[$index][$Y] = 16384;
		$this->locationTable[$index][0] |= 1 << $Y;
		return true;
	}
	
	public function getBlockIDsXZ($x, $z){
		$X = $x >> 4;
		$Z = $z >> 4;
		$index = $this->getIndex($X, $Z);
		return $this->chunks[$index] ?? 0;
	}
	
	public function getBlockID($x, $y, $z){
		if($y > 127 || $y < 0) return 0;
		
		$X = $x >> 4;
		$Z = $z >> 4;
		$Y = $y >> 4;
		$index = $this->getIndex($X, $Z);
		if(!isset($this->chunks[$index]) || $this->chunks[$index] === false || ($this->chunks[$index][$Y] === false)){
			return 0;
		}
		$aX = $x & 0xf;
		$aZ = $z & 0xf;
		$aY = $y & 0xf;
		$b = ord($this->chunks[$index][$Y][($aY + ($aX << 6) + ($aZ << 10))]);
		
		return $b;
	}

	public function setBlockID($x, $y, $z, $block){
		if($y > 127 or $y < 0) return false;
		
		$X = $x >> 4;
		$Z = $z >> 4;
		$Y = $y >> 4;
		$block &= 0xFF;
		$index = $this->getIndex($X, $Z);
		if(!isset($this->chunks[$index]) or $this->chunks[$index] === false){
			if($this->loadChunk($X, $Z, false) === false){
				$this->createUnpopulatedChunk($X, $Z);
			}
		}
		if($this->chunks[$index][$Y] === false){
			$this->fillMiniChunk($X, $Z, $Y);
		}
		
		$aX = $x & 0xf;
		$aZ = $z & 0xf;
		$aY = $y & 0xf;
		$this->chunks[$index][$Y][(int) ($aY + ($aX << 6) + ($aZ << 10))] = chr($block);
		if(!isset($this->chunkChange[$index][$Y])){
			$this->chunkChange[$index][$Y] = 1;
		}else{
			++$this->chunkChange[$index][$Y];
		}
		$this->chunkChange[$index][-1] = true;
		return true;
	}

	public function getBlockDamage($x, $y, $z){
		if($y > 127 or $y < 0){
			return 0;
		}
		$X = $x >> 4;
		$Z = $z >> 4;
		$Y = $y >> 4;
		$index = $this->getIndex($X, $Z);
		if(!isset($this->chunks[$index]) || $this->chunks[$index] === false || ($this->chunks[$index][$Y] === false)){
			return 0;
		}
		$aX = $x & 0xf;
		$aZ = $z & 0xf;
		$aY = $y & 0xf;
		//if(is_array($this->chunks) && isset($this->chunks[$index]) && is_array($this->chunks[$index]) && isset($this->chunks[$index][$Y]) && is_string($this->chunks[$index][$Y])){
		$m = ord($this->chunks[$index][$Y][(int) (($aY >> 1) + 16 + ($aX << 6) + ($aZ << 10))]);
		//}else{ //php8 fix
		//	$m = 0;
		//}
		
		if(($y & 1) === 0){
			$m = $m & 0x0F;
		}else{
			$m = $m >> 4;
		}
		return $m;
	}

	public function setBlockDamage($x, $y, $z, $damage){
		if($y > 127 or $y < 0){
			return false;
		}
		$X = $x >> 4;
		$Z = $z >> 4;
		$Y = $y >> 4;
		$damage &= 0x0F;
		$index = $this->getIndex($X, $Z);
		$aX = $x & 0xf;
		$aZ = $z & 0xf;
		$aY = $y & 0xf;
		$mindex = (int) (($aY >> 1) + 16 + ($aX << 6) + ($aZ << 10));
		$old_m = ord($this->chunks[$index][$Y][$mindex]);
		if(($y & 1) === 0){
			$m = ($old_m & 0xF0) | $damage;
		}else{
			$m = ($damage << 4) | ($old_m & 0x0F);
		}

		if($old_m != $m){
			$this->chunks[$index][$Y][$mindex] = chr($m);
			if(!isset($this->chunkChange[$index][$Y])){
				$this->chunkChange[$index][$Y] = 1;
			}else{
				++$this->chunkChange[$index][$Y];
			}
			$this->chunkChange[$index][-1] = true;
			return true;
		}
		return false;
	}

	public function getBlock($x, $y, $z){
		$X = $x >> 4;
		$Z = $z >> 4;
		$Y = $y >> 4;
		if($y >= 128 or $y < 0){
			return [AIR, 0];
		}
		$index = $this->getIndex($X, $Z);
		if(!isset($this->chunks[$index]) or $this->chunks[$index] === false){
			if($this->loadChunk($X, $Z) === false){
				return [AIR, 0];
			}
		}
		if($this->chunks[$index][$Y] === false){
			return [AIR, 0];
		}
		$aX = $x & 0xf;
		$aZ = $z & 0xf;
		$aY = $y & 0xf;
		
		$b = ord($this->chunks[$index][$Y][($aY + ($aX << 6) + ($aZ << 10))]);
		$m = ord($this->chunks[$index][$Y][(($aY >> 1) + 16 + ($aX << 6) + ($aZ << 10))]);

		if(($y & 1) === 0){
			$m = $m & 0x0F;
		}else{
			$m = $m >> 4;
		}
		return [$b, $m];
	}
	
	public function createUnpopulatedChunk($X, $Z){
		$this->initCleanChunk($X, $Z);
		$this->level->generator->generateChunk($X, $Z);
		$this->fakeLoaded[self::getIndex($X, $Z)] = "$X.$Z"; //TODO do not use string
	}
	
	public function setBlock($x, $y, $z, $block, $meta = 0){
		$X = $x >> 4;
		$Z = $z >> 4;
		$Y = $y >> 4;
		$block &= 0xFF;
		$meta &= 0x0F;
		if($Y >= 128 or $y < 0){
			return false;
		}
		
		$index = $this->getIndex($X, $Z);
		if(!isset($this->chunks[$index]) or $this->chunks[$index] === false){
			if($this->loadChunk($X, $Z, false) === false){
				$this->createUnpopulatedChunk($X, $Z);
			}
		}
		if(!isset($this->chunks[$index][$Y]) || $this->chunks[$index][$Y] === false){
			$this->fillMiniChunk($X, $Z, $Y);
		}
		$aX = $x - ($X << 4);
		$aZ = $z - ($Z << 4);
		$aY = $y - ($Y << 4);
		$bindex = (int) ($aY + ($aX << 6) + ($aZ << 10));
		$mindex = (int) (($aY >> 1) + 16 + ($aX << 6) + ($aZ << 10));
		$old_b = ord($this->chunks[$index][$Y][$bindex] ?? '\x00');
		$old_m = ord($this->chunks[$index][$Y][$mindex] ?? '\x00');
		if(($y & 1) === 0){
			$m = ($old_m & 0xF0) | $meta;
		}else{
			$m = ($meta << 4) | ($old_m & 0x0F);
		}

		if($old_b !== $block or $old_m !== $m){
			$this->chunks[$index][$Y][$bindex] = chr($block);
			$this->chunks[$index][$Y][$mindex] = chr($m);
			if(!isset($this->chunkChange[$index][$Y])){
				$this->chunkChange[$index][$Y] = 1;
			}else{
				++$this->chunkChange[$index][$Y];
			}
			$this->chunkChange[$index][-1] = true;
			if($old_b instanceof LiquidBlock){
				$pos = new Position($x, $y, $z, $this->level);
				for($side = 0; $side <= 5; ++$side){
					$b = $pos->getSide($side);
					ServerAPI::request()->api->block->scheduleBlockUpdate($b, ($b instanceof LavaBlock ? 40: 10), BLOCK_UPDATE_NORMAL);
				}
			}
			return true;
		}
		return false;
	}

	public function doSaveRound(){
		foreach($this->chunks as $index => $chunk){ //TODO fix variables($X, $Z are undefined)
			$this->getXZ($index, $X, $Z);
			$this->saveChunk($X, $Z);
		}
	}

}
