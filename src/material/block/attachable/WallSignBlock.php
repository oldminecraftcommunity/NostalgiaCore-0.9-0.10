<?php

/***REM_START***/
require_once("SignPostBlock.php");
/***REM_END***/

class WallSignBlock extends SignPostBlock{
	public function __construct($meta = 0){
		TransparentBlock::__construct(WALL_SIGN, $meta, "Wall Sign");
		$this->isSolid = false;
		$this->isFullBlock = false;
		$this->hardness = 5;
	}

	public function onUpdate($type){
		return false;
	}
}
