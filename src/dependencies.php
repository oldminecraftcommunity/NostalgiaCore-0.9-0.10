<?php

/***REM_START***/
require_once(dirname(__FILE__) . "/config.php");
require_once(FILE_PATH . "/src/utils/TextFormat.php");
require_once(FILE_PATH . "/src/functions.php");
/***REM_END***/
define("DATA_PATH", realpath(arg("data-path", FILE_PATH)) . "/");

if(arg("enable-ansi", strpos(strtoupper(php_uname("s")), "WIN") === 0 ? false : true) === true and arg("disable-ansi", false) !== true){
	define("ENABLE_ANSI", true);
}else{
	define("ENABLE_ANSI", false);
}

set_error_handler("error_handler", E_ALL);

$errors = 0;

if(version_compare("8.0.0", PHP_VERSION) > 0){
	console("[ERROR] Use PHP >= 8.0.0", true, true, 0);
	++$errors;
}

define("USE_NATIVE_RANDOM", version_compare("8.2.0", PHP_VERSION) <= 0);

if(php_sapi_name() !== "cli"){
	console("[ERROR] You must run NostalgiaCore using the CLI.", true, true, 0);
	++$errors;
}

if(!extension_loaded("sockets") and @dl((PHP_SHLIB_SUFFIX === "dll" ? "php_" : "") . "sockets." . PHP_SHLIB_SUFFIX) === false){
	console("[ERROR] Unable to find the Socket extension.", true, true, 0);
	++$errors;
}

if(!extension_loaded("pthreads") and @dl((PHP_SHLIB_SUFFIX === "dll" ? "php_" : "") . "pthreads." . PHP_SHLIB_SUFFIX) === false){
	console("[ERROR] Unable to find the pthreads extension.", true, true, 0);
	++$errors;
}else{
	$pthreads_version = phpversion("pthreads");
	if(substr_count($pthreads_version, ".") < 2){
		$pthreads_version = "0.$pthreads_version";
	}
	if(version_compare($pthreads_version, "0.1.0") < 0){
		console("[ERROR] pthreads >= 0.1.0 is required, while you have $pthreads_version.", true, true, 0);
		++$errors;
	}
}

if(!extension_loaded("curl") and @dl((PHP_SHLIB_SUFFIX === "dll" ? "php_" : "") . "curl." . PHP_SHLIB_SUFFIX) === false){
	console("[ERROR] Unable to find the cURL extension.", true, true, 0);
	++$errors;
}

if(!extension_loaded("sqlite3") and @dl((PHP_SHLIB_SUFFIX === "dll" ? "php_" : "") . "sqlite3." . PHP_SHLIB_SUFFIX) === false){
	console("[ERROR] Unable to find the SQLite3 extension.", true, true, 0);
	++$errors;
}

if(!extension_loaded("yaml") and @dl((PHP_SHLIB_SUFFIX === "dll" ? "php_" : "") . "yaml." . PHP_SHLIB_SUFFIX) === false){
	console("[ERROR] Unable to find the YAML extension.", true, true, 0);
	++$errors;
}

if(!extension_loaded("zlib") and @dl((PHP_SHLIB_SUFFIX === "dll" ? "php_" : "") . "zlib." . PHP_SHLIB_SUFFIX) === false){
	console("[ERROR] Unable to find the Zlib extension.", true, true, 0);
	++$errors;
}

if($errors > 0){
	console("[ERROR] Please use the installer provided on the homepage, or recompile PHP again.", true, true, 0);
	exit(1); //Exit with error
}

$sha1sum = "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0";
/***REM_START***/
require_once(FILE_PATH . "/src/utils/IRandom.php");
require_once(FILE_PATH . "/src/math/Vector3.php");
require_once(FILE_PATH . "/src/math/AxisAlignedBB.php");
require_once(FILE_PATH . "/src/world/Position.php");
require_once(FILE_PATH . "/src/pmf/PMF.php");

require_once(FILE_PATH . "/src/astarnavigator/IElement.php");
require_once(FILE_PATH . "/src/astarnavigator/PHPUtils.php");
require_once(FILE_PATH . "/src/astarnavigator/PathTile.php");

require_once(FILE_PATH . "/src/astarnavigator/algorithms/IDistanceAlgorithm.php");
require_once(FILE_PATH . "/src/astarnavigator/algorithms/Pythagoras3D.php");
require_once(FILE_PATH . "/src/astarnavigator/algorithms/ManhattanHeuristic3D.php");

require_once(FILE_PATH . "/src/astarnavigator/providers/INeighborProvider.php");
require_once(FILE_PATH . "/src/astarnavigator/providers/MCDiagonalProvider.php");

