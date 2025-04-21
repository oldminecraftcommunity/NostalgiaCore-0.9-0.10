<?php
/**
 * Small class to hopefully optimize entities a bit
 */
class StaticBlock
{
	const DEFAULT_SLIPPERINESS = 0.6;
	const DEFAULT_HARDNESS = 0;
	private static $NULL_BOUNDS;
	public static $isSolid = [];
	public static $isTransparent = [];
	public static $isFlowable = [];
	public static $isReplaceable = [];
	public static $isPlaceable = [];
	public static $hasPhysics = [];
	public static $isLiquid = [];
	public static $isFullBlock = [];
	
	public static $hardness = [];
	public static $slipperiness = [];
	public static $boundingBoxes = [];
	
	//exported from 0.9.5
	public static $lightEmission = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 15, 15, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 14, 15, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 13, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 9, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 15, 0, 15, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 13, 0, 0, 0, 0, 0, 0, 0, 0, 0];
	public static $lightBlock = [0, 255, 255, 255, 255, 255, 0, 255, 3, 3, 255, 255, 255, 255, 255, 255, 255, 255, 1, 255, 0, 255, 255, 0, 255, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 255, 0, 0, 0, 0, 0, 255, 255, 255, 0, 255, 255, 255, 255, 255, 0, 0, 0, 0, 0, 0, 255, 255, 255, 0, 0, 255, 255, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 255, 255, 0, 0, 0, 0, 3, 255, 0, 255, 0, 0, 0, 255, 255, 0, 255, 0, 255, 0, 0, 0, 0, 0, 255, 255, 255, 255, 0, 0, 255, 0, 0, 0, 0, 0, 0, 255, 0, 255, 0, 0, 0, 0, 0, 0, 0, 0, 255, 0, 0, 0, 0, 0, 0, 0, 255, 0, 0, 0, 255, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 255, 0, 255, 0, 255, 0, 1, 255, 0, 0, 0, 0, 0, 0, 0, 255, 0, 255, 255, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 255, 0, 255, 255, 255, 255, 255, 0, 0, 0, 0, 0, 255];
	
	public static function init(){
		self::$NULL_BOUNDS = new AxisAlignedBB(0, 0, 0, 0, 0, 0);
		foreach(Block::$class as $nonstaticname){
			/**@var Block $b*/
			$b = new $nonstaticname();
			
			self::$isSolid[$b->getID()] = $b->isSolid;
			self::$isTransparent[$b->getID()] = $b->isTransparent;
			self::$isFlowable[$b->getID()] = $b->isFlowable;
			self::$isReplaceable[$b->getID()] = $b->isReplaceable;
			self::$isPlaceable[$b->getID()] = $b->isPlaceable;
			self::$hasPhysics[$b->getID()] = $b->hasPhysics;
			self::$isLiquid[$b->getID()] = $b->isLiquid;
			self::$isFullBlock[$b->getID()] = $b->isFullBlock;
			self::$slipperiness[$b->getID()] = $b->slipperiness;
			self::$boundingBoxes[$b->getID()] = $b->boundingBox;
			self::$hardness[$b->getID()] = $b->getHardness();
		}
	}
	
	public static function getBlock($id){
		return Block::$class[$id] ?? Block::$class[0];
	}
	
	public static function getHardness($id){
		return self::$hardness[$id] ?? StaticBlock::DEFAULT_HARDNESS;
	}
	
	public static function getBoundingBoxForBlockCoords($id, $x, $y, $z){
		/**@var AxisAlignedBB $bb*/
		$bb = self::$boundingBoxes[$id] ?? false;
		if($bb === false){
			return clone self::$NULL_BOUNDS;
		}
		$bb = clone $bb;
		return $bb->setBounds($x + $bb->minX, $y + $bb->minY, $z + $bb->minZ, $x + $bb->maxX, $y + $bb->maxY, $z + $bb->maxZ);
	}
	
	
	public static function getSlipperiness($id){
		return self::$slipperiness[$id] ?? StaticBlock::DEFAULT_SLIPPERINESS;
	}
	
	public static function getIsSolid($id){
		return self::$isSolid[$id] ?? false;
	}
	
	public static function getIsTransparent($id){
		return self::$isTransparent[$id] ?? false;
	}
	
	public static function getIsFlowable($id){
		return self::$isFlowable[$id] ?? false;
	}
	
	public static function getIsReplaceable($id){
		return self::$isReplaceable[$id] ?? false;
	}
	
	public static function getIsPlaceable($id){
		return self::$isPlaceable[$id] ?? false;
	}
	
	public static function getHasPhysics($id){
		return self::$hasPhysics[$id] ?? false;
	}
	
	public static function getIsLiquid($id){
		return self::$isLiquid[$id] ?? false;
	}
	
	public static function getIsFullBlock($id){
		return self::$isFullBlock[$id] ?? false;
	}
}
