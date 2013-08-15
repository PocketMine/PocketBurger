<?php

$params = getopt("e:o:lt:", array(
	"extract:",
	"output:",
	"list",
	"toppings:"
));


function getp($short, $long = false){
	global $params;
	if(isset($params[$long])){
		return $params[$long];
	}elseif(isset($params[$short])){
		return $params[$short];
	}
	return null;
}

function info($str, $more=PHP_EOL){
	fwrite(STDERR, $str.$more);
}