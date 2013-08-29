<?php

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

function parser($asmfile, array $toppings){
	if(!file_exists($asmfile) or strtolower(substr($asmfile, -4)) !== ".asm"){
		echo "Invalid ASM provided $asmfile".PHP_EOL;
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
	$asm = explode("\n", str_replace(array("\r", "\t", "   ", "  "), array("", " ", " ", " "), $asm));
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
			if($asm[$line] === "; ===========================================================================" or strpos($asm[$line], "AREA .text, CODE") !== false){
				unset($asm[$line]);
				break;
			}
		}
		unset($asm[$line]);
	}

	$classindex = array();
	$classes = array();
	$variables = array();
	$fn = false;
	$methodscount = 0;
	info("\r[*] More parsing... found $methodscount methods and ".count($variables)." strings", "");
	for(;$line < $cnt;++$line){
		$l = $asm[$line];
		if(strpos($l, "AREA .data, DATA") !== false){
			break;
		}
		unset($asm[$line]);
		if(preg_match('#^([A-Za-z0-9_]{1,}) DCB "(.{1,})",0#', $l, $matches) > 0){
			$variables[$matches[1]] = $matches[2];
			info("\r[*] More parsing... found $methodscount methods and ".count($variables)." strings", "");
		}elseif($fn === false){
			if($l === "; =============== S U B R O U T I N E ======================================="){
				$fn = true;
			}elseif($l !== "" and preg_match('#^; ([A-Za-z0-9_\:\~]{1,})\(([A-Za-z0-9_\:\~, \*\&]*)\)#', $l, $matches) > 0){
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
	unset($asm);


	if(in_array("version", $toppings, true) !== false){
		//get version directly, for older versions
		$version = "";
		foreach($variables as $index => $value){
			if(preg_match('#aV[0-9]_[0-9]_[0-9]#', $index) > 0){
				$version = $value;
				break;
			}
		}
		if(trim($version) == ""){ //Different methods to get version
			// 0.7.3+ compatible method
			$vVars = findPREG($classindex["Common::getGameVersionString"], '#MOVS R[0-9], \#([0-9]{1})#');
			if(!isset($vVars[2])){ //0.6.1+ method 
				$vVars = findPREG($classindex["Common::getGameVersionString"], '#ADD R[0-9], PC ; "([ a-zA-Z0-9\.]*)"#');
				$version = $vVars[0][1];
			}else{
				$version = "v".$vVars[0][1].".".@intval($vVars[2][1]).".".$vVars[1][1];
			}
		}
		info("[+] Minecraft: Pocket Edition $version");
		

		$protocol = findPREG($classindex["ClientSideNetworkHandler::onConnect"], '/MOVS R[1-9], #([0-9A-Fx]{1,})/');
		$protocol = substr($protocol[0][1], 0, 2) == "0x" ? hexdec($protocol[0][1]):intval($protocol[0][1]);
		info("[+] Protocol #$protocol");
	}

	if(in_array("sounds", $toppings, true) !== false){
		info("[*] Getting sounds...", "");
		$soundnames = findPREG($classindex["SoundEngine::init"], '/ADD R[0-9]{1,}, PC ; "([A-Za-z0-9_\.]*)"/', true);
		$sounds = array();
		foreach($soundnames as $line => $d){
			$sounds[$d[1]] = array(
				"name" => $d[1],
				"versions" => array(),
			);
		}
		info(" found ".count($sounds));
	}

	if(in_array("biomes", $toppings, true) !== false){
		info("[*] Getting biomes...", "");
		$biomenames = findPREG($classindex["Biome::initBiomes"], '/LDR R1, =\(([A-Za-z0-9_]*) \-/', true);
		$biomecolors = findPREG($classindex["Biome::initBiomes"], '/LDR R1, =(0x[A-F0-9]*)/', true);
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
		info(" found ".count($biomes));
	}

	if(in_array("blocks", $toppings, true) !== false){
		$btextures = array(
			1 => array(1, 0),
			2 => array(3, 0),
			3 => array(2, 0),
			4 => array(0, 1),
			5 => array(4, 0),
			6 => array(15, 0),
			7 => array(1, 1),
			8 => array(15, 12),
			9 => array(15, 12),
			10 => array(15, 14),
			11 => array(15, 14),
			12 => array(2, 1),
			13 => array(3, 1),
			14 => array(0, 2),
			15 => array(1, 2),
			16 => array(2, 2),
			17 => array(4, 1),
			18 => array(4, 3),
			
			21 => array(0, 10),
			22 => array(0, 9),
			
			24 => array(0, 12),
			
			26 => array(7, 9),
			
			30 => array(11, 0),
			31 => array(7, 2),
			35 => array(0, 4),
			37 => array(13, 0),
			38 => array(12, 0),
			39 => array(13, 1),
			40 => array(12, 1),
			41 => array(7, 1),
			42 => array(6, 1),
			43 => array(5, 0),
			44 => array(6, 0),
			45 => array(7, 0),
			46 => array(8, 0),
			47 => array(3, 2),
			48 => array(4, 2),
			49 => array(5, 2),
		);
		info("[*] Getting blocks...", "");
		//$blocknames = findPREG($classindex["Tile::initTiles"], '/ADD R1, PC ; "([A-Za-z]*)"/', true);
		$blocknames = findPREG($classindex["Tile::initTiles"], '/LDR R3, \[R4,R3\] ; Tile::([A-Za-z]*)/', true);
		$blockstrings = findPREG($classindex["Tile::initTiles"], '/LDR R1, =\(([A-Za-z0-9_]*) \-/', true);
		$blockids = findPREG($classindex["Tile::initTiles"], '/(MOVS|MOV\.W) R1, #([xA-F0-9]*)/', true);
		$blockhardness = findPREG($classindex["Tile::initTiles"], '/(LDR|MOV\.W) R1, (#|=)([xA-F0-9]{5,})/', true);
		$blockclasses = findPREG($classindex["Tile::initTiles"], '/BL [a-zA-Z0-9_]* ; ([A-Za-z0-9_]*)::\g{1}\(int/', true);
		$blocks = array();
		foreach($blocknames as $line => $d){
			foreach($blockclasses as $cline => $c){
				if($cline > $line){
					break;
				}
				$classl = $cline;
			}		
			foreach($blockids as $iline => $i){
				if($iline > $classl){
					break;
				}
				$id = hexdec(str_replace("0x", "", $i[2]));
			}
			$hardness = "\x00\x00\x00\x00";
			foreach($blockhardness as $hline => $h){
				if($hline > $line){
					break;
				}elseif($hline > $classl){
					$hardness = hex2bin(str_pad(str_replace("0x", "", $h[3]), 8, "0", STR_PAD_LEFT));
					if($h[1] === "MOV.W"){
						break;
					}
				}
			}
			$string = "";
			foreach($blockstrings as $sline => $s){
				if($sline > $line){
					break;
				}elseif($sline > $classl){
					$string = $variables[$s[1]];
				}
			}
			if($string === ""){
				$string = $d[1];
			}
			list(,$hardness) = pack("d", 1) === "\77\360\0\0\0\0\0\0" ? unpack("f", $hardness):unpack("f", strrev($hardness));
			if(!isset($blocks[$id])){
				$blocks[$id] = array(
					"name" => $d[1],
					"id" => $id,
					"display_name" => $string,
					"hardness" => round($hardness, 2),
				);
				if(isset($btextures[$id])){
					$blocks[$id]["texture"] = array("x" => $btextures[$id][0], "y" => $btextures[$id][1]);
				}
			}else{
				info(" !".$id.":".$d[1]."[".$string."]", "");
			}
		}
		info(" found ".count($blocks));
	}

	if(in_array("packets", $toppings, true) !== false){

		info("[*] Searching network functions...", "");
		$serverSide = array();
		$clientSide = array();
		foreach($classindex["ServerSideNetworkHandler::handle"] as $parameters => $class){
			if(preg_match("#, ([A-Za-z_]*)#", $parameters, $matches) > 0){
				$serverSide[$matches[1]] = true;
			}
		}
		foreach($classindex["ClientSideNetworkHandler::handle"] as $parameters => $class){
			if(preg_match("#, ([A-Za-z_]*)#", $parameters, $matches) > 0){
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
					$pid = findPREG($fn, '/(MOVS|MOV\.W) (R[23456]|LR)\, \#0x([0-9A-F]{2})/');
					$pid = hexdec($pid[0][3]);
					$networkFunctions[$pid] = array($pid, $dir, $n[0]);
				}
			}
		}
		info(" found ".count($networkFunctions));
		if(in_array("packetinstructions", $toppings, true) !== false){
			foreach($networkFunctions as $pid => $data){
				info("[*] Getting ".$data[2]." structure...", "");
				$things = findPREG($classindex[$data[2]."::write"], '/BL [a-zA-Z0-9_]* ; .*\:\:([A-Za-z0-9_\<\> ]*)\(/', true);
				$bits = findPREG($classindex[$data[2]."::write"], '/(MOVS|MOV\.W) R[2]\, #([x0-9A-F]{1,4})/', true);
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
							$f = "ushort";
							break;
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
							$f = "long";
							break;
						case "write<ulong long>":
						case "raknetguid>":
							$f = "ulong";
							break;					
						case "write<uchar>":
							$f = "ubyte";
							break;
						case "write<char>":
						case "write<signed char>":
							$f = "byte";
							break;
						case "packall":
						case "pack":
							$f = "Metadata";
							break;
						case "writenamedtag":
							$f = "NamedTag";
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
				"name" => $packet[2],
			);
			if(in_array("packetinstructions", $toppings, true)){
				$packets["packet"][$packet[0]]["instructions"] = array();
				$cnt = 0;
				$next = false;
				foreach($packet[3] as $instruction){
					if($instruction[0] === 0){
						if(substr($instruction[1], 0, 4) === "bits" or $instruction[1] === "Metadata" or $instruction[1] === "NamedTag" or $instruction[1] === "Item"){
							$instructions = array(
								"field" => $instruction[1]."(".chr(0x61 + $cnt).")".($next !== false ? ".".$next:""),
								"operation" => "write",
								"type" => "byte[]",
							);
						}else{
							if($instruction[1]{0} === "u"){
								$instruction[1] = substr($instruction[1], 1);
								$more = "(unsigned) ";
							}else{
								$more = "";
							}
							$instructions = array(
								"field" => $more.chr(0x61 + $cnt).($next !== false ? ".".$next:""),
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

	if(in_array("sounds", $toppings, true) !== false){
		$data["sounds"] = $sounds;
	}



	if(in_array("blocks", $toppings, true) !== false){
		$data["blocks"] = array(
			"info" => array(
				"count" => count($blocks),
				"real_count" => count($blocks),
			),
			"block" => $blocks,
		);
	}

	return $data;

}