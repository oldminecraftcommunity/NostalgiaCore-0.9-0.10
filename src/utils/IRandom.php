<?php

interface IRandom{
	/**
	 * Generates a random 32-bit positive integer.
	 */
	public function nextInt($bound = null);
	/**
	 * Generates a random float in range from 0 to 1.
	 */
	public function nextFloat();
	public function setSeed($seed);
}