# Introduction #

This article provides an overview of the code needed to use php-atompub-server with [phpBB](http://www.phpbb.com/) (tested with 3.0.1). The [code](http://code.google.com/p/php-atompub-server/source/browse/trunk/examples/phpbb/) is in the examples subdirectory.

There is no support for foreign markup in atom entries and posting will not preserve markup in the content, only plain text.

# Details #

phpBB stores data in a database, usually MySQL. This means we have to use a custom store that talks to the database. To do this, we need to set up a [custom collection](http://php-atompub-server.googlecode.com/svn/trunk/examples/phpbb/collection_phpbb.php) that creates the store, in this case named phpbb, so its location will be `{base}/phpbb/`. Make sure the includes point to the right files. The parameters that need to be changed are [the forum](http://php-atompub-server.googlecode.com/svn/trunk/examples/phpbb/collection_phpbb.php#char=412,426) you want to use as a store and the [base URI](http://php-atompub-server.googlecode.com/svn/trunk/examples/phpbb/collection_phpbb.php#char=368,402) of the AtomPub server.

The [list store](http://php-atompub-server.googlecode.com/svn/trunk/examples/phpbb/phpbbliststore.php) and the [atom store](http://php-atompub-server.googlecode.com/svn/trunk/examples/phpbb/phpbbatomstore.php) talk to the [forum class](http://php-atompub-server.googlecode.com/svn/trunk/examples/phpbb/phpbb.php). That class uses a BBCode parser. I have used [this one](http://www.webtech101.com/PHP/simple-bb-code), but others should also work fine. The standard phpBB one is unusable, since it requires code from all over the phpBB-place. You also might want to change the [database driver and password](http://php-atompub-server.googlecode.com/svn/trunk/examples/phpbb/phpbb.php#char=231,287).

The last thing to do is to add the collection to the service document:
```
	<collection href="phpbb/">
		<atom:title>AtomPub Test: PHPBB</atom:title>
	</collection>
```

Now you can use at least Windows Live Writer to add topics to the forum.