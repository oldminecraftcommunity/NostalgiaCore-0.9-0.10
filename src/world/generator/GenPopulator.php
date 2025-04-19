<?php

abstract class GenPopulator{
	public abstract function populate(Level $level, &$blocks, &$meta, $chunkX, $chunkZ, IRandom $random);
}