require_once(FILE_PATH . "/src/astarnavigator/providers/IBlockedProvider.php");
require_once(FILE_PATH . "/src/astarnavigator/providers/MCBlockedProvider.php");

require_once(FILE_PATH . "/src/astarnavigator/ITileNavigator.php");
require_once(FILE_PATH . "/src/astarnavigator/TileNavigator.php");

require_once(FILE_PATH . "/src/nbt/tag/NamedTag.php");

require_once(FILE_PATH . "/src/entity/ai/tasks/TaskBase.php");
require_once(FILE_PATH. "/src/entity/ai/tasks/TaskTempt.php");

require_once(FILE_PATH . "/src/entity/Rideable.php");
require_once(FILE_PATH . "/src/entity/Attachable.php");
require_once(FILE_PATH . "/src/entity/Damageable.php");
require_once(FILE_PATH . "/src/entity/Breedable.php");
require_once(FILE_PATH . "/src/entity/Damageable.php");
require_once(FILE_PATH . "/src/entity/Pathfindable.php");
require_once(FILE_PATH . "/src/entity/Entity.php");
require_once(FILE_PATH . "/src/entity/Living.php");
require_once(FILE_PATH . "/src/entity/Creature.php");
require_once(FILE_PATH . "/src/entity/Ageable.php");
require_once(FILE_PATH . "/src/entity/Animal.php");

require_once(FILE_PATH . "/src/world/MobSpawner.php");

require_once(FILE_PATH . "/src/material/Item.php");
require_once(FILE_PATH . "/src/material/item/base/ItemTool.php");
require_once(FILE_PATH . "/src/material/item/base/ItemHoe.php");
require_once(FILE_PATH . "/src/material/item/base/ItemPickaxe.php");
require_once(FILE_PATH . "/src/material/item/base/ItemAxe.php");
require_once(FILE_PATH . "/src/material/item/base/ItemShovel.php");
require_once(FILE_PATH . "/src/material/item/base/ItemSword.php");
require_once(FILE_PATH . "/src/material/item/armor/ArmorItem.php");

require_once(FILE_PATH . "/src/structure/Structure.php");
require_once(FILE_PATH . "/src/world/generator/Populator.php");
require_once(FILE_PATH . "/src/world/generator/LevelGenerator.php");

require_once(FILE_PATH . "/src/world/generator/structure/StructureBase.php");
require_once(FILE_PATH . "/src/world/generator/structure/StructureGenerator.php");
require_once(FILE_PATH . "/src/plugin/Plugin.php");
require_once(FILE_PATH . "/src/plugin/OtherPluginRequirement.php");
require_once(FILE_PATH . "/src/plugin/DummyPlugin.php");
require_once(FILE_PATH . "/src/plugin/phar/IClassLoader.php");
require_once(FILE_PATH . "/src/plugin/phar/PharUtils.php");

require_once(FILE_PATH . "/src/world/generator/LevelGenerator.php");
require_once(FILE_PATH . "/src/world/generator/NewLevelGenerator.php");

require_once(FILE_PATH . "/src/world/generator/biome/BiomeDecorator.php");

require_once(FILE_PATH . "/src/world/generator/biome/Biome.php");
require_once(FILE_PATH . "/src/world/generator/biome/BiomeSelector.php");
require_once(FILE_PATH . "/src/world/generator/biome/NormalGeneratorBiomeSelector.php");
require_once(FILE_PATH . "/src/world/generator/biome/biomes/BiomeWithGrass.php");
require_once(FILE_PATH . "/src/world/generator/biome/biomes/BiomeWithSand.php");
require_once(FILE_PATH . "/src/world/generator/biome/biomes/BiomeWithSnow.php");
require_once(FILE_PATH . "/src/world/generator/biome/biomes/BiomeExtremeHills.php");


require_once(FILE_PATH . "/src/world/generator/populator/TreePopulator.php");




require_all(FILE_PATH . "src/");



$inc = get_included_files();
$inc[] = array_shift($inc);
$srcdir = realpath(FILE_PATH . "src/");
foreach($inc as $s){
	if(strpos(realpath(dirname($s)), $srcdir) === false and strtolower(basename($s)) !== "pocketmine-mp.php"){
		continue;
	}
	$sha1sum ^= sha1_file($s, true);
}
/***REM_END***/
ini_set("opcache.mmap_base", bin2hex(Utils::getRandomBytes(8, false))); //Fix OPCache address errors
define("SOURCE_SHA1SUM", bin2hex($sha1sum));

/***REM_START***/
if(!file_exists(DATA_PATH . "server.properties") and arg("no-wizard", false) != true){
	$installer = new Installer();
}
/***REM_END***/
