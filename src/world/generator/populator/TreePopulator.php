<?php
class TreePopulator extends Populator{
	public $level;
	public $randomAmount;
	public $baseAmount;
	
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
			
			$y = $this->getHighestWorkableBlock($x, $z);
			if($y === -1){
				continue;
			}
			if($random->nextFloat() > 0.75){
				$meta = SaplingBlock::BIRCH;
			}elseif(($random->nextFloat() < 0.75) and ($random->nextFloat() > 0.25)){
				$meta = SaplingBlock::OAK;
			}else{
				$meta = SaplingBlock::JUNGLE;
			}
			TreeObject::growTree($this->level, new Vector3($x, $y, $z), $random, $meta);
		}
	}
	
	public function getHighestWorkableBlock($x, $z){
		$xc = $x & 0xf;
		$zc = $z & 0xf;
		$column = $this->level->level->getBlockIDsXZ($x, $z);
		if($column == 0) return -1;
		for($y = 127; $y >= 0; --$y){
			$chunkY = $y >> 4;
			if($column[$chunkY] === false){
				$y -= 15;
				continue;
			}
			$b = ord($column[$chunkY][(($y & 0xf) + ($xc << 6) + ($zc << 10))]);
			if($b == DIRT || $b == GRASS){
				return $y+1;
			}
		}
		return -1;
	}
}