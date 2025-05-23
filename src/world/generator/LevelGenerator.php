<?php

interface LevelGenerator{

	public function __construct(array $options = []);

	public function init(Level $level, IRandom $random);

	public function generateChunk($chunkX, $chunkZ);

	public function populateChunk($chunkX, $chunkZ);

	public function populateLevel();

	public function getSpawn();
}