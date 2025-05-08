<?php

define("BIG_ENDIAN", 0x00);
define("LITTLE_ENDIAN", 0x01);
define("ENDIANNESS", (pack("d", 1) === "\77\360\0\0\0\0\0\0" ? BIG_ENDIAN : LITTLE_ENDIAN));

class Utils{

	public static $online = true;
	public static $ip = false;
	public static $threads = [];
	
	/*public static function isOnline(){
		return ((@fsockopen("8.8.8.8", 80, $e = null, $n = null, 2) !== false or @fsockopen("www.linux.org", 80, $e = null, $n = null, 2) !== false or @fsockopen("www.php.net", 80, $e = null, $n = null, 2) !== false) ? true:false);
	}*/
	const emojiRegex = "([*#0-9](?>\\xEF\\xB8\\x8F)?\\xE2\\x83\\xA3|\\xC2[\\xA9\\xAE]|\\xE2..(\\xF0\\x9F\\x8F[\\xBB-\\xBF])?(?>\\xEF\\xB8\\x8F)?|\\xE3(?>\\x80[\\xB0\\xBD]|\\x8A[\\x97\\x99])(?>\\xEF\\xB8\\x8F)?|\\xF0\\x9F(?>[\\x80-\\x86].(?>\\xEF\\xB8\\x8F)?|\\x87.\\xF0\\x9F\\x87.|..(\\xF0\\x9F\\x8F[\\xBB-\\xBF])?|(((?<zwj>\\xE2\\x80\\x8D)\\xE2\\x9D\\xA4\\xEF\\xB8\\x8F\k<zwj>\\xF0\\x9F..(\k<zwj>\\xF0\\x9F\\x91.)?|(\\xE2\\x80\\x8D\\xF0\\x9F\\x91.){2,3}))?))";
	public static function getEntityTypeByID($id){
		return match($id){
			MOB_CHICKEN, MOB_COW, MOB_CREEPER, MOB_ENDERMAN, MOB_MOOSHROOM, MOB_PIG, MOB_PIGMAN, MOB_SHEEP, MOB_SILVERFISH, MOB_SKELETON, MOB_SLIME, MOB_SPIDER,
				 MOB_VILLAGER, MOB_WOLF, MOB_ZOMBIE => ENTITY_MOB,
			OBJECT_ARROW, OBJECT_EGG, OBJECT_MINECART, OBJECT_PAINTING, OBJECT_PRIMEDTNT, OBJECT_SNOWBALL, OBJECT_TRIPOD_CAMERA => ENTITY_OBJECT,
			default => $id,
		};
	}
	
	/**
	 * Keeps thread reference in memory
	 */
	public static function addThread($t){
		$id = spl_object_hash($t);
		self::$threads[$id] = $t;
	}
	
	/**
	 *  Releases thread reference from memory. Thread should be stopped before calling this method.
	 */
	public static function removeThread($t){
		unset(self::$threads[spl_object_hash($t)]);
	}
	
	public static function wrapAngleTo360($angle)
	{
		$angle = (int)$angle % 360;
		return $angle < 0 ? $angle + 360 : $angle;
	}
	
	public static function chunkPos2Int($x, $z){
		return ($x & 0xFFFFFFFF) | (($z & 0xFFFFFFFF) << 32);
	}
	
	public static function wrapAngleTo180($angle)
	{
		$angle = fmod($angle+180, 360);
		return $angle < 0 ? $angle + 360 : $angle - 180;
	}
	
	public static function getSign($v){
		return $v > 0 ? 1 : ($v < 0 ? -1  : 0);
	}
	
	public static function clampDegrees($v){
		return floor(($v % 360 + 360) % 360);
	}
	
	public static function endsWith($str, $check) {
		return substr($str, -strlen($check)) === $check;
	}
	
	public static function hasEmoji($s){
		return preg_match(Utils::emojiRegex, $s);
	}
	
	public static function getCallableIdentifier(callable $variable){
		if(is_array($variable)){
			return sha1(strtolower(get_class($variable[0])) . "::" . strtolower($variable[1]));
		}else{
			return sha1(strtolower($variable));
		}
	}
	
	public static function in_range($num, $min, $max){
		return $num >= $min && $num <= $max;
	}
	
