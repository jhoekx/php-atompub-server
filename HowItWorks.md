# Introduction #

This document gives a high level overview of the architecture of php-atompub-server. It describes how HTTP requests are handled and map to the AtomPub classes.


# HTTP Requests #

The entry point for handling any HTTP request is the `start.php` file. This file describes the storage and starts an instance of `App_Server`.

`App_Server` maps the request URI to a resource and creates an object of the requested class. The object that's returned always extends `HTTPResource`. `HTTPResource` contains utility methods for gzipping content and validating if an object in the cache is fresh.

Each class that inherits from `HTTPResource` implements some HTTP methods. For example: `App_Collection` has methods http\_GET and http\_POST. An `App_Entry` has http\_GET, http\_PUT and http\_DELETE methods.

The start script now calls the appropriate method of the object that was returned by the Server and hands it an HTTPRequest object. The resource either returns an HTTPResponse or an Exception.

If an HTTPResponse was returned, the resource will have tested if the content can be gzipped or if a 304 can be returned.

# AtomPub #

AtomPub defines quite a few types of resources. There's the Service Document, there are Collections, Categories and different types of Entries.

They all have methods for conveniently using them when accessed from inside another PHP script. A Collection will have methods to create an Entry and be updated whenever an Entry is updated. These methods do not require HTTP requests, but work on objects instead.

Entries are always associated with a Collection.