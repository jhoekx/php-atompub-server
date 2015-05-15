# Introduction #

This document shows how to quickly setup a server for testing. There is no authentication built in currently. You'll need mod\_rewrite or similar tools to rewrite everything to the url "handling" code.


# Details #

## Get the server code ##
Check out the latest version of the server software from [subversion](http://code.google.com/p/php-atompub-server/source).

## Setting up ##
The example code is in the [subversion repo](http://php-atompub-server.googlecode.com/svn/trunk/app/). Save it somewhere where it's web-accessible in Apache. Don't forget the `templates` directory!

Now edit the `.htaccess` file of the directory where you've installed the server:
```
RewriteEngine On

RewriteRule ^(.+) start.php

DirectoryIndex start.php
```

Make sure the server code is in the `../classes/` directory, or change the includes in the start.php file.

## Permissions ##
The web server should have write access to the directory.

## Setting up the service document ##
The example also includes a service document, `service.xml`. It contains a few collections.

## Test it ##
Now, the server should be up and running. Try to access the service document at `$base_uri/service` or $base\_uri/ . If that works all is fine, otherwise, ask for help.

In the [tools section](http://php-atompub-server.googlecode.com/svn/trunk/tools/), there's a file `postatom.html`. Save it somewhere on your server, together with the `data.xml` file, which contains a sample entry (straight from the spec) and point your browser at it. It's a crude way to test the functionality.

Good luck!