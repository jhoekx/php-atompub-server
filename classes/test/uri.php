<?php

require_once("../appuri.php");

header("Content-Type: text/plain");

$base = new URI("http://www.example.com/test/more");
echo "base: ".$base."\n";

$rel = new URI("../niks");
echo "rel: ".$rel."\n";
echo $rel->resolve($base)."\n\n\n";

$offset = new URI("/niks");
echo "base: ".$base."\n";
echo "offset: ".$offset."\n";
echo $offset->resolve($base)."\n\n\n";

$xml = DOMDocument::load("base.xml");

$links = $xml->getElementsByTagName("a");
foreach ($links as $link) {
	$correct = $link->textContent;
	$result = URI::resolve_node( $link->getAttributeNode("href"), "" );
	if ( $correct != $result ) {
		echo "FAIL ";
	} else {
		echo "PASS ";
	}
	echo $correct." = ".$result."\n\n\n";
}

$base = new URI("http://www.example.com/test/more/nomore/never");
$full = new URI("http://www.example.com/niks/geen");
$new = new URI($full->base_on($base));
echo "base:     ".$base."\n";
echo "absolute: ".$full."\n";
echo "relative: ".$new."\n";
echo "absolute: ".$new->resolve($base)."\n\n\n";

$full = new URI("http://www.example.com/test/niks/geen");
$new = new URI($full->base_on($base));
echo "base:     ".$base."\n";
echo "absolute: ".$full."\n";
echo "relative: ".$new."\n";
echo "absolute: ".$new->resolve($base)."\n\n\n";

$base = new URI("http://www.example.com/test/more");
$full = new URI("http://www.example.com/test/niks/geen");
$new = new URI($full->base_on($base));
echo "base:     ".$base."\n";
echo "absolute: ".$full."\n";
echo "relative: ".$new."\n";
echo "absolute: ".$new->resolve($base)."\n\n\n";
