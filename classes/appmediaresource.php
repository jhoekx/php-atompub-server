<?php

require_once("appentry.php");

class App_MediaResource extends App_Entry {
	
	public $content;
	
	public function __construct($uri, $collection) {
		parent::__construct($uri, $collection);
		
		$this->extension = $uri->get_extension();
	}
	
	public function get_content() {
		if ( !isset($this->content) ) {
			$key = "/".$this->collection->name."/files/".$this->name.".".$this->extension;

			$data = $this->store->get($key);
			if ($data == "") {
				throw new HTTPException("Error loading data.",500);
			}
			
			$this->content = $data;
		}
		return $data;
	}
	
	public function save() {
		$key = "/".$this->collection->name."/files/".$this->name.".".$this->extension;
		$this->store->store($key, $this->content);
	}
	public function update($content) {
		$this->collection->update_entry($this);
	
		$this->content = $content;
		
		$this->collection->on_entry_update($this);
		
		$this->save();
	}
	
	protected function last_modified() {
			$key = "/".$this->collection->name."/files/".$this->name.".".$this->extension;
			return $this->store->modified($key);
	}
	
	public function http_GET($request) {
		$response = new HTTPResponse();
		
		$time = $this->last_modified();
		
		$etag = '"'.md5($time).'"';
		$last_modified = $this->time_to_gmt($time);
		
		if ( $this->try_cache($request, $response, 
			array("ETag" => $etag, "Last-Modified" => $last_modified)) ) 
		{
			return $response;
		}
		
		$mime_t = new App_Mimetype($this->extension);
		if ( $mime_t->type == "text" ) {
			$mime_t->parameters["charset"] = "utf-8";
		}
		
		$response->http_status = "200 Ok";
		$response->headers["Content-Type"] = $mime_t->to_string();
		$response->headers["ETag"] = $etag;
		$response->headers["Last-Modified"] = $last_modified;
		$response->response_body = $this->get_content();
		
		$this->collection->on_entry_get($request, $response);
		
		return $response;
	}
	
	public function http_PUT($request) {
		$response = new HTTPResponse();
		
		$time = $this->last_modified();
		$etag = '"'.md5($time).'"';
		
		/* Requires If-Match to match!!!*/
		if ( (!$request->header_exists('If-Match')) || 
					( $request->header_exists('If-Match') && 
						$etag != str_replace(";gzip","",$request->headers['If-Match']) ) ) {
			throw new HTTPException("Not the most recent version in cache.", 412);
		}
		if ( !$request->header_exists("Content-Type") ) {
			throw new HTTPException("No Content-Type header sent.", 400);
		}
		
		$content_type = $request->headers["Content-Type"];
		$mime_obj = new App_Mimetype($content_type);
		
		// convert to utf-8
		if ( $mime_obj->type == "text" && $mime_obj->parameter_exists("charset") ) {
			$charset = $mime_obj->parameters["charset"];
			
			$request->request_body = iconv($charset, "utf-8", $request->request_body);
		}
		
		$data = $request->request_body;
		
		$this->update($data);
		
		$this->response->http_status = "200 Ok";
		$this->response->response_body = "";
		
		$this->collection->on_entry_put($request, $response);
		
		return $response;
	}
	
	public function http_DELETE($request) {
		throw new HTTPException("Delete the media link entry instead!",400);
	}
}
