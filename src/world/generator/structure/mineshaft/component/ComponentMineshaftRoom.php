<?php

class ComponentMineshaftRoom extends StructureComponent
{
	/**
	 * @var AxisAlignedBB[]
	 */
	public $roomsLinkedToTheRoom = [];
	
	public function __construct($par1, IRandom $random, $par3, $par4){
		parent::__construct($par1);
		$this->boundingBox = new AxisAlignedBB($par3, 50, $par4, $par3 + 7 + $random->nextInt(6), $par4 + 7, $par4 + 7 + $random->nextInt(6));
	}
	
	public function buildComponent(StructureComponent $component, $aList, IRandom $random){
		$var4 = $this->componentType;
		$var6 = $this->boundingBox->getYSize() - 3 - 1;
		if($var6 <= 0) $var6 = 1;
		
		for($var5 = 0; $var5 < $this->boundingBox->getXSize(); $var5 += 4){
			$var5 += $random->nextInt($this->boundingBox->getXSize());
			if($var5 + 3 < $this->boundingBox->getXSize()) break;
			//var7 = StructureMineshaftPieces.getNextComponent(par1StructureComponent, par2List, par3Random, this.boundingBox.minX + var5, this.boundingBox.minY + par3Random.nextInt(var6) + 1, this.boundingBox.minZ - 1, 2, var4);
			$var7 = StructureMineshaftPieces::getNextMineShaftComponent($component, $aList, $random, $this->boundingBox->minX + $var5, $this->boundingBox->minY + $random->nextInt($var6) + 1, $this->boundingBox->minZ - 1, 2, $var4);
			
			if($var7 != null){
				$var8 = $var7->boundingBox;
				//this.roomsLinkedToTheRoom.add(new StructureBoundingBox(var8.minX, var8.minY, this.boundingBox.minZ, var8.maxX, var8.maxY, this.boundingBox.minZ + 1));
				$this->roomsLinkedToTheRoom[] = new StructureAABB($var8->minX, $var8->minY, $this->boundingBox->minZ, $var8->maxX, $var8->maxY, $this->boundingBox->minZ + 1);
			}
		}
		
		for($var5 = 0; $var5 < $this->boundingBox->getXSize(); $var5 += 4){
			$var5 += $random->nextInt($this->boundingBox->getXSize());
			if($var5 + 3 < $this->boundingBox->getXSize()) break;
			//var7 = StructureMineshaftPieces.getNextComponent(par1StructureComponent, par2List, par3Random, this.boundingBox.minX + var5, this.boundingBox.minY + par3Random.nextInt(var6) + 1, this.boundingBox.maxZ + 1, 0, var4);
			$var7 = StructureMineshaftPieces::getNextMineShaftComponent($component, $aList, $random, $this->boundingBox->minX + $var5, $this->boundingBox->minY + $random->nextInt($var6) + 1, $this->boundingBox->maxZ + 1, 0, $var4);
			
			if($var7 != null){
				$var8 = $var7->boundingBox;
				//this.roomsLinkedToTheRoom.add(new StructureBoundingBox(var8.minX, var8.minY, this.boundingBox.maxZ - 1, var8.maxX, var8.maxY, this.boundingBox.maxZ));
				$this->roomsLinkedToTheRoom[] = new StructureAABB($var8->minX, $var8->minY, $this->boundingBox->maxZ - 1, $var8->maxX, $var8->maxY, $this->boundingBox->maxZ);
			}
		}
		
		for($var5 = 0; $var5 < $this->boundingBox->getZSize(); $var5 += 4){
			$var5 += $random->nextInt($this->boundingBox->getZSize());
			if($var5 + 3 < $this->boundingBox->getZSize()) break;
			//var7 = StructureMineshaftPieces.getNextComponent(par1StructureComponent, par2List, par3Random, this.boundingBox.minX - 1, this.boundingBox.minY + par3Random.nextInt(var6) + 1, this.boundingBox.minZ + var5, 1, var4);
			$var7 = StructureMineshaftPieces::getNextMineShaftComponent($component, $aList, $random, $this->boundingBox->minX - 1, $this->boundingBox->minY + $random->nextInt($var6) + 1, $this->boundingBox->minZ + $var5, 1, $var4);
			
			if($var7 != null){
				$var8 = $var7->boundingBox;
				//this.roomsLinkedToTheRoom.add(new StructureBoundingBox(this.boundingBox.minX, var8.minY, var8.minZ, this.boundingBox.minX + 1, var8.maxY, var8.maxZ));
				$this->roomsLinkedToTheRoom[] = new StructureAABB($this->boundingBox->minX, $var8->minY, $var8->minZ, $this->boundingBox->minX + 1, $var8->maxY, $var8->maxZ);
			}
		}
		
		for($var5 = 0; $var5 < $this->boundingBox->getZSize(); $var5 += 4){
			$var5 += $random->nextInt($this->boundingBox->getZSize());
			if($var5 + 3 < $this->boundingBox->getZSize()) break;
			//var7 = StructureMineshaftPieces.getNextComponent(par1StructureComponent, par2List, par3Random, this.boundingBox.maxX + 1, this.boundingBox.minY + par3Random.nextInt(var6) + 1, this.boundingBox.minZ + var5, 3, var4);
			$var7 = StructureMineshaftPieces::getNextMineShaftComponent($component, $aList, $random, $this->boundingBox->maxX + 1, $this->boundingBox->minY + $random->nextInt($var6) + 1, $this->boundingBox->minZ + $var5, 3, $var4);
			
			if($var7 != null){
				$var8 = $var7->boundingBox;
				// this.roomsLinkedToTheRoom.add(new StructureBoundingBox(this.boundingBox.maxX - 1, var8.minY, var8.minZ, this.boundingBox.maxX, var8.maxY, var8.maxZ));
				$this->roomsLinkedToTheRoom[] = new StructureAABB($this->boundingBox->maxX - 1, $var8->minY, $var8->minZ, $this->boundingBox->maxX, $var8->maxY, $var8->maxZ);
			}
		}
	}
	
