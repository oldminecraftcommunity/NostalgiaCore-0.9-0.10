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


abstract class NoiseGenerator{
	protected $perm = array();
	protected $offsetX = 0;
	protected $offsetY = 0;
	protected $offsetZ = 0;
	protected $octaves = 8;
	
	public static function fade($x){
		return $x * $x * $x * ($x * ($x * 6 - 15) + 10);
	}
	
	public static function lerp($x, $y, $z){
		return $y + $x * ($z - $y);
	}
	
	public static function grad($hash, $x, $y, $z){
		$hash &= 15;
		$u = $hash < 8 ? $x : $y;
		$v = $hash < 4 ? $y : (($hash == 12 || $hash == 14) ? $x : $z);
		return (($hash & 1) == 0 ? $u : -$u) + (($hash & 2) == 0 ? $v : -$v);
	}
	
	abstract public function getNoise2D($x, $z);
	
	abstract public function getNoise3D($x, $y, $z);
	
	public function noise2D($x, $z, $frequency, $amplitude, $normalized = false){
		$result = 0;
		$amp = 1;
		$freq = 1;
		$max = 0;
		
		for($i = 0; $i < $this->octaves; ++$i){
			$result += $this->getNoise2D($x * $freq, $z * $freq) * $amp;
			$max += $amp;
			$freq *= $frequency;
			$amp *= $amplitude;
		}
		if($normalized === true){
			$result /= $max;
		}
		
		return $result;
	}
	
	public function noise3D($x, $y, $z, $frequency, $amplitude, $normalized = false){
		$result = 0;
		$amp = 1;
		$freq = 1;
		$max = 0;
		
		for($i = 0; $i < $this->octaves; ++$i){
			$result += $this->getNoise3D($x * $freq, $y * $freq, $z * $freq) * $amp;
			$max += $amp;
			$freq *= $frequency;
			$amp *= $amplitude;
		}
		if($normalized) $result /= $max;
		
		return $result;
	}
	
	public function setOffset($x, $y, $z){
		$this->offsetX = $x;
		$this->offsetY = $y;
		$this->offsetZ = $z;
	}
	
	public function getFastNoise3D($xSize, $ySize, $zSize, $xSamplingRate, $ySamplingRate, $zSamplingRate, $x, $y, $z){
		$noiseArray = array_fill(0, $xSize, array_fill(0, $zSize, []));
		
		for($xx = 0; $xx <= $xSize; $xx += $xSamplingRate){
			for($zz = 0; $zz <= $zSize; $zz += $zSamplingRate){
				for($yy = 0; $yy <= $ySize; $yy += $ySamplingRate){
					$noiseArray[$xx][$zz][$yy] = $this->noise3D(($x + $xx) / 32, ($y + $yy) / 32, ($z + $zz) / 32, 2, 0.25, true);
				}
			}
		}
		
		for($xx = 0; $xx < $xSize; ++$xx){
			$leftX = $xx % $xSamplingRate;
			if($leftX == 0){
				$nnx = $xx + $xSamplingRate;
				$noiseNX = &$noiseArray[$xx];
				$noiseNNX = &$noiseArray[$nnx];
				$dx1 = 1;
				$dx2 = 0;
			}else{
				$dx1 = (($nnx - $xx) / $xSamplingRate);
				$dx2 = ($leftX / $xSamplingRate);
			}
			$noiseXX = &$noiseArray[$xx];
			
			for($zz = 0; $zz < $zSize; ++$zz){
				$leftZ = $zz % $zSamplingRate;
				if($leftZ == 0){
					$nnz = $zz + $zSamplingRate;
					$noiseNXNZ = &$noiseNX[$zz];
					$noiseNXNNZ = &$noiseNX[$nnz];
					$noiseNNXNZ = &$noiseNNX[$zz];
					$noiseNNXNNZ = &$noiseNNX[$nnz];
					$dz1 = 1;
					$dz2 = 0;
				}else{
					$dz1 = ($nnz - $zz) / $zSamplingRate;
					$dz2 = $leftZ / $zSamplingRate;
				}
				
				$dz1dx1 = $dz1*$dx1;
				$dz1dx2 = $dz1*$dx2;
				$dz2dx1 = $dz2*$dx1;
				$dz2dx2 = $dz2*$dx2;
				$noiseXXZZ = &$noiseXX[$zz];
				
				for($yy = 0; $yy < $ySize; ++$yy){
					$leftY = $yy % $ySamplingRate;
					if($leftY == 0){
						$nny = $yy + $ySamplingRate;
						$a = $dz1dx1 * $noiseNXNZ[$yy] + $dz1dx2 * $noiseNNXNZ[$yy];
						$b = $dz1dx1 * $noiseNXNZ[$nny] + $dz1dx2 * $noiseNNXNZ[$nny];
						$c = $dz2dx1 * $noiseNXNNZ[$yy] + $dz2dx2 * $noiseNNXNNZ[$yy];
						$d = $dz2dx1 * $noiseNXNNZ[$nny] + $dz2dx2 * $noiseNNXNNZ[$nny];
					}
					
					if($leftX != 0 || $leftZ != 0 || $leftY != 0){
						$dy1 = (($nny - $yy) / $ySamplingRate);
						$dy2 = ($leftY / $ySamplingRate);
						
						$noiseXXZZ[$yy] = ($dy1 * $a + $dy2 * $b) + ($dy1 * $c + $dy2 * $d);
					}
				}
			}
		}
		return $noiseArray;
	}
}