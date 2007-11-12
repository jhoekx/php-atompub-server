<?php

error_reporting(E_ALL);

require_once("../classes/app_server.php");
require_once("../classes/app_store_file.php");
require_once("../classes/app_response.php");
require_once("../classes/app_request.php");
require_once("../classes/app_servicedoc.php");

// Edit this
$base_uri = "http://localhost/app/";

$request = new App_Request();
$service = new App_Servicedoc("service.xml", $base_uri);
$service->collection_specific_dir = "collectionconf";

$store = new App_Store_File("store", $base_uri, $service);

$request->fill_from_server();

$server = new App_Server($store, $service, $base_uri, $request);

// Resource
$test = uri_match_file($request->request_uri);
if ( $test != FALSE ) {
	$collection_name = $test["Collection"];
	$year = $test["Year"];
	$month= $test["Month"];
	$day= $test["Day"];
	$name= $test["Name"];

	switch($request->method) {
		case "GET":
			$response = $server->get_resource($collection_name, $year, $month, $day, $name);
			app_output($response);
			exit;
			break;
		case "PUT":
			$response = $server->update_resource($collection_name, $year, $month, $day, $name);
			app_output($response);
			exit;
			break;
		case "DELETE":
			$response = $server->remove_resource($collection_name, $year, $month, $day, $name);
			app_output($response);
			exit;
			break;
		default:
			// Unknown method
			$response = new App_Response();
			$response->http_status = "405 Method Not Allowed";
			$response->headers["Content-Type"] = "text/plain";
			$response->headers["Allow"] = "GET, PUT, DELETE";
			$response->response_body = "Only GET, PUT and DELETE are supported on a resource.";
			app_output($response);
			exit;
			break;
	}
	
}

// Collection
$test = uri_match_collection($request->request_uri);
if ( $test != FALSE ) {
	switch($request->method) {
		case "GET":
			$response = $server->show_collection_page($test, 1);
			app_output($response);
			exit;
			break;
		case "POST":
			$response = $server->create_resource($test);
			app_output($response);
			exit;
			break;
		default:
			// Unknown method
			$response = new App_Response();
			$response->http_status = "405 Method Not Allowed";
			$response->headers["Content-Type"] = "text/plain";
			$response->headers["Allow"] = "GET, POST";
			$response->response_body = "Only GET and POST are supported on a collection.";
			app_output($response);
			exit;
			break;
	}
	
}
// Collection page
$test = uri_match_collection_page($request->request_uri);
if ( $test != FALSE ) {
	$collection_name = $test["Collection"];
	$page = $test["Page"];
	switch($request->method) {
		case "GET":
			$response = $server->show_collection_page($collection_name, $page);
			app_output($response);
			exit;
			break;
		case "POST":
			$response = $server->create_resource($collection_name);
			app_output($response);
			exit;
			break;
		default:
			// Unknown method
			$response = new App_Response();
			$response->http_status = "405 Method Not Allowed";
			$response->headers["Content-Type"] = "text/plain";
			$response->headers["Allow"] = "GET, POST";
			$response->response_body = "Only GET and POST are supported on a collection.";
			app_output($response);
			exit;
			break;
	}
	
}

// category
$test = uri_match_category_page($request->request_uri);
if ( $test != FALSE ) {
	$collection_name = $test["Category"];
	$page = $test["Page"];
	switch($request->method) {
		case "GET":
			$response = $server->show_category_page($collection_name, $page);
			app_output($response);
			exit;
			break;
		default:
			// Unknown method
			$response = new App_Response();
			$response->http_status = "405 Method Not Allowed";
			$response->headers["Content-Type"] = "text/plain";
			$response->headers["Allow"] = "GET";
			$response->response_body = "Only GET is supported on a category";
			app_output($response);
			exit;
			break;
	}
	
}
$test = uri_match_category($request->request_uri);
if ( $test != FALSE ) {
	$collection_name = $test["Category"];
	$page = $test["Page"];
	switch($request->method) {
		case "GET":
			$response = $server->show_category_page($collection_name, $page);
			app_output($response);
			exit;
			break;
		default:
			// Unknown method
			$response = new App_Response();
			$response->http_status = "405 Method Not Allowed";
			$response->headers["Content-Type"] = "text/plain";
			$response->headers["Allow"] = "GET";
			$response->response_body = "Only GET is supported on a category";
			app_output($response);
			exit;
			break;
	}
	
}
// Other
$app_path = str_replace($base_uri,"","http://".$_SERVER["HTTP_HOST"].$request->request_uri);
$response = new App_Response();
if ($app_path == "service") {
	// Service document
	$etag = '"'.md5(filemtime("service.xml")).'"';
	
	if ( array_key_exists('If-None-Match',$request->headers) && 
					$etag == $request->headers['If-None-Match'] ) {
		$response->http_status = "304 Not Modified";
		$response->headers["Content-Type"] = "application/atomsvc+xml";
		$response->headers['Cache-Control'] = "must-revalidate";
		$response->headers['ETag'] = $etag;
		$response->response_body = "";
	} else {
		$response->http_status = "200 Ok";
		$response->headers["Content-Type"] = "application/atomsvc+xml";
		$response->headers["ETag"] = $etag;
		$response->response_body = file_get_contents("service.xml");
	}
	app_output($response);
	exit;
} else if ($app_path == "") {
	// Friendly index
	$index_text = "AtomPub server, service document at ./service .";
	$etag = '"'.md5($index_text).'"';
	
	if ( array_key_exists('If-None-Match',$request->headers) && 
					$etag == $request->headers['If-None-Match'] ) {
		$response->http_status = "304 Not Modified";
		$response->headers["Content-Type"] = "text/plain; charset=utf-8";
		$response->headers['Cache-Control'] = "must-revalidate";
		$response->headers['ETag'] = $etag;
		$response->response_body = "";
	} else {
		$response->http_status = "200 Ok";
		$response->headers["Content-Type"] = "text/plain; charset=utf-8";
		$response->headers["ETag"] = $etag;
		$response->response_body = $index_text;
	}
	app_output($response);
	exit;
}

