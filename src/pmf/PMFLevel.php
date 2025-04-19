<?php

define("PMF_CURRENT_LEVEL_VERSION", 0x04);

class PMFLevel extends PMF{

	public $isLoaded = true;
	/**
	 * @var $level Level
	 */
	public $level;
	public $levelData = [];
	private $payloadOffset = 0;
	
	private $chunkChange = [];
	
	public $blockIds = [];
	public $blockMetas = [];
	public $blockLight = [];
	public $skyLight = [];
	
	public $biomeColorInfo = [];
	public $biomeInfo = [];
	
	public $populated = [];
	public $fakeLoaded = [];
	
	public $justConverted = false;
	
	
	/**
	 * Used only for coverting worlds from older pmf
	 * @var array
	 */
	private $locationTable = [];
	/**
	 * Used only for coverting worlds from older pmf
	 * @var array
	 */
	private $chunks = [];
	/**
	 * @deprecated replaced by biomeInfo[index] and biomeColorInfo[index]
	 */
	public $chunkInfo = [];
	
	public function __construct($file, $blank = false){
		if(is_array($blank)){
			$this->create($file, 0);
			$this->levelData = $blank;
			$this->createBlank();
			$this->isLoaded = true;
		}else{
			if($this->load($file) !== false){
				$this->parseInfo();
				if($this->parseLevel($file) === false){
					$this->isLoaded = false;
				}else{
					$this->isLoaded = true;
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
				$this->initCleanChunk($X, $Z);
				
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

	public function saveData($locationTable = false){
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
		$this->chunkChange[$index] = false;
		$this->biomeInfo[$index] = str_repeat(ord(BIOME_PLAINS), 256);
		$this->biomeColorInfo[$index] = ""; //biome color data, passing strlen==0 to force regenerate on next normal chunk load
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
					$this->chunkChange[$index] = true;

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
		$this->chunkChange[$index] = false;
		$this->biomeInfo[$index] = substr($chunk, $offset, 256); //Biome data
		$this->biomeColorInfo[$index] = ""; //biome color data, passing strlen==0 to force regenerate on next normal chunk load
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
		
		$this->chunkChange[$index] = true; //force save
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
		$this->biomeInfo[$index] = substr($chunk, $offset, 256); //Biome data
		$this->biomeColorInfo[$index] = ""; //biome color data, passing strlen==0 to force regenerate on next normal chunk load

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

		$this->chunkChange[$index] = true; //force save
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
					$this->justConverted = true;
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
					$this->justConverted = true;
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
					$this->justConverted = true;
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
		for($Z = 0; $Z < 16; ++$Z){
			for($X = 0; $X < 16; ++$X){
				$index = $this->getIndex($X, $Z);
				$this->chunks[$index] = false;
				$this->chunkChange[$index] = false;
			}
		}
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
		parent::close();
	}
	
	public function getBiomeId($x, $z){
		$X = $x >> 4;
		$Z = $z >> 4;
		$aX = $x & 15;
		$aZ = $z & 15;
		$index = $this->getIndex($X, $Z);
		
		return ord($this->biomeInfo[$index][$aX + ($aZ << 4)] ?? "\x00");
	}
	public function setGrassColorArrayForChunk($x, $z, $biomecols){
		$index = $this->getIndex($x, $z);
		$this->chunkChange[$index] = true;
		$this->biomeColorInfo[$index] = $biomecols;
	}
	public function setBiomeIdArrayForChunk($x, $z, $biomeIds){
		$index = $this->getIndex($x, $z);
		$this->chunkChange[$index] = true;
		$this->biomeInfo[$index] = $biomeIds;
	}
	public function setBiomeId($x, $z, $id){
		$X = $x >> 4;
		$Z = $z >> 4;
		$index = $this->getIndex($X, $Z);
		if(!isset($this->biomeInfo[$index])) return false;
		$aX = $x & 15;
		$aZ = $z & 15;
		$this->biomeInfo[$index][$aX + ($aZ << 4)] = chr($id);
	}
	public function forceUnloadChunk($X, $Z, $save = true){
		$X = (int) $X;
		$Z = (int) $Z;
		$index = $this->getIndex($X, $Z);
		unset($this->chunks[$index], $this->blockIds[$index], $this->blockMetas[$index], $this->blockLight[$index], $this->skyLight[$index], $this->chunkChange[$index]);
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
		unset($this->chunks[$index], $this->blockIds[$index], $this->blockMetas[$index], $this->blockLight[$index], $this->skyLight[$index], $this->chunkChange[$index]);
		return true;
	}

	public function isChunkPopulated($X, $Z){
		return $this->populated[self::getIndex($X, $Z)] ?? false;
	}
	
	public function isChunkLoaded($X, $Z){
		$index = $this->getIndex($X, $Z);
		return isset($this->blockIds[$index]);
	}

	public static function getIndex($X, $Z){
		return "$X.$Z";
	}
	public function getXZ($index, &$X = null, &$Z = null){
		$xz = explode(".", $index);
		$Z = $xz[1];
		$X = $xz[0];
		return array($X, $Z);
	}
	
	public function saveChunk($X, $Z){
		$X = (int) $X;
		$Z = (int) $Z;

		if(!$this->isChunkLoaded($X, $Z)){
			return false;
		}
		$index = $this->getIndex($X, $Z);
		if(!($this->chunkChange[$index] ?? false)){ //No changes in chunk
			return true;
		}
		$chunk = @gzopen($this->getChunkPath($X, $Z), "wb" . PMF_LEVEL_DEFLATE_LEVEL);
		$bitmap = 0b11111111;

		$biomedata = $this->biomeInfo[$index];
		$biomecolordata = $this->biomeColorInfo[$index];
		
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
		gzwrite($chunk, $this->blockIds[$index]);
		gzwrite($chunk, $this->blockMetas[$index]);
		gzwrite($chunk, $this->blockLight[$index]);
		gzwrite($chunk, $this->skyLight[$index]);
		
		$this->chunkChange[$index] = false;
		return true;
	}
	
	public function generateChunk($X, $Z, LevelGenerator $generator){
		$index = $this->getIndex($X, $Z);
		if(isset($this->blockIds[$index])){
			return false;
		}
		$this->initCleanChunk($X, $Z);
		$this->fillFullChunk($X, $Z);
		$generator->generateChunk($X, $Z);
		$generator->populateChunk($X, $Z);
	}
	
	public function fillFullChunk($X, $Z){
		for($Y = 0; $Y < 8; ++$Y){
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
		if(strlen($chunk) == 0) return false;
		$info = [0 => Utils::readShort(substr($chunk, $offset, 2))];
		$offset+=2;
		$populated = ord($chunk[$offset]) > 0;
		++$offset;
		$hasbiomecolors = ord($chunk[$offset]) > 0;
		++$offset;
		
		$this->chunkChange[$index] = false;
		$this->biomeInfo[$index] = substr($chunk, $offset, 256); //Biome data
		$offset += 256;
		$this->biomeColorInfo[$index] = substr($chunk, $offset, 1024); //Biome colors
		$offset += 1024;
		
		$this->blockIds[$index] = substr($chunk, $offset, 16*16*128);
		$offset += 16*16*128;
		$this->blockMetas[$index] = substr($chunk, $offset, 16*16*128);
		$offset += 16*16*128;
		$this->blockLight[$index] = substr($chunk, $offset, 16*16*128);
		$offset += 16*16*128;
		$this->skyLight[$index] = substr($chunk, $offset, 16*16*128);
		$offset += 16*16*128;
		

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
		//TODO get rid of this - cant, used by world converter
		if($this->isChunkLoaded($X, $Z) === false){
			return false;
		}
		$index = $this->getIndex($X, $Z);
		
		$this->chunks[$index][$Y] = str_repeat("\x00", 16384);
		$this->chunkChange[$index] = true;
		return true;
	}
	
	public function initCleanChunk($X, $Z){
		$index = $this->getIndex($X, $Z);
		if(!isset($this->blockIds[$index])){
			$this->blockIds[$index] = str_repeat("\x00", 16*16*128);
			$this->blockMetas[$index] = str_repeat("\x00", 16*16*128);
			$this->blockLight[$index] = str_repeat("\x00", 16*16*128);
			$this->skyLight[$index] = str_repeat("\x00", 16*16*128);
			
			$this->chunkChange[$index] = true;
			$this->biomeInfo[$index] = str_repeat("\x00", 256);
			$this->biomeColorInfo[$index] = str_repeat("\x00\x85\xb2\x4a", 256);
			
			$this->setPopulated($X, $Z, false);
		}
	}
	
	public function setChunkData($X, $Z, $ids = false, $metas = false, $blocklight = false, $skylight = false){
		$ind = $this->getIndex($X, $Z);
		if($ids != false) $this->blockIds[$ind] = $ids;
		if($metas != false) $this->blockMetas[$ind] = $metas;
		if($blocklight != false) $this->blockLight[$ind] = $blocklight;
		if($skylight != false) $this->skyLight[$ind] = $skylight;
		
	}
	
	
	public function setMiniChunk($X, $Z, $Y, $data){
		//TODO get rid of this
		if($this->isChunkLoaded($X, $Z) === false){
			$this->loadChunk($X, $Z);
		}
		if(strlen($data) !== 16384){
			return false;
		}
		$index = $this->getIndex($X, $Z);
		$this->chunks[$index][$Y] = (string) $data;
		$this->chunkChange[$index] = true;
		return true;
	}
	
	public function getBlockIDsXZ($x, $z){
		$X = $x >> 4;
		$Z = $z >> 4;
		$index = $this->getIndex($X, $Z);
		return $this->blockIds[$index] ?? 0;
	}
	
	public function getBlockID($x, $y, $z){
		$x = (int) $x;
		$y = (int) $y;
		$z = (int) $z;
		if($y > 127 || $y < 0) return 0;
		
		$X = $x >> 4;
		$Z = $z >> 4;
		
		$index = $this->getIndex($X, $Z);
		if(!isset($this->blockIds[$index])) return 0; //TODO getBlock will try to load chunk
		
		$cx = $x & 0xf;
		$cz = $z & 0xf;
		$b = ord($this->blockIds[$index][($cx << 11) | ($cz << 7) | $y]);
		
		return $b;
	}
	
	public function setBlockID($x, $y, $z, $block){
		$x = (int) $x;
		$y = (int) $y;
		$z = (int) $z;
		$block &= 0xFF;
		if($y > 127 || $y < 0) return false;
		
		$X = $x >> 4;
		$Z = $z >> 4;
		
		$index = $this->getIndex($X, $Z);
		if(!isset($this->blockIds[$index])){
			if($this->loadChunk($X, $Z, false) === false){
				$this->createUnpopulatedChunk($X, $Z);
			}
		}
		
		$cx = $x & 0xf;
		$cz = $z & 0xf;
		$this->blockIds[$index][($cx << 11) | ($cz << 7) | $y] = chr($block);
		$this->chunkChange[$index] = true;
		return true;
	}
	
	public function getBlockDamage($x, $y, $z){
		$x = (int) $x;
		$y = (int) $y;
		$z = (int) $z;
		
		if($y > 127 or $y < 0) return 0;
		$X = $x >> 4;
		$Z = $z >> 4;
		$index = $this->getIndex($X, $Z);
		if(!isset($this->blockMetas[$index])) return 0; //TODO getBlock will try to load chunk
		
		$cx = $x & 0xf;
		$cz = $z & 0xf;
		$m = ord($this->blockMetas[$index][($cx << 11) | ($cz << 7) | $y]);
		return $m;
	}

	public function setBlockDamage($x, $y, $z, $damage){
		$x = (int) $x;
		$y = (int) $y;
		$z = (int) $z;
		$damage &= 0x0F;
		if($y > 127 || $y < 0) return false;
		
		$X = $x >> 4;
		$Z = $z >> 4;
		$index = $this->getIndex($X, $Z);
		if(!isset($this->blockMetas[$index])) return 0;
		
		$cx = $x & 0xf;
		$cz = $z & 0xf;
		$old_m = $this->blockMetas[$index][($cx << 11) | ($cz << 7) | $y];
		$m = chr($damage);
		
		if($old_m != $m){
			$this->blockMetas[$index][($cx << 11) | ($cz << 7) | $y] = $m;
			$this->chunkChange[$index] = true;
			return true;
		}
		return false;
	}

	public function getBlock($x, $y, $z){
		$x = (int) $x;
		$y = (int) $y;
		$z = (int) $z;
		
		$X = $x >> 4;
		$Z = $z >> 4;
		if($y >= 128 or $y < 0) return [AIR, 0];
		$index = $this->getIndex($X, $Z);
		if(!isset($this->blockIds[$index])){
			if($this->loadChunk($X, $Z) === false) return [AIR, 0];
		}
		
		$cx = $x & 0xf;
		$cz = $z & 0xf;
		
		$b = ord($this->blockIds[$index][($cx << 11) | ($cz << 7) | $y]);
		$m = ord($this->blockMetas[$index][($cx << 11) | ($cz << 7) | $y]);
		
		return [$b, $m];
	}
	
	public function createUnpopulatedChunk($X, $Z){
		$this->initCleanChunk($X, $Z);
		$this->level->generator->generateChunk($X, $Z);
		$this->fakeLoaded[self::getIndex($X, $Z)] = "$X.$Z"; //TODO do not use string
	}
	
	public function setBlock($x, $y, $z, $block, $meta = 0){
		$x = (int) $x;
		$y = (int) $y;
		$z = (int) $z;
		$block &= 0xFF;
		$meta &= 0x0F;
		if($y >= 128 || $y < 0) return false;
		
		$X = $x >> 4;
		$Z = $z >> 4;
		
		$index = $this->getIndex($X, $Z);
		if(!isset($this->blockIds[$index])){
			if($this->loadChunk($X, $Z, false) === false){
				$this->createUnpopulatedChunk($X, $Z);
			}
		}
		
		$cx = $x & 0xf;
		$cz = $z & 0xf;
		$bindex = ($cx << 11) | ($cz << 7) | $y;
		
		$old_b = ord($this->blockIds[$index][$bindex] ?? '\x00');
		$old_m = ord($this->blockMetas[$index][$bindex] ?? '\x00');

		if($old_b !== $block or $old_m !== $meta){
			$this->blockIds[$index][$bindex] = chr($block);
			$this->blockMetas[$index][$bindex] = chr($meta);
			$this->chunkChange[$index] = true;
			return true;
		}
		return false;
	}

	public function doSaveRound(){
		foreach($this->blockIds as $index => $_){
			$this->getXZ($index, $X, $Z);
			$this->saveChunk($X, $Z);
		}
	}

}
