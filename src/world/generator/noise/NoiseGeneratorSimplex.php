<?php

/***REM_START***/
require_once("NoiseGenerator.php");
require_once("NoiseGeneratorPerlin.php");
/***REM_END***/

/**
 * Generates simplex-based noise.
 * <p>
 * This is a modified version of the freely published version in the paper by
 * Stefan Gustavson at
 * <a href="http://staffwww.itn.liu.se/~stegu/simplexnoise/simplexnoise.pdf">
 * http://staffwww.itn.liu.se/~stegu/simplexnoise/simplexnoise.pdf</a>
 */
class NoiseGeneratorSimplex extends NoiseGeneratorPerlin{
	const SQRT_3 = 1.7320508075688772;
	const SQRT_5 = 2.23606797749979;
	const F2 = 0.5 * (self::SQRT_3 - 1);
	const G2 = (3 - self::SQRT_3) / 6;
	const G22 = self::G2 * 2 - 1;
	const F3 = 1/3;
	const G3 = 1/6;
	const F4 = (self::SQRT_5 - 1.0) / 4.0;
	const G4 = (5.0 - self::SQRT_5) / 20.0;
	const G42 = self::G4 * 2.0;
	const G43 = self::G4 * 3.0;
	const G44 = self::G4 * 4.0 - 1;
	protected $offsetW;
	
	public function __construct(Random $random, $octaves){
		parent::__construct($random, $octaves);
		$this->offsetW = $random->nextFloat() * 256;
	}
	
	protected static function dot2D($g, $x, $y){
		return $g[0] * $x + $g[1] * $y;
	}
	
	protected static function dot3D($g, $x, $y, $z){
		return $g[0] * $x + $g[1] * $y + $g[2] * $z;
	}
	
	protected static function dot4D($g, $x, $y, $z, $w){
		return $g[0] * $x + $g[1] * $y + $g[2] * $z + $g[3] * $w;
	}
	
	public function getNoise3D($x, $y, $z){
		$x += $this->offsetX;
		$y += $this->offsetY;
		$z += $this->offsetZ;
		
		// Skew the input space to determine which simplex cell we're in
		$s = ($x + $y + $z) * self::F3; // Very nice and simple skew factor for 3D
		$i = floor($x + $s);
		$j = floor($y + $s);
		$k = floor($z + $s);
		$t = ($i + $j + $k) * self::G3;
		$X0 = $i - $t; // Unskew the cell origin back to (x,y,z) space
		$Y0 = $j - $t;
		$Z0 = $k - $t;
		$x0 = $x - $X0; // The x,y,z distances from the cell origin
		$y0 = $y - $Y0;
		$z0 = $z - $Z0;
		
		// For the 3D case, the simplex shape is a slightly irregular tetrahedron.
		
		// Determine which simplex we are in.
		if($x0 >= $y0){
			if($y0 >= $z0){
				$i1 = 1;
				$j1 = 0;
				$k1 = 0;
				$i2 = 1;
				$j2 = 1;
				$k2 = 0;
			}// X Y Z order
			elseif($x0 >= $z0){
				$i1 = 1;
				$j1 = 0;
				$k1 = 0;
				$i2 = 1;
				$j2 = 0;
				$k2 = 1;
			}// X Z Y order
			else{
				$i1 = 0;
				$j1 = 0;
				$k1 = 1;
				$i2 = 1;
				$j2 = 0;
				$k2 = 1;
			}// Z X Y order
		}else{ // x0<y0
			if($y0 < $z0){
				$i1 = 0;
				$j1 = 0;
				$k1 = 1;
				$i2 = 0;
				$j2 = 1;
				$k2 = 1;
			}// Z Y X order
			elseif($x0 < $z0){
				$i1 = 0;
				$j1 = 1;
				$k1 = 0;
				$i2 = 0;
				$j2 = 1;
				$k2 = 1;
			}// Y Z X order
			else{
				$i1 = 0;
				$j1 = 1;
				$k1 = 0;
				$i2 = 1;
				$j2 = 1;
				$k2 = 0;
			}// Y X Z order
		}
		
		// A step of (1,0,0) in (i,j,k) means a step of (1-c,-c,-c) in (x,y,z),
		// a step of (0,1,0) in (i,j,k) means a step of (-c,1-c,-c) in (x,y,z), and
		// a step of (0,0,1) in (i,j,k) means a step of (-c,-c,1-c) in (x,y,z), where
		// c = 1/6.
		$x1 = $x0 - $i1 + self::G3; // Offsets for second corner in (x,y,z) coords
		$y1 = $y0 - $j1 + self::G3;
		$z1 = $z0 - $k1 + self::G3;
		$x2 = $x0 - $i2 + 2.0 * self::G3; // Offsets for third corner in (x,y,z) coords
		$y2 = $y0 - $j2 + 2.0 * self::G3;
		$z2 = $z0 - $k2 + 2.0 * self::G3;
		$x3 = $x0 - 1.0 + 3.0 * self::G3; // Offsets for last corner in (x,y,z) coords
		$y3 = $y0 - 1.0 + 3.0 * self::G3;
		$z3 = $z0 - 1.0 + 3.0 * self::G3;
		
		// Work out the hashed gradient indices of the four simplex corners
		$ii = $i & 255;
		$jj = $j & 255;
		$kk = $k & 255;
		$gi0 = $this->perm[$ii + $this->perm[$jj + $this->perm[$kk]]] % 12;
		$gi1 = $this->perm[$ii + $i1 + $this->perm[$jj + $j1 + $this->perm[$kk + $k1]]] % 12;
		$gi2 = $this->perm[$ii + $i2 + $this->perm[$jj + $j2 + $this->perm[$kk + $k2]]] % 12;
		$gi3 = $this->perm[$ii + 1 + $this->perm[$jj + 1 + $this->perm[$kk + 1]]] % 12;
		
		// Calculate the contribution from the four corners
		$t0 = 0.6 - $x0 * $x0 - $y0 * $y0 - $z0 * $z0;
		if($t0 < 0){
			$n0 = 0.0;
		}else{
			$t0 *= $t0;
			$n0 = $t0 * $t0 * self::dot3D(self::grad3[$gi0], $x0, $y0, $z0);
		}
		
		$t1 = 0.6 - $x1 * $x1 - $y1 * $y1 - $z1 * $z1;
		if($t1 < 0){
			$n1 = 0.0;
		}else{
			$t1 *= $t1;
			$n1 = $t1 * $t1 * self::dot3D(self::grad3[$gi1], $x1, $y1, $z1);
		}
		
		$t2 = 0.6 - $x2 * $x2 - $y2 * $y2 - $z2 * $z2;
		if($t2 < 0){
			$n2 = 0.0;
		}else{
			$t2 *= $t2;
			$n2 = $t2 * $t2 * self::dot3D(self::grad3[$gi2], $x2, $y2, $z2);
		}
		
		$t3 = 0.6 - $x3 * $x3 - $y3 * $y3 - $z3 * $z3;
		if($t3 < 0){
			$n3 = 0.0;
		}else{
			$t3 *= $t3;
			$n3 = $t3 * $t3 * self::dot3D(self::grad3[$gi3], $x3, $y3, $z3);
		}
		
		// Add contributions from each corner to get the noise value.
		// The result is scaled to stay just inside [-1,1]
		return 32.0 * ($n0 + $n1 + $n2 + $n3);
	}
	
