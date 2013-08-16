<?php

define("FILE_PATH", dirname(__FILE__)."/");
ini_set("memory_limit", -1); //Woo
include("src/init.php");

$toppings = array(
	"version" => "Provides the protocol and release version.",
	"packets" => "Provides minimal information on all network packets.",
	"packetinstructions" => "Provides the instructions used to construct network packets.",
	"biomes" => "Gets most biome types.",
);

if(getp("l", "list") !== null){
	$topp = "Toppings: ";
	foreach($toppings as $name => $desc){
		$topp .= "$name, ";
	}
	echo substr($topp, 0, -2).PHP_EOL;
	die();
}



$asmfile = array_pop($argv);
if(!file_exists($asmfile) or strtolower(substr($asmfile, -4)) !== ".asm"){
	echo "Invalid ASM provided".PHP_EOL;
	exit(-1);
}

info("[*] Getting file contents...","");
$asm = file_get_contents($asmfile);
info(" done");
if($asm === false){
	echo "Error loading $asmfile".PHP_EOL;
	exit(-1);
}
info("[*] Splitting lines...","");
$asm = explode("\n", str_replace("\r", "", $asm));
$header = false;
$cnt = count($asm);
$line = 0;
info(" parsing...");
for(;$line < $cnt;++$line){
	if($header === false){
		if($asm[$line] === "; +-------------------------------------------------------------------------+"){
			$header = array(0 => $asm[$line]);
		}
	}else{
		$header[] = $asm[$line];
		if($asm[$line] === "; ==========================================================================="){
			unset($asm[$line]);
			break;
		}
	}
	unset($asm[$line]);
}


function findPREG(array $functions, $pattern, $indexline = false){
	$m = array();
	foreach($functions as $fn){
		foreach($fn[2] as $index => $line){
			if(preg_match($pattern, $line, $matches) > 0){
				if($indexline === true){
					$m[$index] = $matches;
				}else{
					$m[] = $matches;
				}
			}
		}
	}
	return $m;
	
}

$classindex = array();
$classes = array();
$variables = array();
$fn = false;
$methodscount = 0;
info("\r[*] More parsing... found $methodscount methods and ".count($variables)." strings", "");
for(;$line < $cnt;++$line){
	$l = str_replace("\t", " ", $asm[$line]);
	if(strpos($l, "AREA .data, DATA") !== false){
		break;
	}
	unset($asm[$line]);
	if(preg_match('#^([A-Za-z0-9_]{1,}) {1,}DCB "(.{1,})",0#', $l, $matches) > 0){
		$variables[$matches[1]] = $matches[2];
		info("\r[*] More parsing... found $methodscount methods and ".count($variables)." strings", "");
	}elseif($fn === false){
		if($l === "; =============== S U B R O U T I N E ======================================="){
			$fn = true;
		}
	}else{
		if($fn === true){
			if($l !== "" and preg_match('#^; ([A-Za-z0-9_\:\~]{1,})\(([A-Za-z0-9_\:\~, \*\&]*)\)#', $l, $matches) > 0){
				$method = explode("::", $matches[1]);
				$class = array_shift($method);
				$method = implode("::", $method);
				$fn = array($class, $method, $matches[2]);
				if(!isset($classes[$class])){
					$classes[$class] = array(
						$method => array(),
					);
				}elseif(!isset($classes[$class][$method])){
					$classes[$class][$method] = array();
				}
				$classes[$class][$method][$matches[2]] = array(
					0 => $matches[1], //fn
					1 => $matches[2], //Params
					2 => array(), //Instructions
				);
			}elseif($ln != ""){
				$fn = false;
			}
		}else{
			if($l !== "" and substr($l, 0, 17) === "; End of function"){
				$classindex[$fn[0]."::".$fn[1]] =& $classes[$fn[0]][$fn[1]];
				++$methodscount;
				info("\r[*] More parsing... found $methodscount methods and ".count($variables)." strings", "");
				$fn = false;
			}else{
				$classes[$fn[0]][$fn[1]][$fn[2]][2][] = trim($l);
			}
		}
	}
}
info("\r[*] More parsing... found $methodscount methods and ".count($variables)." strings", "");


info(PHP_EOL. "[+] done!");


