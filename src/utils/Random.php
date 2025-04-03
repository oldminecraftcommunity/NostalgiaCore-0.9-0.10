<?php

//Unsecure, not used for "Real Randomness"
class Random implements IRandom{

	public $x, $y, $z, $w;
	public $i, $j, $state;
	public $haveNextNextGaussian = false;
	public $nextNextGaussian = 0;

	public function __construct($seed = false){
		$this->setSeed($seed);
	}

	public function setSeed($seed = false){
		$seed = $seed !== false ? Utils::writeInt((int) $seed) : Utils::getRandomBytes(4, false);
		$state = [];
		for($i = 0; $i < 256; ++$i){
			$state[] = $i;
		}
		for($i = $j = 0; $i < 256; ++$i){
			$j = ($j + ord($seed[$i & 0x03]) + $state[$i]) & 0xFF;
			$state[$i] ^= $state[$j];
			$state[$j] ^= $state[$i];
			$state[$i] ^= $state[$j];
		}
		$this->state = $state;
		$this->i = $this->j = 0;
	}

	public function nextGaussian(){
		if($this->haveNextNextGaussian){
			$this->haveNextNextGaussian = false;
			return $this->nextNextGaussian;
		}else{
			$v1 = $v2 = $s = null;
			do{
				$v1 = 2 * $this->nextFloat() - 1;   // between -1.0 and 1.0
				$v2 = 2 * $this->nextFloat() - 1;   // between -1.0 and 1.0
				$s = $v1 * $v1 + $v2 * $v2;
			}while($s >= 1 || $s == 0);
			$multiplier = sqrt(-2 * log($s) / $s);
			$this->nextNextGaussian = $v2 * $multiplier;
			$this->haveNextNextGaussian = true;
			return $v1 * $multiplier;
		}
	}

	public function nextFloat(){
		return $this->nextInt() / 0x7FFFFFFF;
	}
	
	public function nextLong($n = -1){
		if($n > 0){
			return Utils::readLong($this->nextBytes(8)) & 0x7FFFFFFF % $n;
		}
		return Utils::readLong($this->nextBytes(8)) & 0x7FFFFFFF;
	}
	
	public function nextInt($n = null){
		if($n != null){
			return Utils::readInt($this->nextBytes(4)) & 0x7FFFFFFF % $n;
		}
		return Utils::readInt($this->nextBytes(4)) & 0x7FFFFFFF;
	}

	public function nextBytes($byteCount){
		$bytes = "";
		for($i = 0; $i < $byteCount; ++$i){
			$this->i = ($this->i + 1) & 0xFF;
			$this->j = ($this->j + $this->state[$this->i]) & 0xFF;
			$this->state[$this->i] ^= $this->state[$this->j];
			$this->state[$this->j] ^= $this->state[$this->i];
			$this->state[$this->i] ^= $this->state[$this->j];
			$bytes .= chr($this->state[($this->state[$this->i] + $this->state[$this->j]) & 0xFF]);
		}
		return $bytes;
	}

	public function nextSignedFloat(){
		return $this->nextSignedInt() / 0x7FFFFFFF;
	}

	public function nextSignedInt(){
		return Utils::readInt($this->nextBytes(4));
	}

	public function nextBoolean(){
		return ($this->nextBytes(1) & 0x01) == 0;
	}

	public function nextRange($start = 0, $end = PHP_INT_MAX){
		return $start + ($this->nextInt() % ($end + 1 - $start));
	}

}