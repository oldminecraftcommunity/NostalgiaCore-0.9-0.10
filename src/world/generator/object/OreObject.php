<?php

class OreObject{

	public $type;
	/**
	 * @var IRandom
	 */
	private $random;

	public function __construct(IRandom $random, OreType $type){
		$this->type = $type;
		$this->random = $random;
	}

	public function getType(){
		return $this->type;
	}

	public function canPlaceObject(Level $level, $x, $y, $z){
		return ($level->level->getBlockID($x, $y, $z) != AIR);
	}

	public function placeObject(Level $level, $xVect, $yVect, $zVect){
		$clusterSize = (int) $this->type->clusterSize;
		$piDivClusterSize = (M_PI / $clusterSize);
		
		$angle = $this->random->nextFloat() * M_PI;
		$offset = VectorMath::getDirection2D($angle)->multiply($clusterSize)->divide(8);
		$x1 = $xVect + 8 + 0; $offset->x;
		$x2 = $xVect + 8 - 0; $offset->x;
		$z1 = $zVect + 8 + 0; $offset->y;
		$z2 = $zVect + 8 - 0; $offset->y;
		$y1 = $yVect + $this->random->nextInt(4) + 2;
		$y2 = $yVect + $this->random->nextInt(4) + 2;
		$id = $this->type->material->getID();
		$meta = $this->type->material->getMetadata();
		for($count = 0; $count <= $clusterSize; ++$count){
			$seedX = $x1 + ($x2 - $x1) * $count / $clusterSize;
			$seedY = $y1 + ($y2 - $y1) * $count / $clusterSize;
			$seedZ = $z1 + ($z2 - $z1) * $count / $clusterSize;
			$size = ((sin($count * $piDivClusterSize) + 1) * $this->random->nextFloat() * $clusterSize / 16 + 1) / 2;

			$startX = (int) ($seedX - $size);
			$startY = (int) ($seedY - $size);
			$startZ = (int) ($seedZ - $size);
			$endX = (int) ($seedX + $size);
			$endY = (int) ($seedY + $size);
			$endZ = (int) ($seedZ + $size);

			for($x = $startX; $x <= $endX; ++$x){
				$sizeX = ($x + 0.5 - $seedX) / $size;
				$sizeX *= $sizeX;
				if($sizeX < 1){
					for($y = $startY; $y <= $endY; ++$y){
						$sizeY = ($y + 0.5 - $seedY) / $size;
						$sizeY *= $sizeY;
						$sizeXpY = $sizeX + $sizeY;
						if($y > 0 and $sizeXpY < 1){
							for($z = $startZ; $z <= $endZ; ++$z){
								$sizeZ = ($z + 0.5 - $seedZ) / $size;
								$sizeZ *= $sizeZ;
								if(($sizeXpY + $sizeZ) < 1 && $level->level->getBlockID($x, $y, $z) == STONE){
									$level->level->setBlock($x, $y, $z, $id, $meta);
								}
							}
						}
					}
				}
			}
		}
	}

}
