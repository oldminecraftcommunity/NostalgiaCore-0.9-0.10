<?php

class ThreadedBiomeSelector
{
	public static $biomes;
	public static $temp, $rain;
	public static $map;
	
	public static function saveState(BiomeSelector $old){
		self::$temp = $old->temperature;
		self::$rain = $old->rainfall;
		self::$map = [];
		foreach($old->map as $k => $v){
			self::$map[$k] = $v;
		}
	}
	
	public static function getTemperature($x, $z){
		return (self::$temp->noise2D($x * 0.001953125, $z * 0.001953125, 2, 0.0625, true) + 1) / 2;
	}
	
	public static function getRainfall($x, $z){
		return (self::$temp->noise2D($x * 0.001953125, $z * 0.001953125, 2, 0.0625, true) + 1) / 2;
	}
	
	public static function pickBiomeID($x, $z){
		$temperature = (int) (self::getTemperature($x, $z) * 63);
		$rainfall = (int) (self::getRainfall($x, $z) * 63);
		$biomeId = self::$map[$temperature + ($rainfall << 6)] ?? -1;
		return $biomeId;
	}
}

