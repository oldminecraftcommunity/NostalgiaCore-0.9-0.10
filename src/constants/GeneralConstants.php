<?php

define("PMF_LEVEL_DEFLATE_LEVEL", 6);

//Gamemodes
define("SURVIVAL", 0);
define("CREATIVE", 1);
define("ADVENTURE", 2);
define("VIEW", 3);
define("VIEWER", 3);
define("SPECTATOR", 3);

//Players
define("MAX_CHUNK_RATE", 2);
define("PLAYER_MAX_QUEUE", 1024);

define("PLAYER_SURVIVAL_SLOTS", 36);
define("PLAYER_CREATIVE_SLOTS", 112); //hum?

//Block Updates
define("BLOCK_UPDATE_NORMAL", 1);
define("BLOCK_UPDATE_RANDOM", 2);
define("BLOCK_UPDATE_SCHEDULED", 3);
define("BLOCK_UPDATE_WEAK", 4);
define("BLOCK_UPDATE_TOUCH", 5);

//Block Sides
define("SIDE_DOWN", 0);
define("SIDE_UP", 1);
define("SIDE_ZNEG", 2);
define("SIDE_ZPOS", 3);
define("SIDE_XNEG", 4);
define("SIDE_XPOS", 5);

//Light Layers - contain default values for light. Must be different to work correctly
const LIGHTLAYER_SKY = 15;
const LIGHTLAYER_BLOCK = 0;