	public function xrange($start, $limit, $step = 1) {
		if ($start <= $limit) {
			if ($step <= 0) {
				throw new LogicException('Step must be positive');
			}
			
			for ($i = $start; $i <= $limit; $i += $step) {
				yield $i;
			}
		} else {
			if ($step >= 0) {
				throw new LogicException('Step must be negative');
			}
			
			for ($i = $start; $i >= $limit; $i += $step) {
				yield $i;
			}
		}
	}
	
	public static function getUniqueID($raw = false, $extra = ""){
		$machine = php_uname("a");
		$machine .= file_exists("/proc/cpuinfo") ? file_get_contents("/proc/cpuinfo") : "";
		$machine .= sys_get_temp_dir();
		$machine .= $extra;
		if(Utils::getOS() == "win"){
			exec("ipconfig /ALL", $mac);
			$mac = implode("\n", $mac);
			if(preg_match_all("#Physical Address[. ]{1,}: ([0-9A-F\-]{17})#", $mac, $matches)){
				foreach($matches[1] as $i => $v){
					if($v == "00-00-00-00-00-00"){
						unset($matches[1][$i]);
					}
				}
				$machine .= implode(" ", $matches[1]); //Mac Addresses
			}
		}
		$data = $machine . PHP_MAXPATHLEN;
		$data .= PHP_INT_MAX;
		$data .= PHP_INT_SIZE;
		$data .= get_current_user();
		foreach(get_loaded_extensions() as $ext){
			$data .= $ext . ":" . phpversion($ext);
		}
		return hash("md5", $machine, $raw) . hash("sha512", $data, $raw);
	}

	public static function getOS(){
		$uname = php_uname("s");
		if(stripos($uname, "Darwin") !== false){
			if(strpos(php_uname("m"), "iP") === 0){
				return "ios";
			}else{
				return "mac";
			}
		}elseif(stripos($uname, "Win") !== false or $uname === "Msys"){
			return "win";
		}elseif(stripos($uname, "Linux") !== false){
			if(@file_exists("/system/build.prop")){
				return "android";
			}else{
				return "linux";
			}
		}elseif(stripos($uname, "BSD") !== false or $uname === "DragonFly"){
			return "bsd";
		}else{
			return "other";
		}
	}

	public static function getIP($force = false){
		if(Utils::$online === false){
			return false;
		}elseif(Utils::$ip !== false and $force !== true){
			return Utils::$ip;
		}
		$ip = trim(strip_tags(Utils::curl_get("http://checkip.dyndns.org/")));
		if(preg_match('#Current IP Address\: ([0-9a-fA-F\:\.]*)#', $ip, $matches) > 0){
			Utils::$ip = $matches[1];
		}else{
			$ip = Utils::curl_get("http://www.checkip.org/");
			if(preg_match('#">([0-9a-fA-F\:\.]*)</span>#', $ip, $matches) > 0){
				Utils::$ip = $matches[1];
			}else{
				$ip = Utils::curl_get("http://checkmyip.org/");
				if(preg_match('#Your IP address is ([0-9a-fA-F\:\.]*)#', $ip, $matches) > 0){
					Utils::$ip = $matches[1];
				}else{
					$ip = trim(Utils::curl_get("http://ifconfig.me/ip"));
					if($ip != ""){
						Utils::$ip = $ip;
					}else{
						return false;
					}
				}
			}
		}
		return Utils::$ip;

	}
	
	public static function makeHeaders($json){
		$arr = json_decode($json);
		$rarr = [];
		foreach ($arr as $key => $value){
			$rarr[] = $key.": ".$value;
		}
		return $rarr;
	}
	
