<?php

class App_FileStore {

	private $dir;

	public function __construct($dir) {
		$this->dir = $dir;
		if (!file_exists($dir)) {
			mkdir($dir);
		}
	}
	
	public function store($uri, $data) {
		$parts = split("/",$uri);
		
		$filename = array_pop($parts);
		
		$path = $this->dir;
		for ($i=0; $i<count($parts); $i++ ) {
			$path = $path."/".$parts[$i];
			if ( !file_exists($path) ) {
				mkdir($path);
			}
		}
		
		file_put_contents($path."/".$filename, $data);
	}
	
	public function get($uri) {
		if ( file_exists($this->dir.$uri) ) {
			return file_get_contents($this->dir.$uri);
		} else {
			return "";
		}
	}
	
	public function modified($uri) {
		if ( file_exists($this->dir.$uri) ) {
			return filemtime($this->dir.$uri);
		} else {
			return 0;
		}
	}
	public function exists($uri) {
		return file_exists($this->dir.$uri);
	}
	
	public function remove($uri) {
		if ( file_exists($this->dir.$uri) ) {
			unlink($this->dir.$uri);
		}
	}
	public function remove_dir($uri) {
		$key = $this->dir.$uri;
		if ( file_exists($key) ) {
		
			$d = dir($key);
			while (false !== ($file = $d->read())) {
				if ($file != "." && $file != "..") {
					unlink($key."/".$file);
				}
			}
			$d->close();
			
			rmdir($key);
		}
	}

}

?>