	public function getNoise2D($x, $y){
		$x += $this->offsetX;
		$y += $this->offsetY;
		
		// Skew the input space to determine which simplex cell we're in
		$s = ($x + $y) * self::F2; // Hairy factor for 2D
		$i = floor($x + $s);
		$j = floor($y + $s);
		$t = ($i + $j) * self::G2;
		$X0 = $i - $t; // Unskew the cell origin back to (x,y) space
		$Y0 = $j - $t;
		$x0 = $x - $X0; // The x,y distances from the cell origin
		$y0 = $y - $Y0;
		
		// For the 2D case, the simplex shape is an equilateral triangle.
		
		// Determine which simplex we are in.
		if($x0 > $y0){
			$i1 = 1;
			$j1 = 0;
		}// lower triangle, XY order: (0,0)->(1,0)->(1,1)
		else{
			$i1 = 0;
			$j1 = 1;
		}// upper triangle, YX order: (0,0)->(0,1)->(1,1)
		
		// A step of (1,0) in (i,j) means a step of (1-c,-c) in (x,y), and
		// a step of (0,1) in (i,j) means a step of (-c,1-c) in (x,y), where
		// c = (3-sqrt(3))/6
		
		$x1 = $x0 - $i1 + self::G2; // Offsets for middle corner in (x,y) unskewed coords
		$y1 = $y0 - $j1 + self::G2;
		$x2 = $x0 + self::G22; // Offsets for last corner in (x,y) unskewed coords
		$y2 = $y0 + self::G22;
		
		// Work out the hashed gradient indices of the three simplex corners
		$ii = $i & 255;
		$jj = $j & 255;
		$gi0 = $this->perm[$ii + $this->perm[$jj]] % 12;
		$gi1 = $this->perm[$ii + $i1 + $this->perm[$jj + $j1]] % 12;
		$gi2 = $this->perm[$ii + 1 + $this->perm[$jj + 1]] % 12;
		
		// Calculate the contribution from the three corners
		$t0 = 0.5 - $x0 * $x0 - $y0 * $y0;
		if($t0 < 0){
			$n0 = 0.0;
		}else{
			$t0 *= $t0;
			$n0 = $t0 * $t0 * self::dot2D(self::grad3[$gi0], $x0, $y0); // (x,y) of grad3 used for 2D gradient
		}
		
		$t1 = 0.5 - $x1 * $x1 - $y1 * $y1;
		if($t1 < 0){
			$n1 = 0.0;
		}else{
			$t1 *= $t1;
			$n1 = $t1 * $t1 * self::dot2D(self::grad3[$gi1], $x1, $y1);
		}
		
		$t2 = 0.5 - $x2 * $x2 - $y2 * $y2;
		if($t2 < 0){
			$n2 = 0.0;
		}else{
			$t2 *= $t2;
			$n2 = $t2 * $t2 * self::dot2D(self::grad3[$gi2], $x2, $y2);
		}
		
		// Add contributions from each corner to get the noise value.
		// The result is scaled to return values in the interval [-1,1].
		return 70.0 * ($n0 + $n1 + $n2);
	}
}