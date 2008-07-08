<?php

require_once("../classes/appuri.php");

require_once("../classes/appserver.php");
require_once("../classes/appfilestore.php");

require_once("../classes/httprequest.php");
require_once("../classes/httpresponse.php");
require_once("../classes/httpexception.php");


$base_uri = new URI("http://localhost/appv2/app/");
//$base_uri = new URI("http://dev.hamok.be/app/");

$server = new App_Server($base_uri);

$request = new HTTPRequest();
$request->fill_from_server();

try {
	$resource = $server->get_resource($request->request_uri);

	if ( $resource->method_allowed($request->method) ) {
		$name ="http_$request->method";
		$response = $resource->$name($request);
	} else {
		$response = new HTTPResponse();
		$response->http_status = "405 Method Not Allowed";
		$response->headers["Content-Type"] = "text/plain";
		$response->headers["Allow"] = join($resource->allowed_methods(),", ");
		$response->response_body = "Method not supported.";
	}
} catch (HTTPException $err) {
	$response = new HTTPResponse();
	$response->http_status = $err->http_status;
	$response->headers["Content-Type"] = "text/plain";
	$response->response_body = $err->getMessage();
} catch (Exception $err) {
	$response = new HTTPResponse();
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
