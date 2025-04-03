<?php

class StructureMineshaftStart extends StructureStart
{
	public function __construct(Level $level, IRandom $random, $par3, $par4){
		$var5 = new ComponentMineshaftRoom(0, $random, ($par3 << 4) + 2, ($par4 << 4) + 2);
		$this->components[] = $var5;
		$var5->buildComponent($var5, $this->components, $random);
		$this->updateBoundingBox();
		$this->markAvailableHeight($level, $random, 10);
	}
}

