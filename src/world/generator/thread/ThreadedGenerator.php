<?php

interface ThreadedGenerator{
	/**
	 * @return ThreadedChunkDataProvider
	 */
	public function getDataProvider();
}

