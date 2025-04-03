<?php

class PondPopulator extends Populator{

	private $waterOdd = 4;
	private $lavaOdd = 4;
	private $lavaSurfaceOdd = 4;

	public function populate(Level $level, $chunkX, $chunkZ, IRandom $random){
		if($random->nextInt($this->waterOdd+1) === 0){
			$v = new Vector3(
				($chunkX << 4) + $random->nextInt(16),
				$random->nextInt(128),
				($chunkZ << 4) + $random->nextInt(16)
			);
			$pond = new PondObject($random, new WaterBlock());
			if($pond->canPlaceObject($level, $v)){
				$pond->placeObject($level, $v);
			}
		}
	}

	public function setWaterOdd($waterOdd){
		$this->waterOdd = $waterOdd;
	}

	public function setLavaOdd($lavaOdd){
		$this->lavaOdd = $lavaOdd;
	}

	public function setLavaSurfaceOdd($lavaSurfaceOdd){
		$this->lavaSurfaceOdd = $lavaSurfaceOdd;
	}
}