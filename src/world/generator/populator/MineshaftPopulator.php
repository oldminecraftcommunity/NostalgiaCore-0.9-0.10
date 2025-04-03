<?php

class MineshaftPopulator extends Populator{

	private static $DISTANCE = 256;
	private static $VARIATION = 16;
	private static $ODD = 3;
	private static $BASE_Y = 35;
	private static $RAND_Y = 11;

	public function populate(Level $level, $chunkX, $chunkZ, IRandom $random){
		if($random->nextInt(MineshaftPopulator::$ODD+1) === 0){
			//$mineshaft = new Mineshaft($random);
		}
	}

}