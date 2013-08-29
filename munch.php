<?php

define("FILE_PATH", dirname(__FILE__)."/");
ini_set("memory_limit", -1); //Woo
include("src/init.php");


$toppings = array(
	"version" => "Provides the protocol and release version.",
	"packets" => "Provides minimal information on all network packets.",
	"packetinstructions" => "Provides the instructions used to construct network packets.",
	"biomes" => "Gets most biome types.",
	"blocks" => "Gets most available block types.",
	"sounds" => "Finds all named sound effects.",
);

if(getp("l", "list") !== null){
	$topp = "Toppings: ";
	foreach($toppings as $name => $desc){
		$topp .= "$name, ";
	}
	echo substr($topp, 0, -2).PHP_EOL;
	die();
}

include("src/parser.php");
$output = array();
$output[] = parser(array_pop($argv));

if(getp("c", "compare") !== null){
	$output[] = parser(getp("c", "compare"));
}

$output = json_encode($output, JSON_PRETTY_PRINT);
if(getp("o", "output") !== null){
	file_put_contents(getp("o", "output"), $output);
}else{
	echo $output;
}
info(PHP_EOL."[*] Everything done!");
