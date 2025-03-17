<?php

class TestGenerator implements NewLevelGenerator, ThreadedGenerator{
	/**
	 * @var Level
	 */
	public $level;
	public $dataProvider;
	public function __construct(array $settings = []){
		$this->dataProvider = new class extends ThreadedChunkDataProvider{
			public function getChunkData($X, $Z){
				$rng = new XorShift128Random(TestGenerator::seed($X, $Z));
				$chunk = [];
				for($y = 0; $y < 8; ++$y){
					$mini = "";
					for($z = 0; $z < 16; ++$z){
						for($x = 0; $x < 16; ++$x){
							for($yy = 0; $yy < 16; ++$yy){
								$mini .= $rng->nextFloat() > 0.5 && $y < 4 ? "\x01" : "\x00";
							}
							$mini .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; //meta
							$mini .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; //light/skylight
							$mini .= "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"; //skylight/light
						}
					}
					
					$chunk[$y] = $mini;
				}
				
				usleep(1000000/10); //1/10 sec/chunk
				
				return $chunk;
			}
		};
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see ThreadedGenerator::getDataProvider()
	 */
	public function getDataProvider(){
		return $this->dataProvider;
	}
	
	public function init(Level $level, Random $random){
		$this->level = $level;
	}

	public function getSpawn(){
		return new Vector3(127, 128, 127);
	}

	public function populateChunk($chunkX, $chunkZ){
		ConsoleAPI::debug("Populating $chunkX:$chunkZ");
		$this->level->level->setPopulated($chunkX, $chunkZ, true);
	}

	public function getSettings(){}
	static function seed($X, $Z){
		return ($Z << 16) | ($X < 0 ? (~--$X & 0x7fff) | 0x8000 : $X & 0x7fff);
	}
	
	public function preGenerateChunk($chunkX, $chunkZ){
		if($this->getDataProvider()->isReady($chunkX, $chunkZ)) return true;
		ConsoleAPI::debug("Requesting $chunkX:$chunkZ");
		$this->getDataProvider()->request($chunkX, $chunkZ);
		
		return false;
	}
	public function generateChunk($chunkX, $chunkZ){
		ConsoleAPI::debug("Generation finished $chunkX:$chunkZ");
		$data = $this->getDataProvider()->get($chunkX, $chunkZ);
		
		foreach($data as $k => $v){
			$this->level->setMiniChunk($chunkX, $chunkZ, $k, $v);
		}
		$this->level->level->setBiomeIdArrayForChunk($chunkX, $chunkZ, str_repeat(chr(BIOME_PLAINS), 256));
		$this->level->level->unloadChunk($chunkX, $chunkZ, true);
	}

	public function populateLevel(){}
}