if(($topp = getp("t", "toppings")) !== null){
	$toppings = explode(",", strtolower(str_replace(" ", "", $topp)));
}else{
	$toppings = array("version", "packets", "packetinstructions","biomes");
}

if(in_array("packetinstructions", $toppings, true) !== false and in_array("packets", $toppings, true) === false){
	$toppings[] = "packets";
}


if(in_array("version", $toppings, true) !== false){
	// 0.7.0+ compatible
	$vVars = findPREG($classindex["Common::getGameVersionString"], '#MOVS {1,}R[0-9], \#([0-9]{1})#');
	if(!isset($vVars[2])){
		$vVars[2] = array(1 => $vVars[1][1]);
		$vVars[1][1] = "0";
	}
	$version = $vVars[0][1].".".@intval($vVars[2][1]).".".$vVars[1][1];
	info("[+] Minecraft: Pocket Edition v$version");


	$protocol = findPREG($classindex["ClientSideNetworkHandler::onConnect"], '/MOVS {1,}R[1-9], #([0-9A-Fx]{1,})/');
	$protocol = substr($protocol[0][1], 0, 2) == "0x" ? hexdec($protocol[0][1]):intval($protocol[0][1]);
	info("[+] Protocol #$protocol");
}

if(in_array("biomes", $toppings, true) !== false){
	info("[*] Getting biomes...", "");
	$biomenames = findPREG($classindex["Biome::initBiomes"], '/LDR {1,}R1, {1,}=\(([A-Za-z0-9_]*) {1,}\-/', true);
	$biomecolors = findPREG($classindex["Biome::initBiomes"], '/LDR {1,}R1, {1,}=(0x[A-F0-9]*)/', true);
	$biomes = array();
	foreach($biomenames as $line => $d){
		$color = "000000";
		foreach($biomecolors as $cline => $c){
			if($cline > $line){
				break;
			}
			$color = $c[1];
		}
		$biomes[$variables[$d[1]]] = array(
			"name" => $variables[$d[1]],
			"color" => hexdec($color),
		);
	}
	info(" done");
}

if(in_array("packets", $toppings, true) !== false){

	info("[*] Searching network functions...", "");
	$serverSide = array();
	$clientSide = array();
	foreach($classindex["ServerSideNetworkHandler::handle"] as $parameters => $class){
		if(preg_match("#, {1,}([A-Za-z_]*)#", $parameters, $matches) > 0){
			$serverSide[$matches[1]] = true;
		}
	}
	foreach($classindex["ClientSideNetworkHandler::handle"] as $parameters => $class){
		if(preg_match("#, {1,}([A-Za-z_]*)#", $parameters, $matches) > 0){
			$clientSide[$matches[1]] = true;
		}
	}

	$networkFunctions = array();
	foreach($classindex as $class => $fn){
		$n = explode("::", $class);
		if(isset($n[1]) and $n[1] === "write"){
			if(substr($n[0], -6) === "Packet" or $n[0] === "MoveEntityPacket_PosRot"){
				if(isset($serverSide[$n[0]]) and isset($clientSide[$n[0]])){
					$dir = 3;
				}elseif(isset($serverSide[$n[0]])){
					$dir = 2;
				}elseif(isset($clientSide[$n[0]])){
					$dir = 1;
				}else{
					$dir = 0;
				}
				$pid = findPREG($fn, '/(MOVS|MOV\.W) {1,}(R[23456]|LR)\, {1,}\#0x([0-9A-F]{2})/');
				$pid = hexdec($pid[0][3]);
				$networkFunctions[$pid] = array($pid, $dir, $n[0]);
			}
		}
	}
	info(" found ".count($networkFunctions));
	if(in_array("packetinstructions", $toppings, true) !== false){
		foreach($networkFunctions as $pid => $data){
			info("[*] Getting ".$data[2]." structure...", "");
			$things = findPREG($classindex[$data[2]."::write"], '/BL {1,}[a-zA-Z0-9_]* {1,}; {1,}.*\:\:([A-Za-z0-9_\<\> ]*)\(/', true);
			$bits = findPREG($classindex[$data[2]."::write"], '/(MOVS|MOV\.W) {1,}R[2]\, {1,}#([x0-9A-F]{1,4})/', true);
			ksort($bits);
			reset($things);
			$key = key($things);
			unset($things[$key]);
			$funcs = array();
			foreach($things as $line => $fn){
				$t = 0;
				switch(strtolower($fn[1])){
					case "writebits":
						foreach($bits as $bline => $d){
							if($bline > $line){
								break;
							}
							$bdata = $d;
						}
						$f = "bits[".hexdec(str_replace("0x", "", $bdata[2]))."]";
						break;
					case "write":
						$f = "byte[]";
						break;
					case "write<ushort>":
					case "write<short>":
						$f = "short";
						break;
					case "write<int>":
						$f = "int";
						break;
					case "write<float>":
						$f = "float";
						break;
					case "write<long>":
					case "write<ulong long>":
					case "raknetguid>":
						$f = "long";
						break;
					case "write<uchar>":
					case "write<char>":
					case "write<signed char>":
						$f = "byte";
						break;
					case "packall":
					case "pack":
						$f = "Metadata";
						break;
					case "writeiteminstance":
						$f = "Item";
						break;
					case "serialize":
					case "writestring":
						$f = "string8";
						break;
					case "doendianswap":
						$f = "doEndianSwap()";
						$t = 1;
						break;
					case "reversebytes":
						$f = "reverseBytes()";
						$t = 1;
						break;
					case "rot_degreestochar":
						$f = "degreesToChar()";
						$t = 1;
						break;
					case "clamp":
						$f = "clamp()";
						$t = 1;
						break;
					case "isnetworkorder":
					case "isnetworkorderinternal":
					case "rakstring":
					case "reset":					
					case "nonvariadic":
					default:
						$f = false;
						break;
				}
				if($f === false){
					continue;
				}
				$funcs[] = array($t, $f);
			}
			if(count($funcs) > 1 and $funcs[count($funcs) - 1][1] === "byte[]"){
				array_pop($funcs);
			}
			$networkFunctions[$pid][3] = $funcs;
			info(" found ".count($funcs)." field(s)");	
		}
	}

}

