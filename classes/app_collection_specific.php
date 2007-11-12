<?php
interface App_Collection_Specific{
	public function on_read($response);
	public function on_create($entry);
	public function on_update($entry);
	public function on_delete($entry);
}
?>