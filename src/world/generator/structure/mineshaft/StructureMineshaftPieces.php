<?php

class StructureMineshaftPieces
{
	//TODO chests
	//private static final WeightedRandomChestContent[] mineshaftChestContents = new WeightedRandomChestContent[] {new WeightedRandomChestContent(Item.ingotIron.itemID, 0, 1, 5, 10), new WeightedRandomChestContent(Item.ingotGold.itemID, 0, 1, 3, 5), new WeightedRandomChestContent(Item.redstone.itemID, 0, 4, 9, 5), new WeightedRandomChestContent(Item.dyePowder.itemID, 4, 4, 9, 5), new WeightedRandomChestContent(Item.diamond.itemID, 0, 1, 2, 3), new WeightedRandomChestContent(Item.coal.itemID, 0, 3, 8, 10), new WeightedRandomChestContent(Item.bread.itemID, 0, 1, 3, 15), new WeightedRandomChestContent(Item.pickaxeIron.itemID, 0, 1, 1, 1), new WeightedRandomChestContent(Block.rail.blockID, 0, 4, 8, 1), new WeightedRandomChestContent(Item.melonSeeds.itemID, 0, 2, 4, 10), new WeightedRandomChestContent(Item.pumpkinSeeds.itemID, 0, 2, 4, 10)};
	/**
	 * @return StructureComponent
	 */
	public static function getRandomComponent($aList, IRandom $random, $par2, $par3, $par4, $par5, $par6){
		$var7 = $random->nextInt(100);
		
		/*if($var7 >= 80){
			$var8 = ComponentMineshaftCross //TODO
			if($var8 != null) return new ComponentMineshaftCross($par6, $random, $var8, $par5);
		}else if($var7 >= 70){
			$var8 = ComponentMineshaftStairs //TODO
			if($var8 != null) return new ComponentMineshaftStairs($par6, $random, $var8, $par5);
		}else{
			$var8 = ComponentMineshaftCorridor //TODO
			if($var8 != null) return new ComponentMineshaftCorridor($par6, $random, $var8, $par5);
		}*/
		
		return null;
		
	}
	
	public static function getNextMineShaftComponent(StructureComponent $component, $aList, IRandom $random, $par3, $par4, $par5, $par6, $par7){
		if($par7 > 8) return null;
		
		if(abs($par3 - $component->boundingBox->minX) <= 80 && abs($par5 - $component->boundingBox->minZ) <= 80){
			$var8 = self::getRandomComponent($aList, $random, $par3, $par4, $par5, $par6, $par7 + 1);
			if($var8 != null){
				$aList[] = $var8;
				$var8->buildComponent($component, $aList, $random);
			}
			return $var8;
		}
		
		return null;
	}
}

