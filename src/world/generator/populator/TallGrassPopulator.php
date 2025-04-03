<?php

/**
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

class TallGrassPopulator extends Populator{
	private $level;
	private $randomAmount;
	private $baseAmount;
	
	public function setRandomAmount($amount){
		$this->randomAmount = $amount;
	}
	
	public function setBaseAmount($amount){
		$this->baseAmount = $amount;
	}
	
	public function populate(Level $level, $chunkX, $chunkZ, IRandom $random){
		$this->level = $level;
		$amount = $random->nextInt($this->randomAmount + 1+1) + $this->baseAmount;
		for($i = 0; $i < $amount; ++$i){
			$x = ($chunkX << 4) + $random->nextInt(16);
			$z = ($chunkZ << 4) + $random->nextInt(16);
			for($size = 30; $size > 0; --$size){
				$xx = $x - 7 + $random->nextInt(16);
				$zz = $z - 7 + $random->nextInt(16);
				$yy = $this->getHighestWorkableBlock($xx, $zz);
				if($yy !== -1 and $this->canTallGrassStay($this->level->level->getBlockID($xx, $yy, $zz),$this->level->level->getBlockID($xx, $yy - 1, $zz))){
					if(mt_rand(1,4) == 1){
						$this->level->level->setBlock($xx, $yy, $zz, TALL_GRASS, 2);
					}else{
						$this->level->level->setBlock($xx, $yy, $zz, TALL_GRASS, 1);
					}
				}
			}
		}
	}
	
	private function canTallGrassStay($id, $idbottom){
		return $id === AIR and $idbottom === GRASS;
	}
	
	private function getHighestWorkableBlock($x, $z){
		for($y = 128; $y > 0; --$y){
			$b = $this->level->level->getBlockID($x, $y, $z);
			if($b == GRASS){
				return $y + 1;
			}
		}
		return -1;
	}
}