<?php

interface App_Store {
	public function get_collection($name);
	public function get_collection_page($name, $page);
	public function collection_last_edited($name);
	public function collection_exists($name);
	
	public function resource_exists($collection, $year, $month, $day, $name);
	public function store_resource($collection, $year, $month, $day, $name, $contents);
	public function get_resource($collection, $year, $month, $day, $name);
	public function remove_resource($collection, $year, $month, $day, $name);
}

interface App_Categories_Store {
	public function add_to_category($cat, $uri, $time);
	public function remove_from_category($cat, $uri);
	public function category_last_edited($name);
}

?>