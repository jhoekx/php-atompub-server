# Introduction #

This document explains the built-in extensibility models. Three different customizations are possible: the feed template model, the event model and the custom store.

# The Feed Template model #

When creating a feed for a collection, `php-atompub-server` looks in the `templates` subdirectory for `feed_{$collection_name}.xml`. This file will be used as the basis, to which entries are added.

This extension mechanism allows the addition of for example copyright information, or a default author to the feed.

# The Event Model #

When creating a App\_Collection object, the Server will look into the `templates` subdirectory for a file `collection_{$collection_name}.php`. This file should contain a class `App_Collection_{$collection_name}` that extends `App_Collection`.

This class can override existing methods of the collection, but it's also possible (and far easier) to attach an eventlistener to the collection.

Take a look at this example:

```
class App_Collection_test extends App_Collection {

	public function __construct($name, $service) {
		parent::__construct($name, $service);
		
		$this->addEventListener("entry_create", $this, "on_entry_create");
	}
	
	public function on_entry_create($event) {
		$entry = $event->entry;
		
		$doc = $entry->get_document();
		
		$link = $doc->createElementNS("http://www.w3.org/2005/Atom","link");
		$link->setAttribute("rel","alternate");
		$link->setAttribute("type","text/html");
		
		$uri = new URI("/news/".$entry->name);
		$link->setAttribute("href", $uri);
		
		$doc->documentElement->appendChild($link);
	}

	public function give_name($slug) {
		return date("Y")."/".date("m")."/".date("d")."/".$slug;
	}

}
```

This class will be used for all operations in the "test" collection. It does two things: a link to a webpage is added to the entry and the naming conventions for entries in the collection are changed.

The link is added using the event system, while the naming is done by overriding an existing method.

The event system is similar to the one used in DOM 2 Events. An `EventListener` is registered on the collection. It includes the name of the event we're interested in and the object and methodname we want to have called when the event takes place.

The eventhandler method will receive an `Event` object. These come in two varieties: the HTTPEvent and the APPEvent. Each contains the name of the event.

The HTTPEvent object has the `HTTPRequest` and `HTTPResponse` objects used for handling this request. One can use the HTTPResponse to add extra long caching headers to images in a collection for example. HTTPEvents are fired before the response is returned.

An APPEvent object contains an App\_Entry object. This makes it possible to change parts of a document. They are fired after php-atompub-server is done handling the entry, but before the entry is saved.

An entry will dispatch the following events:
  * APPEvent "before\_entry\_update"
  * APPEvent "entry\_update"
  * APPEvent "entry\_remove"
  * HTTPEvent "entry\_get"
  * HTTPEvent "entry\_put"
  * HTTPEvent "entry\_delete"

A collection dispatches these events:
  * APPEvent "entry\_open"
  * APPEvent "entry\_create"
  * HTTPEvent "collection\_get"
  * HTTPEvent "collection\_post"

Additionally, all events dispatched by the entry will also be dispatched to the collection. This means you can listen for "entry\_put" on a collection.

# Custom Store #
Adding a custom store allows you to operate on other resources than just files. Let `php-atompub-server` do the AtomPub work and you'll be handed in a nice Atom entry to save to your backend database.

An example of this extension model can be found in the [phpBBStore](http://code.google.com/p/php-atompub-server/wiki/phpBBStore) example.