<?php

require_once("../classes/appuri.php");

require_once("../classes/appserver.php");
require_once("../classes/appfilestore.php");

require_once("../classes/httprequest.php");
require_once("../classes/httpresponse.php");
require_once("../classes/httpexception.php");

/*
 * Configuration
 */
/**
 * $base_uri containts the path of the directory this start.php file is in.
 * Example : "http://sites.localhost/php-atompub-server/app/"
 */
$script_path = explode("start.php", $_SERVER['PHP_SELF']);
$base_uri = new URI("http://".$_SERVER["HTTP_HOST"].$script_path[0]);

/*
 * Start the server logic.
 */

$server = new App_Server($base_uri);

$request = new App_HTTPRequest();
$request->fill_from_server();

try {
	$resource = $server->get_resource($request->request_uri);

	if ( $resource->method_allowed($request->method) ) {
		$name ="http_$request->method";
		$response = $resource->$name($request);
	} else {
		$response = new App_HTTPResponse();
		$response->http_status = "405 Method Not Allowed";
		$response->headers["Content-Type"] = "text/plain";
		$response->headers["Allow"] = join($resource->allowed_methods(),", ");
		$response->response_body = "Method not supported.";
	}
} catch (App_HTTPException $err) {
	$response = new App_HTTPResponse();
	$response->http_status = $err->http_status;
	$response->headers["Content-Type"] = "text/plain";
	$response->response_body = $err->getMessage();
} catch (Exception $err) {
	$response = new App_HTTPResponse();
	$response->http_status = "500 Internal Server Error";
	$response->headers["Content-Type"] = "text/plain";
	$response->response_body = $err->getMessage();
}

app_output($response);

/* Functions */
function app_output($response) {
	header("HTTP/1.1 $response->http_status");
	//header("Connection: close");
	foreach( $response->headers as $header => $value ) {
		header("$header: $value");
	}
	if ($_SERVER["REQUEST_METHOD"] != "HEAD" ) {
		echo $response->response_body;
	}
}


?>
