<?php

class BiomeSelector
{
	/** @var Biome */
	public $fallback;
	
	/** @var NoiseGeneratorSimplex */
	public $temperature;
	/** @var NoiseGeneratorSimplex */
	public $rainfall;
	
	/** @var Biome[] */
	public static $biomes = [];
	
	public $map = [];
	
	public $lookup;
	
	public function __construct(Random $random, Biome $fallback){
		$this->fallback = $fallback;
		$this->temperature = new NoiseGeneratorSimplex($random, 2);
		$this->rainfall = new NoiseGeneratorSimplex($random, 2);
		$this->generateBiomeLookup();
	}
	
	public static function registerBiome(Biome $b){
		ConsoleAPI::debug("Registered $b");
		self::$biomes[$b->id] = $b;
	}
	/**
	 * @param int $id
	 * @return Biome | false
	 */
	public static function get($id){
		return self::$biomes[$id] ?? false;
	}
	
	public function generateBiomeLookup(){
		for($t = 0; $t < 64; ++$t){
			for($r = 0; $r < 64; ++$r){
				$this->map[$t + ($r << 6)] = $this->getBiomeTR($t / 63, $r / 63);
			}
		}
	}

	public function getBiomeTR($t, $r){
		if($r < 0.25){
			if($t < 0.7){
				return BIOME_OCEAN;
			}else if($t < 0.85){
				return BIOME_RIVER;
			}else{
				return BIOME_SWAMP;
			}
		}else if($r < 0.60){
			if($t < 0.25){
				return BIOME_ICE_PLAINS;
			}else if($t < 0.75){
				return BIOME_PLAINS;
			}else{
				return BIOME_DESERT;
			}
		}else if($r < 0.80){
			if($t < 0.25){
				return BIOME_TAIGA;
			}else if($t < 0.75){
				return BIOME_FOREST;
			}else{
				return BIOME_BIRCH_FOREST;
			}
		}else{
			if($t < 0.25){
				return BIOME_EXTREME_HILLS;
			}else if($t < 0.70){
				return BIOME_EXTREME_HILLS_EDGE;
			}else{
				return BIOME_RIVER;
			}
		}
	}
	
	public function getTemperature($x, $z){ //TODO cache
		return ($this->temperature->noise2D($x * 0.001953125, $z * 0.001953125, 2, 0.0625, true) + 1) / 2;
	}
	
	public function getRainfall($x, $z){
		return ($this->rainfall->noise2D($x * 0.001953125, $z * 0.001953125, 2, 0.0625, true) + 1) / 2;
	}
	
	public function pickBiome($x, $z){
		return self::$biomes[$this->pickBiomeID($x, $z)] ?? $this->fallback;
	}
	public function pickBiomeID($x, $z){
		$temperature = (int) ($this->getTemperature($x, $z) * 63);
		$rainfall = (int) ($this->getRainfall($x, $z) * 63);
		$biomeId = $this->map[$temperature + ($rainfall << 6)] ?? -1;
		return $biomeId;
	}
}

