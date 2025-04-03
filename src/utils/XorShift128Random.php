<?php

class XorShift128Random implements IRandom{
	public $x, $y, $z, $w;

	public function __construct($seed = null){
		$this->setSeed($seed === null ? microtime(true) : $seed);
	}

	public function setSeed($seed){
		$this->x = 123456789 ^ $seed;
		$this->y = 362436069 ^ ($seed << 17) | (($seed >> 15) & 0x7fffffff) & 0xffffffff;
		$this->z = 521288629 ^ ($seed << 31) | (($seed >> 1 ) & 0x7fffffff) & 0xffffffff;
		$this->w = 88675123 ^  ($seed << 18) | (($seed >> 14) & 0x7fffffff) & 0xffffffff;
	}
	
	public function gen(){
		$t = ($this->x ^ ($this->x << 11)) & 0xffffffff;
		$this->x = $this->y;
		$this->y = $this->z;
		$this->z = $this->w;
		$this->w = ($this->w ^ (($this->w >> 19) & 0x7fffffff) ^ ($t ^ (($t >> 8) & 0x7fffffff))) & 0xffffffff;
		
		return $this->w << 32 >> 32;
	}
	
	public function nextInt($bound = null){
		if($bound == null) return $this->gen();
		return (($this->gen() & 0x7fffffff) % $bound);
	}

	public function nextFloat(){
		return (($this->gen() & 0x7fffffff) / 0x7fffffff);
	}
}
