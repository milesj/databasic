# Databasic v2.6.1 #

A wrapper class for accessing, abstracting and manipulating a MySQL database.

## Requirements ##

* PHP 5.2.x
* MySQLi Extension - http://php.net/manual/book.mysqli.php

## Features ##

* Creates a connection to a database using MySQLi
* Can store and connect to multiple databases
* Stores the connection within a Multiton instance
* Logs queried information and total executed queries
* Binds variables to SQL statements; similar to PDO
* Cleans all binded data to prevent mysql injection
* Fetches data as an object or an associative array
* Logs each queries execution time
* Has support for a debug mode
* Pre-built methods for CREATE TABLE, DELETE, DESCRIBE, DROP, UPDATE, INSERT, SELECT, TRUNCATE and OPTIMIZE
* And much more...

## Documentation ##

Thorough documentation can be found here: http://milesj.me/code/php/databasic

