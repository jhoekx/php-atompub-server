<?php

error_reporting(E_ALL);

require_once("../classes/app_server.php");
require_once("../classes/app_store_file.php");
require_once("../classes/app_response.php");
require_once("../classes/app_request.php");
require_once("../classes/app_servicedoc.php");

$base_uri = "http://localhost/driedaagse/app/";
//$base_uri = "http://driedaagse.hamok.be/app/";

$request = new App_Request();
$service = new App_Servicedoc("service.xml", $base_uri);
$service->collection_specific_dir = "collectionconf";

$store = new App_Store_File("store", $base_uri, $service);

$request->fill_from_server();

$server = new App_Server($store, $service, $base_uri, $request);

$app_path = str_replace($base_uri,"","http://".$_SERVER["HTTP_HOST"].$_SERVER['REQUEST_URI']);
// URI routing
$url = parse_url($app_path);
if( array_key_exists("path",$url) ) {
	$path = $url["path"];
} else {
	$path = "";
}
$parts = split("/",$path);

$method = $request->method;

switch (count($parts)) {
	case 1:
		switch ($parts[0]) {
			case "":
				// Friendly index
				$etag = '"'.md5(filemtime("index.html")).'"';
				
				if ( array_key_exists('If-None-Match',$request->headers) && 
								$etag == $request->headers['If-None-Match'] ) {
					$response->http_status = "304 Not Modified";
					$response->headers["Content-Type"] = "text/html; charset=utf-8";
					$response->headers['Cache-Control'] = "must-revalidate";
					$response->headers['ETag'] = $etag;
					$response->response_body = "";
				} else {
					$response->http_status = "200 Ok";
					$response->headers["Content-Type"] = "text/html; charset=utf-8";
					$response->headers["ETag"] = $etag;
					$response->response_body = file_get_contents("index.html");;
				}
				break;
			case "service":
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
				break;
			default:
				$response->http_status = "404 File Not Found";
				$response->headers["Content-Type"] = "text/plain";
				$response->response_body = "File Not Found";
				break;
		}
		break;
	case 2:
		switch( $parts[0] ) {
			case "cats":
				// unpaged categories
				
				break;
			default:
				// collections
				$collection_name = $parts[0];
				switch ($parts[1]) {
					case "":
						// unpaged collections
						$page = 1;
						// passes through
					default:
						//paged collection
						if (!isset($page)) {
							$page = (int) $parts[1];
						}
						switch($request->method) {
							case "HEAD":
							case "GET":
								$response = $server->show_collection_page($collection_name, $page);
								break;
							case "POST":
								$response = $server->create_resource($collection_name);
								break;
							default:
								// Unknown method
								$response = new App_Response();
								$response->http_status = "405 Method Not Allowed";
								$response->headers["Content-Type"] = "text/plain";
								$response->headers["Allow"] = "HEAD, GET, POST";
								$response->response_body = "Only HEAD, GET and POST are supported on a collection.";
								break;
						}
				}
				break;
		}
		break;
	case 3:
		switch ($parts[0]) {
			case "cats":
				$category_name = $parts[1];
				switch ($parts[2]) {
					case "":
						// unpaged category
						$page = 1;
						// passes through
					default:
						// paged categories
						if (!isset($page)) {
							$page = (int) $parts[2];
						}
						switch($request->method) {
							case "HEAD":
							case "GET":
								$response = $server->show_category_page($category_name, $page);
								break;
							default:
								// Unknown method
								$response = new App_Response();
								$response->http_status = "405 Method Not Allowed";
								$response->headers["Content-Type"] = "text/plain";
								$response->headers["Allow"] = "HEAD, GET";
								$response->response_body = "Only HEAD and GET are supported on a category";
								break;
						}
						break;
				}
				break;
			default:
				$response->http_status = "404 File Not Found";
				$response->headers["Content-Type"] = "text/plain";
				$response->response_body = "File Not Found";
				break;
		}
		break;
	case 5:
		// resource
		$collection_name = $parts[0];
		$year = (int) $parts[1];
		$month= (int) $parts[2];
		$day  = (int) $parts[3];
		$name = $parts[4];
	
		switch($request->method) {
			case "HEAD":
			case "GET":
				$response = $server->get_resource($collection_name, $year, $month, $day, $name);
				break;
			case "PUT":
				$response = $server->update_resource($collection_name, $year, $month, $day, $name);
				break;
			case "DELETE":
				$response = $server->remove_resource($collection_name, $year, $month, $day, $name);
				break;
			default:
				// Unknown method
				$response = new App_Response();
				$response->http_status = "405 Method Not Allowed";
				$response->headers["Content-Type"] = "text/plain";
				$response->headers["Allow"] = "GET, PUT, DELETE";
				$response->response_body = "Only GET, PUT and DELETE are supported on a resource.";
				break;
		}
		break;
	default:
		$response->http_status = "404 File Not Found";
		$response->headers["Content-Type"] = "text/plain";
		$response->response_body = "File Not Found";
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
