<?php

class BiomeDecorator
{
	public function decorate(Level $level, $chunkX, $chunkZ, IRandom $random){
		return new SmallTreeObject(SaplingBlock::JUNGLE);
	}
}

