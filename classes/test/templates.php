<?php

require_once("../appuritemplate.php");
require_once("../appuri.php");

header("Content-Type: text/plain");

$uri = new URI("http://www.example.com/test/pages/1");
$uri2 = new URI("http://www.example.com/test/page/1");
$uri3 = new URI("http://www.example.com/test/pages/tachtig");

$temp = "http://www.example.com/{colname}/pages/{nr}";

$template = new App_URITemplate($temp);

print_r($template->matches($uri));
print_r($template->matches($uri2));
print_r($template->matches($uri3));
