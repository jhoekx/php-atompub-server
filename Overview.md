# Introduction #

This page gives a quick overview of the features of php-atompub-server. To get something to play with up and running quickly, see [HTTPServer](http://code.google.com/p/php-atompub-server/wiki/HTTPServer).


# Quick Overview #

## What is php-atompub-server? ##

php-atompub-server aims to provide a framework to build web applications/pages entirely accessible through the [Atom Publishing Protocol](http://tools.ietf.org/html/rfc5023). It allows access through the standard HTTP interface to AtomPub, but it's also directly accessible from within PHP classes, using a convenient API, so it's also possible to add an HTML administration interface for a site.

## Features ##

### Collections ###
php-atompub-server uses the service document to determine the capabilities of a specific collection. No fiddling with configuration directives, just edit the service document to whatever you like and it just works.

Note that "whatever you like" is not quite correct. Collections should look like `$base_uri/{collection_name}/` .

### Storage ###
There is a pluggable storage architecture. A filesystem based approach is built-in, but it's fairly straightforward to change it.

### Feeds ###
The feeds produced are currently not necessarily [valid](http://feedvalidator.org/). Created entries will always have an `id`, `title`, `author` and `updated` element, but `summary` or `content`` elements are not always added, since sensible defaults are hard to provide. Correct values for these elements can be provided using collection specific code.

Feeds are paged at 10 entries per page, this value is editable in the storage class. Paged feeds are given URI's like: `$base_uri/{collection}/?page=3` in the example server. The sample HTTPServer also allows one to acces the complete feed with `$base_uri/{collection}/?page=0` , this will be indicated by the {http://purl.org/syndication/history/1.0}complete element as defined in [Feed Paging and Archiving](http://tools.ietf.org/html/rfc5005#section-2).

### POSTing to a collection ###
Entries that are posted to a collection will be given a URI like `$base_uri/{collection}/{slug}`. This naming scheme can be changed by subclassing the App\_Collection class.

php-atompub-server decides if the resource sent is a media entry based on the mimetype of the request. All text/`*` content will be converted to UTF-8 on posting, depending on the presence of the charset parameter.

Resources are given an extension based on the mimetype. If the mimetype is not in the list of known types, the default is application/octet-stream.

### GETting a resource ###
When a resource is requested, the `If-Modified-Since` and `If-None-Match` headers are taken into account and a `304` can be issued. If the request has an `Accept-Encoding` header that gives `gzip` a higher q-value than `identity`, the content will be gzipped.

Resources are given a `Last-Modified` date and an `ETag`. The `ETag` of the gzipped version is not the same as the `ETag` of the non-gzipped version: `;gzip` is appended.

It's possible to get a resource from a collection that no longer exists, as long as the resource is in the storage.

### PUTting a resource ###
Updating a resource requires the `If-Match` header to provide the correct ETag. php-atompub-server will ensure that the `edit`, `edit-media` link relations and the `src`-attribute of `content` are unchanged.

### DELETE ###
To delete a media entry, delete the corresponding media-link entry. Collection specific code will have to ensure that it listens to `on_delete` to remove for example static images.

### Collection Specific Code ###
The `templates` directory is the place where small changes to the collection behaviour can be made. Feeds will be templated using a `feed_{collectionname}.xml` in the directory. If a file `collection_{collectionname}.php` is present, it should define a class `App_Collection_{collectionname}`, which extends `App_Collection`. This class can listen for events when adding/deleting/updating/creating and getting resources.