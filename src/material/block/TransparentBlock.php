<?php

class TransparentBlock extends GenericBlock{
	/**
	 * @param int $id
	 * @param int $meta
	 * @param string $name
	 */
	public function __construct($id, $meta = 0, $name = "Unknown"){
		parent::__construct($id, $meta, $name);
		$this->isActivable = false;
		$this->breakable = true;
		$this->isFlowable = false;
		$this->isTransparent = true;
		$this->isReplaceable = false;
		$this->isPlaceable = true;		
		$this->isSolid = true;
		$this->boundingBox->setBounds($this->x, $this->y, $this->z, $this->x + 1, $this->y + 1, $this->z + 1);
	}
}
