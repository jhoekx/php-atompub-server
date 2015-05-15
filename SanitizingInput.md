# Introduction #

This document describes a way to sanitize input to a collection. It uses [HTMLPurifier](http://htmlpurifier.org/) (Make sure to keep it up-to-date for the best protection) to filter the input. The output of the code always has type="xhtml".


# Details #

Download the [example file](http://php-atompub-server.googlecode.com/svn/trunk/app/templates/) for the `news` collection `(collection_news.php)` from the subversion repository.

Download HTMLPurifier and save it in the `templates` directory.

All requests for the `news` collection pass through that file. Note that it's best to only load HTMLPurifier when it's really needed. It requires a lot of memory.