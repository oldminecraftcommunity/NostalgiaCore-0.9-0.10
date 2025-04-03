<?php

abstract class Feature
{
	public static $DUNGEON;
	
	public static function init(){
		self::$DUNGEON = new DungeonFeature();
	}
	
	public abstract function place(Level $level, IRandom $rand, $x, $y, $z);
}