	public static function curl_get($page, $timeout = 10, $headers = " "){
		if(Utils::$online === false){
			return false;
		}
		
		if($headers != " "){
			$headers = Utils::makeHeaders($headers);
		}else{
			$headers = ["User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:12.0) Gecko/20100101 Firefox/12.0 PocketMine-MP"];
		}
		$ch = curl_init($page);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $timeout);
		$ret = curl_exec($ch);
		curl_close($ch);
		return $ret;
	}

	public static function hexdump($bin){
		$output = "";
		$bin = str_split($bin, 16);
		foreach($bin as $counter => $line){
			$hex = chunk_split(chunk_split(str_pad(bin2hex($line), 32, " ", STR_PAD_RIGHT), 2, " "), 24, " ");
			$ascii = preg_replace('#([^\x20-\x7E])#', ".", $line);
			$output .= str_pad(dechex($counter << 4), 4, "0", STR_PAD_LEFT) . "  " . $hex . " " . $ascii . PHP_EOL;
		}
		return $output;
	}

	public static function printable($str){
		if(!is_string($str)){
			return gettype($str);
		}
		return preg_replace('#([^\x20-\x7E])#', '.', $str);
	}

	public static function getRandomUpdateTicks(){
		return -log(lcg_value()) * 1365.4; //Poisson distribution (1/(68.27 * 20))
	}

	public static function writeMetadata($data){
		$m = "";
		foreach($data as $bottom => $d){
			$m .= chr(($d["type"] << 5) | ($bottom & 0b00011111));
			switch($d["type"]){
				case 0:
					$m .= Utils::writeByte($d["value"]);
					break;
				case 1:
					$m .= Utils::writeLShort($d["value"]);
					break;
				case 2:
					$m .= Utils::writeLInt($d["value"]);
					break;
				case 3:
					$m .= Utils::writeLFloat($d["value"]);
					break;
				case 4:
					$m .= Utils::writeLShort(strlen($d["value"]));
					$m .= $data["value"];
					break;
				case 5:
					$m .= Utils::writeLShort($d["value"][0]);
					$m .= Utils::writeByte($d["value"][1]);
					$m .= Utils::writeLShort($d["value"][2]);
					break;
				case 6:
					for($i = 0; $i < 3; ++$i){
						$m .= Utils::writeLInt($d["value"][$i]);
					}
					break;
			}
		}
		$m .= "\x7f";
		return $m;
	}

	public static function writeByte($c){
		if($c > 0xff){
			return false;
		}
		if($c < 0 and $c >= -0x80){
			$c = 0xff + $c + 1;
		}
		return chr($c);
	}

	public static function writeLShort($value){
		return pack("v", $value);
	}

	public static function writeLInt($value){
		if($value < 0){
			$value += 0x100000000;
		}
		return pack("V", $value);
	}

	public static function writeLFloat($value){
		return ENDIANNESS === BIG_ENDIAN ? strrev(pack("f", $value)) : pack("f", $value);
	}

	public static function writeSlot(Item $item){
		return Utils::writeShort($item->getID()) . chr($item->count) . Utils::writeShort($item->getMetadata());
	}

	public static function writeShort($value){
		return pack("n", $value);
	}

	public static function readSlot($ob){
		$id = Utils::readShort($ob->get(2));
		$cnt = ord($ob->get(1));
		return BlockAPI::getItem(
			$id,
			Utils::readShort($ob->get(2)),
			$cnt
		);
	}

	public static function readShort($str, $signed = true){
		if(strlen($str) === 0){
			return;
		}

		list(, $unpacked) = unpack("n", $str);
		if($unpacked > 0x7fff and $signed === true){
			$unpacked -= 0x10000; // Convert unsigned short to signed short
		}

		return $unpacked;
	}

	public static function readMetadata($value, $types = false){
		$offset = 0;
		$m = [];
		$b = ord($value[$offset]);
		++$offset;
		while($b !== 127 and isset($value[$offset])){
			$bottom = $b & 0x1F;
			$type = $b >> 5;
			switch($type){
				case 0:
					$r = Utils::readByte($value[$offset]);
					++$offset;
					break;
				case 1:
					$r = Utils::readLShort(substr($value, $offset, 2));
					$offset += 2;
					break;
				case 2:
					$r = Utils::readLInt(substr($value, $offset, 4));
					$offset += 4;
					break;
				case 3:
					$r = Utils::readLFloat(substr($value, $offset, 4));
					$offset += 4;
					break;
				case 4:
					$len = Utils::readLShort(substr($value, $offset, 2));
					$offset += 2;
					$r = substr($value, $offset, $len);
					$offset += $len;
					break;
				case 5:
					$r = [];
					$r[] = Utils::readLShort(substr($value, $offset, 2));
					$offset += 2;
					$r[] = ord($value[$offset]);
					++$offset;
					$r[] = Utils::readLShort(substr($value, $offset, 2));
					$offset += 2;
					break;
				case 6:
					$r = [];
					for($i = 0; $i < 3; ++$i){
						$r[] = Utils::readLInt(substr($value, $offset, 4));
						$offset += 4;
					}
					break;

			}
			if($types === true){
				$m[$bottom] = [$r, $type];
			}else{
				$m[$bottom] = $r;
			}
			$b = ord($value[$offset]);
			++$offset;
		}
		return $m;
	}

	public static function readByte($c, $signed = true){
		$b = ord($c[0]);
		if($signed === true and ($b & 0x80) === 0x80){ //calculate Two's complement
			$b = -0x80 + ($b & 0x7f);
		}
		return $b;
	}

	public static function readLShort($str, $signed = true){
		list(, $unpacked) = @unpack("v", $str);
		if($unpacked > 0x7fff and $signed === true){
			$unpacked -= 0x10000; // Convert unsigned short to signed short
		}
		return $unpacked;
	}

	public static function readLInt($str){
		if(PHP_INT_SIZE === 8){
			return @unpack("V", $str)[1] << 32 >> 32;
		}else{
			return @unpack("V", $str)[1];
		}
	}

	public static function readLFloat($str){
		list(, $value) = ENDIANNESS === BIG_ENDIAN ? @unpack("f", strrev($str)) : @unpack("f", $str);
		return $value;
	}

	public static function readDataArray($str, $len = 10, &$offset = null){
		$data = [];
		$offset = 0;
		for($i = 1; $i <= $len and isset($str[$offset]); ++$i){
			$l = Utils::readTriad(substr($str, $offset, 3));
			$offset += 3;
			$data[] = substr($str, $offset, $l);
			$offset += $l;
		}
		return $data;
	}
	
	public static function php8_nulcoal(&$arr, $else){
		return isset($arr) ? $arr : $else;
	}
	
	public static function readTriad($str){
		return @unpack("N", "\x00" . $str)[1];
	}

	public static function writeDataArray($data){
		$raw = "";
		foreach($data as $v){
			$raw .= Utils::writeTriad(strlen($v));
			$raw .= $v;
		}
		return $raw;
	}
	
	public static function writeLTriad($value){
		return substr(pack("V", $value), 0, -1);
	}
	
	public static function writeTriad($value){
		return substr(pack("N", $value), 1);
	}

	public static function getRandomBytes($length = 16, $secure = true, $raw = true, $startEntropy = "", &$rounds = 0, &$drop = 0){
		return $raw ? random_bytes($length) : bin2hex(random_bytes($length)); //nobody would ever notice other parameters
	}
	
	public static function chance($i){
		return lcg_value() <= $i / 100;
	}
	/**
	 * @deprecated use lcg_value() instead.
	 * @return number
	 */
	public static function randomFloat(){
		return rand() / getrandmax();
	}

	public static function round($number){
		return round($number, 0, PHP_ROUND_HALF_DOWN);
	}
	/**
	 * manhattan distance
	 */
	public static function manh_distance($pos1, $pos2){
		if($pos1 instanceof Vector3){
			$pos1 = $pos1->toArray();
		}
		if($pos2 instanceof Vector3){
			$pos2 = $pos2->toArray();
		}
		
		return abs($pos2["x"] - $pos1["x"]) + abs($pos2["y"] - $pos1["y"]) + abs($pos2["z"] - $pos1["z"]);
	}
	
	/**
	 * Euclidian distance, but without square roots
	 */
	public static function distance_noroot($pos1, $pos2){
		if($pos1 instanceof Vector3){
			$pos1 = $pos1->toArray();
		}
		if($pos2 instanceof Vector3){
			$pos2 = $pos2->toArray();
		}
		$pX = ($pos1["x"] - $pos2["x"]);
		$pY = ($pos1["y"] - $pos2["y"]);
		$pZ = ($pos1["z"] - $pos2["z"]);
		return ($pX*$pX) + ($pY*$pY) + ($pZ*$pZ);
	}
	
	/**
	 * 
	 * @param $pos1
	 * @param $pos2
	 * @return number
	 */
	public static function distance($pos1, $pos2){
		if($pos1 instanceof Vector3){
			$pos1 = $pos1->toArray();
		}
		if($pos2 instanceof Vector3){
			$pos2 = $pos2->toArray();
		}
		return sqrt(($pos1["x"] - $pos2["x"])*($pos1["x"] - $pos2["x"]) + ($pos1["y"] - $pos2["y"])*($pos1["y"] - $pos2["y"]) + ($pos1["z"] - $pos2["z"])*($pos1["z"] - $pos2["z"]));
	}
	
	public static function angle3D($pos1, $pos2){
		if($pos1 instanceof Vector3){
			$pos1 = $pos1->toArray();
		}
		if($pos2 instanceof Vector3){
			$pos2 = $pos2->toArray();
		}
		$X = $pos1["x"] - $pos2["x"];
		$Z = $pos1["z"] - $pos2["z"];
		$dXZ = sqrt(pow($X, 2) + pow($Z, 2));
		$Y = $pos1["y"] - $pos2["y"];
		$hAngle = rad2deg(atan2($Z, $X) - M_PI_2);
		$vAngle = rad2deg(-atan2($Y, $dXZ));
		return ["yaw" => $hAngle, "pitch" => $vAngle];
	}

	public static function curl_post($page, $args, $timeout = 10){
		if(Utils::$online === false){
			return false;
		}

		$ch = curl_init($page);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ["User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:12.0) Gecko/20100101 Firefox/12.0 PocketMine-MP"]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $timeout);
		$ret = curl_exec($ch);
		curl_close($ch);
		return $ret;
	}

	public static function strToHex($str){
		return bin2hex($str);
	}

	public static function hexToStr($hex){
		return hex2bin($hex);
	}

	public static function readBool($b){
		return Utils::readByte($b, false) === 0 ? false : true;
	}

	public static function writeBool($b){
		return Utils::writeByte($b === true ? 1 : 0);
	}

	public static function readInt($str){
		if(strlen($str) <= 0) return; 
		if(PHP_INT_SIZE === 8){
			return @unpack("N", $str)[1] << 32 >> 32;
		}else{
			return @unpack("N", $str)[1];
		}
	}

	public static function writeInt($value){
		if($value < 0){
			$value += 0x100000000;
		}
		return pack("N", $value);
	}

	public static function readFloat($str){
		list(, $value) = ENDIANNESS === BIG_ENDIAN ? @unpack("f", $str) : @unpack("f", strrev($str));
		return $value;
	}

	public static function writeFloat($value){
		return ENDIANNESS === BIG_ENDIAN ? pack("f", $value) : strrev(pack("f", $value));
	}

	public static function printFloat($value){
		return preg_replace("/(\.\d+?)0+$/", "$1", sprintf("%F", $value));
	}

	public static function readDouble($str){
		list(, $value) = ENDIANNESS === BIG_ENDIAN ? @unpack("d", $str) : @unpack("d", strrev($str));
		return $value;
	}

	public static function writeDouble($value){
		return ENDIANNESS === BIG_ENDIAN ? pack("d", $value) : strrev(pack("d", $value));
	}

	public static function readLDouble($str){
		list(, $value) = ENDIANNESS === BIG_ENDIAN ? @unpack("d", strrev($str)) : @unpack("d", $str);
		return $value;
	}

	public static function writeLDouble($value){
		return ENDIANNESS === BIG_ENDIAN ? strrev(pack("d", $value)) : pack("d", $value);
	}

	public static function readLLong($str){
		return Utils::readLong(strrev($str));
	}

	public static function readLong($x, $signed = true){
		$value = "0";
		if($signed === true){
			$negative = ((ord($x[0]) & 0x80) === 0x80) ? true : false;
			if($negative){
				$x = ~$x;
			}
		}else{
			$negative = false;
		}

		for($i = 0; $i < 8; $i += 4){
			$value = bcmul($value, "4294967296", 0); //4294967296 == 2^32
			$value = bcadd($value, 0x1000000 * ord(@$x[$i]) + ((ord(@$x[$i + 1]) << 16) | (ord(@$x[$i + 2]) << 8) | ord(@$x[$i + 3])), 0);
		}
		return ($negative === true ? "-" . $value : $value);
	}

	public static function writeLLong($value){
		return strrev(Utils::writeLong($value));
	}

	public static function writeLong($value){
		$x = "";
		$value = (string) $value;
		if(!is_float($value)){
			if(strval($value[0] == null ? '' : $value[0]) === "-"){
				$negative = true;
				$value = bcadd($value, "1");
				if(strval($value[0]) === "-"){
					$value = substr($value, 1);
				}
			}else{
				$negative = false;
			}
			while(bccomp($value, "0", 0) > 0){
				$temp = bcmod($value, "16777216");
				$x = chr($temp >> 16) . chr($temp >> 8) . chr($temp) . $x;
				$value = bcdiv($value, "16777216", 0);
			}
			$x = str_pad(substr($x, 0, 8), 8, "\x00", STR_PAD_LEFT);
			if($negative === true){
				$x = ~$x;
			}
			return $x;
		}
	}
}

//if(Utils::isOnline() === false){
//Utils::$online = false;
//}