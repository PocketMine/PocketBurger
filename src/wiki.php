<?php

define("FILE_PATH", dirname(__FILE__)."/");
ini_set("memory_limit", -1); //Woo
include("init.php");

$page_template = <<<PAGE
== Data Packets ==

This information has been generated using [https://github.com/PocketMine/PocketBurger PocketBurger], then edited manually.

'''Minecraft: Pocket Edition {{version}}, protocol #{{protocol}}'''

{{packets}}
PAGE;

$packet_template = <<<PACKET
=== {{name}} ({{id}}) ===

''{{direction}}''


{| class="wikitable"
|-
| Packet ID
| Field Name
| Field Type
| Notes
|-
| rowspan="{{rowspan}}" | {{id}}
{{fields}}
|}


PACKET;


$stdin = stream_get_contents(STDIN);

$data = array_pop(json_decode($stdin, true));
$replace = array();
$replace["{{version}}"] = $data["version"]["release"];
$replace["{{protocol}}"] = $data["version"]["protocol"];
$replace["{{packets}}"] = "";

foreach($data["packets"]["packet"] as $id => $packet){
	if($packet["from_client"] === true and $packet["from_server"] === true){
		$direction = "Two-Way";
	}elseif($packet["from_client"] === true){
		$direction = "Client to Server";
	}elseif($packet["from_server"] === true){
		$direction = "Server to Client";
	}else{
		$direction = "None";
	}
	$r = array(
		"{{id}}" => "0x".dechex($id),
		"{{name}}" => $packet["name"],
		"{{direction}}" => $direction,
		"{{fields}}" => "",
		
	);
	$rowspan = 0;
	
	foreach($packet["instructions"] as $instruction){
		++$rowspan;
		$r["{{fields}}"] .= str_replace(array("{{name}}", "{{type}}", ), array($instruction["field"], $instruction["type"]), "| {{name}}\r\n| {{type}}\r\n| \r\n|-\r\n");
	}
	$r["{{rowspan}}"] = $rowspan;
	$replace["{{packets}}"] .= str_replace(array_keys($r), array_values($r), $packet_template);

}

echo str_replace(array_keys($replace), array_values($replace), $page_template);
