# Changelog #

*These logs may be outdated or incomplete.*

## 3.0.0 ##

* Updated to PHP 5.3
* Fixed Composer issues

## 2.6 ##

* Added Composer support
* Replaced errors with exceptions
* Refactored to use strict equality

## 2.5.1 ##

* Fixed a bug where columns with the same name within an OR clause get parsed incorrectly.

## 2.5 ##

* Renamed class to Databasic instead of Database (Not backwards compatible)
* Changed private methods and properties to protected
* Cleaned up and refactored all methods
* Refactored how binding variables works
* Exceptions are now thrown instead of errors
* Adding error check to columns()
* _startLoadTime() now returns and sets the $dataBit
* _buildFields() and _buildConditions() now return the formatted data array
* _endLoadTime() now unsets related data

## 2.4 ##

* Converted to MySQLi
* Fixed multiple database support
* Fixed a problem with statements not being separated by commas
* Fixed problems with binds in select
* Added a new field retrieval setup
* Added asObject() to change the return type
* Added default column options
* Added an enum option instead of length
* Added a tables() method

## 2.3.2 ##

* Fixed a problem with __formatType() not returning the correct type and causing major problems
* Fixed store() referencing the wrong property
* Fixed the regex that attempts to locate MySQL functions
* Fixed the limit not working on update()

## 2.3 ##

* Skipped version 2.2 release because of bugs
* The following operators within conditions are now available: LIKE, NOT LIKE, IS NULL, IS NOT NULL, IN, NOT IN
* You may now pass the value as an array for IN operators to automatically build the list
* Fixed a problem with nested arrays not being parsed into conditions correctly
* Fixed a problem with conditions not being parsed correctly
* Fixed a problem with a method using the wrong name
* Fixed a bug with the __buildConditions() regex
* Added the addBind() method to properly store binds for statements
* Rewrote __buildConditions() to work with the new operators
* Rewrote bind(), update(), delete(), insert(), select(), __formatType() to use the new binds system

## 2.1 ##

* Added support for AND/OR operators within conditions, in turn added the __formatConditions() method
* Added more support for MySQL functions
* Added the __encode() and __encodeMethod() methods
* Added RAND() support for ordering within select()
* Added a $persistent property so that you may use mysql_pconnect() instead of mysql_connect()
* Rewrote create() to work more efficiently and added a "collate" setting
* Rewrote bind to work correctly with MySQL functions
* Rewrote delete(), insert() and update() to not run actions when execute is $false (optimized)
* Rewrote __buildConditions() to support AND/OR operators, as well as the new format for value operations
* Rewrote how operators (!=, <=, etc) are used in conditions
* Optimized all methods to run faster and be cleaner

## 2.0 ##

* Added support for multiple databases
* Added the store() method to store database connection information
* Removed the database connection constants
* Removed the $limit and $offset arguments for select(), they are now an index in the $options array
* Fixed select()'s count return not working when being an object
* Removed logic from __construct(), connecting is now done within getInstance(); changed for multiple db support
* Added methods for drop(), truncate() and describe()
* Rebuilt the optimize() method
* Added a create() method that takes a schema array and creates database table

## 1.10.3 ##

* Fixed the insert() method (left comments/debug code in during testing)
* Fixed the 'group' option in select(), should properly GROUP BY now
* Fixed a problem when setting a column as NULL

## 1.10.2 ##

* Fixed a bug with the $dataBit argument not working correctly on execute()
* Fixed the execution times when calling execute() stand alone

## 1.10.1 ##

* Added an "execution time" for queries in debug mode
* Added the number of affected rows for queries in debug mode
* Added a getAffected() method
* Added a version number property
* Fixed a bug in binds that caused an error when no binds were present
* Fixed a problem with MySQL functions like NOW(), MD5(), etc (will need further testing)
* Removed the $asObject argument from fetch() and fetchAll() but instead made a $asObject property in the class
* Rebuilt the __formatColumnType() method
* Added the __startLoadTime() and __endLoadTime methods for calculating load/process/execution times

## 1.10 ##

* First initial release of Databasic