// URI is Nothing
$response->http_status = "404 File Not Found";
$response->headers["Content-Type"] = "text/plain";
$response->response_body = "File Not Found";
app_output($response);

/* Functions */
function app_output($response) {
	header("HTTP/1.1 $response->http_status");
	header("Connection: close");
	foreach( $response->headers as $header => $value ) {
		header("$header: $value");
	}

	echo $response->response_body;
}

function uri_match_collection($uri) {
	$script_name = str_replace("collection.php","",$_SERVER["SCRIPT_NAME"]);
	$app_path = str_replace($script_name,"",$uri);
	
	$matches = array();
	$pattern = "/^([a-zA-Z\d_\-]+)\/$/";
	$matched = preg_match($pattern,$app_path,$matches);
	
	if ( $matched != 0 ){
		return $matches[1];
	} else {
		return FALSE;
	}
}
function uri_match_collection_page($uri) {
	$script_name = str_replace("collection.php","",$_SERVER["SCRIPT_NAME"]);
	$app_path = str_replace($script_name,"",$uri);
	
	$matches = array();
	$pattern = "/^([a-z\d_\-]+)\/([\d]+)$/";
	$matched = preg_match($pattern,$app_path,$matches);
	
	
	if ( $matched != 0 ){
		$dis = array();
		$dis['Collection'] = $matches[1];
		$dis['Page'] = $matches[2];
		return $dis;
	} else {
		return FALSE;
	}
}
function uri_match_category_page($uri) {
	$script_name = str_replace("collection.php","",$_SERVER["SCRIPT_NAME"]);
	$app_path = str_replace($script_name,"",$uri);
	
	$matches = array();
	$pattern = "/^cats\/([a-z\d_\-]+)\/([\d]+)$/";
	$matched = preg_match($pattern,$app_path,$matches);
	
	
	if ( $matched != 0 ){
		$dis = array();
		$dis['Category'] = $matches[1];
		$dis['Page'] = $matches[2];
		return $dis;
	} else {
		return FALSE;
	}
}
function uri_match_category($uri) {
	$script_name = str_replace("collection.php","",$_SERVER["SCRIPT_NAME"]);
	$app_path = str_replace($script_name,"",$uri);
	
	$matches = array();
	$pattern = "/^cats\/([a-z\d_\-]+)\/$/";
	$matched = preg_match($pattern,$app_path,$matches);
	
	
	if ( $matched != 0 ){
		$dis = array();
		$dis['Category'] = $matches[1];
		$dis['Page'] = 1;
		return $dis;
	} else {
		return FALSE;
	}
}
function uri_match_file($uri) {
	$script_name = str_replace("collection.php","",$_SERVER["SCRIPT_NAME"]);
	$app_path = str_replace($script_name,"",$uri);
	
	$matches = array();
	// /collection/year/month/day/title
	$pattern = "/^(.+)\/(\d{4})\/(\d{2})\/(\d{2})\/([^\;\/\?\:\@\&\=\+\$\,]+)$/";
	$matched = preg_match($pattern,$app_path,$matches);
	
	if ( $matched != 0 ){
		$dis = array();
		
		$dis["Collection"]=$matches[1];
		$dis["Year"]=$matches[2];
		$dis["Month"]=$matches[3];
		$dis["Day"]=$matches[4];
		$dis["Name"]=$matches[5];
		return $dis;
	} else {
		return FALSE;
	}
}
?>