	public function addComponentParts(Level $level, IRandom $random, AxisAlignedBB $boundingBox): bool
	{
		if($this->isLiquidInStructureBoundingBox($level, $boundingBox)){
			return false;
		}
		console("Adding Mineshaft start components! $boundingBox");
		$this->fillWithBlocks($level, $boundingBox, $this->boundingBox->minX, $this->boundingBox->minY, $this->boundingBox->minZ, $this->boundingBox->maxX, $this->boundingBox->maxY, $this->boundingBox->maxZ, DIRT, 0, true);
		$this->fillWithBlocks($level, $boundingBox, $this->boundingBox->minX, $this->boundingBox->minY + 1, $this->boundingBox->minZ, $this->boundingBox->maxX, min($this->boundingBox->minY + 3, $this->boundingBox->maxY), $this->boundingBox->maxZ, 0, 0, false);
		
		foreach($this->roomsLinkedToTheRoom as $bb){
			//this.fillWithBlocks(par1World, par3StructureBoundingBox, var5.minX, var5.maxY - 2, var5.minZ, var5.maxX, var5.maxY, var5.maxZ, 0, 0, false);
			$this->fillWithBlocks($level, $boundingBox, $bb->minX, $bb->maxY - 2, $bb->minZ, $bb->maxX, $bb->maxY, $bb->maxZ, 0, 0, false);
		}
		
		$this->randomlyRareFillWithBlocks($level, $boundingBox, $this->boundingBox->minX, $this->boundingBox->minY + 4, $this->boundingBox->minZ, $this->boundingBox->maxX, $this->boundingBox->maxY, $this->boundingBox->maxZ, 0, false);
		return true;
	}
}