info("[*] Toppings selected: ".implode(",", $toppings));

$data = array();

if(in_array("version", $toppings, true) !== false){
	$data["version"] = array(
		"protocol" => $protocol,
		"release" => $version,
	);
}

if(in_array("packets", $toppings, true) !== false){
	ksort($networkFunctions);
	$packets = array("info" => array(
		"count" => count($networkFunctions),
	), "packet" => array());
	foreach($networkFunctions as $packet){
		$packets["packet"][$packet[0]] = array(
			"class" => $packet[2],
			"id" => $packet[0],
			"from_client" => ($packet[1] & 0x01) > 0 ? true:false,
			"from_server" => ($packet[1] & 0x02) > 0 ? true:false,
		);
		if(in_array("packetinstructions", $toppings, true)){
			$packets["packet"][$packet[0]]["instructions"] = array(
				array(
					"operation" => "Packet: ".$packet[2],
				)
			);
			$cnt = 0;
			$next = false;
			foreach($packet[3] as $instruction){
				if($instruction[0] === 0){
					if(substr($instruction[1], 0, 4) === "bits" or $instruction[1] === "Metadata" or $instruction[1] === "Item"){
						$instructions = array(
							"field" => $instruction[1]."(".chr(0x61 + $cnt).")".($next !== false ? ".".$next:""),
							"operation" => "write",
							"type" => "byte[]",
						);
					}else{
						$instructions = array(
							"field" => chr(0x61 + $cnt).($next !== false ? ".".$next:""),
							"operation" => "write",
							"type" => $instruction[1],
						);
					}
					++$cnt;
					$next = false;
					$packets["packet"][$packet[0]]["instructions"][] = $instructions;
				}elseif($instruction[0] === 1){ //Applied function
					$next = $instruction[1];
				}
				
				
			}
		}
	}
	$data["packets"] = $packets;
}

if(in_array("biomes", $toppings, true) !== false){
	$data["biomes"] = $biomes;
}


$output = json_encode(array($data), JSON_PRETTY_PRINT);

if(getp("o", "output") !== null){
	file_put_contents(getp("o", "output"), $output);
}else{
	echo $output;
}



info(PHP_EOL."[*] Everything done!");
