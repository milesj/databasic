# Databasic #

*Documentation may be outdated or incomplete as some URLs may no longer exist.*

*Warning! This codebase is deprecated and will no longer receive support; excluding critical issues.*

A basic wrapper class for the MySQL database engine. Contains methods for connecting to a database, fetching and returning results, building insert and update queries, optimization and more.

Databasic is a play on words for: Basic Database Access.

* Creates a persistent connection to a specified database
* Can store and connect to multiple databases
* Stores the connection within a Singleton instance
* Logs queried information and total executed queries
* Binds variables to SQL statements; similar to PDO
* Cleans all binded data to prevent mysql injection
* Fetches data as an object or an associative array
* Logs each queries execution time
* Has support for a debug mode
* Pre-built methods for CREATE TABLE, DELETE, DESCRIBE, DROP, UPDATE, INSERT, SELECT, TRUNCATE and OPTIMIZE
* And much more...

## Installation ##

I highly suggest using [PHP PDO](http://php.net/manual/en/book.pdo.php) for database access.

Install by manually downloading the library or defining a [Composer dependency](http://getcomposer.org/).

```javascript
{
    "require": {
        "mjohnson/databasic": "3.0.0"
    }
}
```

We will begin by assuming you know the basics of accessing and manipulating a MySQL database. Begin by opening the DataBasic class and familiarizing yourself with code. In a fresh install of the class, there are no declarations for defining a database and its connection info. To define a database connection, you will use the method `store()`. This method stores the connection info into the class, allowing you to store multiple databases. Each stored connection must have a unique slug to identify when calling the db you wish to connect to.

You can call the `store()` method statically from any external file. You should name your main connection slug "default", as this is the slug the system will use if you do not choose a db when initializing (more of this in the later chapters).

```php
use mjohnson\databasic\Databasic;

// Store connection info
Databasic::store('default', 'localhost', 'dbname', 'username', 'password');

// Initiate our database class
$db = Databasic::getInstance();
```

If you take a look above, we did not initiate the class by using `$db = new mjohnson\databasic\Databasic();` (Calling the class by its construct is disallowed, you must use `getInstance()`). Instead we are calling a static method `getInstance()` which attempts to find an already existent database connection within memory. This method is based on the [Singleton Pattern](http://en.wikipedia.org/wiki/Singleton_pattern) and restricts the database connection to 1 instance instead of many opened instances.

You should now be finished with installing the class. Lets jump onto the next chapter for some understandings of how your queries should be written and executed.

## Variable References ##

There are some variables in this class that are used across multiple methods. I will explain each variable and how it works (it works the same everywhere) and supply some examples.

* `$instance` (object) - The instance variable will hold the database Singleton connection.
* `$executed` (int) - The total number of successfully executed queries.
* `$queries` (array) - Will be an array of all queries with details for the query, run time, error, etc. Will on work if $debug is true.
* `$sql` (string) - Simply the variable that holds the query string.
* `$tableName` (string) - The table that will be affected by the query being executed.
* `$columns` (array) - The columns variable is an associative array containing columns and its values. The array key is the column name and the array value is the value to handle the update or insert (column => value).
* `$options` (array) - The options variable is the most complicated to understand. Within it you can have an associative array with the indexes of conditions, fields, order and group. Each of these indexes would be its own associative array with the keys and values correlating the the columns and values. Conditions would be your statements where clause, fields would be what fields you want to grab (defaults to *), order is how you want to order your results and group adds a group by MySQL result.
* `$conditions` (array) - Work exactly like conditions does in the $options array, just without the other array clutter.

## Creating a Table ##

In the latest version of Databasic, v2.0, you can now create database tables using `create()`. The `create()` method takes 3 arguments: `$tableName`, `$schema` and `$settings`. The `$schema` would be an array of columns you wish to create, the type and any available options for the column (view below for an example). The `$settings` array would be used to override the default table settings, primarily to change the database engine and the collation (locale).

```php
// Schema Legend
$schema = array(
    'columnName' => array(
        'type'        => 'text',
        'length'    => 255,
        'key'        => 'index',
        'options'    => array(
            'null'        => true,    
            'unsigned'    => true,
            'comment'    => 'This is a comment',
            'default'    => 'Default value',
            'auto_increment' => true
        )
    )
);
```

The diagram above shows you all the available options to define a single column for your table. The type index is required and is used to define the type of column. The length determines the length of the int or text columns, an exception would be for the enum type in that length would be an array of values for the ENUM field. The key is an optional field but can be used to define a primary, unique or index; the primary and unique can only be used once. All indexes in the options are optional; the null, unsigned and auto_increment options should be a boolean true/false, the other two are strings. Below is a list of available settings, how they are used and a basic example for a schema.

Types: int, integer, text, string, date, time, datetime, timestamp, enum, blob, float, year
Length: 1+ for integers, 0-255 for strings (VARCHAR), 255+ for TEXT, array of options for ENUM
Keys: primary, unique, index

```php
// Example Schema
$schema = array(
    'id' => array(
        'type'         => 'int',
        'length'     => 10,
        'key'         => 'primary',
        'options'    => array(
            'auto_increment' => true
        )
    ),
    'username' => array(
        'type'        => 'text',
        'length'    => 50,
        'key'        => 'index'
    ),
    'email' => array(
        'type'        => 'text',
        'length'    => 75
    ),
    'gender' => array(
        'type'        => 'enum',
        'length'    => array('male', 'female'),
        'options'    => array(
            'default' => 'female'
        )
    ),
    'birthdate' => array(
        'type'        => 'datetime',
        'options'    => array(
            'null' => true
        )
    )
);

$db->create('testTable', $schema);
```

```sql
CREATE TABLE IF NOT EXISTS `testTable` (
    `id` INT(10) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(75) NOT NULL,
    `gender` ENUM('male', 'female') NOT NULL DEFAULT 'female',
    `birthdate` DATETIME NULL,
    PRIMARY KEY (`id`),
    KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci COMMENT='' AUTO_INCREMENT=1;
```

### Overriding the table settings ###

By default, the `create()` method applies the engine InnoDB, the character set utf8 and sets your auto_increment to 1. You may override these by creating an array of settings, and passing it as the 3rd argument. The available settings can be found in the example below:

```php
$settings = array(
    'engine'     => 'InnoDB',
    'charset'    => 'utf8',
    'collate'    => 'utf8_general_ci',
    'comment'    => '',
    'increment'    => 1
); 

$db->create('testTable', $schema, $settings);
```

## Doing a Select ##

The most difficult method to learn is the `select()` method, but it's also the most powerful. The `select()` method can grab data from a single table or multiple tables, so as long as the relationship is added. Select takes 3 arguments which are `$finder`, `$tableName` and `$options`. The new variable `$finder` is only specific to `select()` and it can have 3 different values: first (grabs the first record that matches), all (grabs all results defined by limit and offset) or count (which returns a count total for the conditions specified). Below is an example on how to use the $options argument.

The options array argument can have 6 different indexes. The indexes are fields (array of fields to return), conditions, order, group, limit and offset.

```php
$options = array(
    'fields' => array('id', 'username', 'email'),
    'conditions' => array(
        'id' => 1,
        'username !=' => 'milesj',
    ),
    'order' => array('username' => 'ASC'),
    'group' => array('username', 'age'),
    'limit' => 10,
    'offset' => 0
);

$results = $db->select('all', 'users', $options);

// Would build the unusual statement of:
// SELECT `id`, `username`, `email` FROM `users` WHERE `id` = 1 AND `username` != 'milesj' ORDER BY `username` ASC GROUP BY `username`, `age` LIMIT 0,10
```

### Select data from multiple tables ###

Now for this to work you would have to have a foreign key that matches two tables together. For our example we will use a users and countries table, and on the users table we will have a column called country_id. We will attempt to grab all user data, and also grab the name of the country for the record that matches country_id. Do note that we are giving the table names aliases of u and c (which will be capitalized), and we will need to use the alias (if given) in the options array as well.

```php
$query = $db->select('first', array('u' => 'users', 'c' => 'countries'), array(
    'fields' => array(
        'U.*',
        'C.name AS countryName'
    ),
    'conditions' => array(
        'U.id' => 1,
        'U.country_id' => 'C.id'
    )
);

// Statement now becomes:
// SELECT `U`.*, `C`.`name` AS `countryName` FROM `users` AS `U`, `countries` AS `C` WHERE `U`.`id` = 1, `U`.`country_id` = `C`.`id` LIMIT 1
```

Now I know you may still be confused at this point, but if you go to the final chapter you will find some advanced examples. You could always send me an email to if you get stuck.

### Using AND/OR and operators ###

The usage of AND/OR only applies to versions 2.1 and above, and for operator usage I will do an example for both versions. Lets begin first with using AND and OR in your conditions (or where clause). All you need to do is add a dimensional array with the index of AND or OR; Databasic by default will join everything with an AND.

```php
$conditions = array(
    'OR' => array(
        'name' => 'Miles',
        'username' => 'Miles'
    )
    'status' => 'active'
);

// WHERE (`name` = 'Miles' OR `username` = 'Miles') AND `status` = 'active'
```

If you want to do an OR on the same column, wrap the array values in an additional array like so. Additionally, you can place an OR within an OR, or an AND within an OR, and vice versa.

```php
$conditions = array(
    'OR' => array(
        array('name' => 'Miles'),
        array('name' => 'Johnson')
    ),
    'status' => 'active'
);

// WHERE ((`name` = 'Miles') OR (`name` = 'Johnson')) AND `status` = 'active'
```

To use operators within your conditions (!=, <=, >=, etc) is quite easy, but there are different ways to achieve this depending on what Databasic version you are using. I will give an example using older and newer versions. If you are familiar with CakePHP, the new supported way should be easy to understand.

```php
// Version 2.0 and below
$conditions = array(
    'age' => array('operator' => '>=', 'value' => 21)
);

// Version 2.1 and above
$conditions = array('age >=' => 21);
```

## Doing an Insert ##

The `insert()` method is quite similar to the `update()` method. It takes only two arguments (which work exactly like the `update()` arguments): `$tableName` and `$columns`. The id of the inserted row is returned (using `getLastInsertId()`) upon a successful execution.

```php
// Initiate the class
$db = Databasic::getInstance();

// Build the variables
$columns = array(
    'id'        => NULL,
    'username'    => 'milesj',
    'website'    => 'http://www.milesj.me',
    'loginTime'    => 'NOW()'
);

// Execute the insert
$query = $db->insert('users', $columns);

// Statement now becomes:
// INSERT INTO `users` (`id`, `username`, `website`, `loginTime`) VALUES (NULL, 'milesj', 'http://www.milesj.me', NOW());
```

## Doing an Update ##

Instead of writing your own SQL query for updating rows - which can be quite tedious with all the placeholders and binding - DataBasic comes with its own `update()` method. This method takes 4 arguments: `$tableName`, `$columns`, `$conditions` and `$limit`; you can read more about these variables above. Please note, this update method is only useful for common queries. If you have more advanced queries, it would be best to write a custom query using the instructions above.

```php
// Build the variables
$columns = array(
    'username'    => 'milesj',
    'website'    => 'http://www.milesj.me',
);

$conditions = array('id' => 1);

// Execute the update
$query = $db->update('users', $columns, $conditions, 1);

// Statement now becomes:
// UPDATE `users` SET `username` = 'milesj', `website` = 'http://www.milesj.me' WHERE `id` = 1 LIMIT 1
```

## Doing a Delete ##

The `delete()` method is about as easy as it can get. It takes three arguments, `$tableName`, `$conditions` and `$limit` (defaults to 1 so we don't delete rows on accident). The arguments should be self explanatory by now, if they do not, refer to the previous chapters.

```php
// Build the variables
$conditions = array('username' => 'milesj');

// Execute the delete
$query = $db->delete('users', $conditions, 1);

// Statement now becomes:
// DELETE FROM `users` WHERE `username` = 'milesj' LIMIT 1
```

## Fetching Data ##

There are two methods for getting data from a result. The first to grab multiple rows is `fetchAll()` and the other `fetch()` is to grab the first (single) row in the result. Fetched data is returned in an associative array be default, if you want your data returned as an object you would set the class property of `$asObject` to true. Here are some examples of how to grab data in different situations (Please note I did not bind data simply to keep the code short, but you should!).

```php
// Get multiple rows from a query
$query = $db->execute("SELECT * FROM users LIMIT 10");
while ($row = $db->fetchAll($query)) {
    echo $row['username'];
}

// Getting a single row
$query = $db->execute("SELECT * FROM users WHERE username = 'milesj' LIMIT 1");
$row = $db->fetch($query);

// Getting rows as an object
$db->asObject(true);

$query = $db->execute("SELECT * FROM users LIMIT 10");
while ($row = $db->fetchAll($query)) {
    echo $row->username;
}
```

## Writing a custom Query ##

In DataBasic, SQL queries are written using placeholders instead of injecting the variables directly into the string. If you are familiar with [PDO (PHP Data Objects)](http://us.php.net/pdo), the next part should be easy for you to understand. In your SQL statement your placeholders will be binded and replaced with respective variables by using the `bind()` method. Placeholders begin with a colon and are follow by a single word (`:username`). The reason for binding variables is to clean the input and to ensure the data being passed is legitimate and will not contain database injections or unwanted code.

Once you have your statement written, you will bind your variables to replace the placeholders. The first argument of `bind()` will be the statement and the second argument will be an array of placeholders and the variables to replace them with. The array key will be the placeholder (should begin with a `:`) and the array value will be the value the placeholder will become. Below is an example of a basic statement that is binded and then executed using `execute()`.

```php
$sql = "SELECT * FROM users WHERE username = ':username' LIMIT :limit";
$sql = $db->bind($sql, array(
    ':username'    => 'milesj',
    ':limit'    => 1
));

// Statement now becomes:
// SELECT * FROM users WHERE username = 'milesj' LIMIT 1

// Execute query and return the result
$query = $db->execute($sql);
```

You would only need to bind variables for custom SQL statements. DataBasic comes bundled with automatic `select()`, `update()`, `insert()` and `delete()` methods which takes your variables (table name, columns, values, where clause, etc), cleans them, creates an SQL statement, binds them and finally executes. Below I will give you an example for both the `insert()` and `update()` methods.

## Using Multiple Databases ##

To use and connect to multiple databases, you would need to define multiple connection using `store()`. Be sure to give each connection a unique slug, the default database should use the slug "default".

```php
// Store 2 databases
Databasic::store('default', 'localhost', 'dbname', 'username', 'password');
Databasic::store('alternate', 'localhost', 'dbname2', 'username', 'password');
```

Now to use the "alternate" database, you would need to pass that as an argument to `getInstance()`. And once you do that, all methods called using that instance will use that database.

```php
// Default
$db = Databasic::getInstance();

// Alternate
$db2 = Databasic::getInstance('alternate');
$db2->getQueries();
```

## Using MySQL Statement Methods ##

DataBasic comes bundled with a few methods that can access and manipulate your database tables. They are named respectively after their matching MySQL function, and they are `describe()`, `drop()`, `optimize()` and `truncate()`. Each of these methods take a single argument for the name of the database table. The only exception is `optimize()`, which could take no arguments and optimize all tables.

```php
// Truncate - Empty all rows in the table
$db->truncate('table');

// Drop - Completely remove the table and all data
$db->drop('table');

// Describe - Return an array of columns and their settings
$db->describe('table');

// Optimize - Optimize a single table or all
$db->optimize('table');
$db->optimize();
```

## Advanced Examples ##

The first example we will be grabbing data from two tables, games and genres, and matching them based on the foreign key genre_id found on the games table. Do note that if you have multiple tables, and you have not declared an alias (like in the select chapter), the tables name will be used as the alias (capitalized).

```php
$results = $db->select('all', array('games', 'genres'), array(
    'fields' => array(
        'Games.*',
        'Genres.name AS genreName',
        'Genres.id as genreId'
    ),
    'conditions' => array('Games.genre_id' => 'Genres.id')
));
```

The next example we will be grabbing a total of how many users are within a country. The result would be an integer with the total.

```php
$total = $db->select('count', 'users', array(
    'conditions' => array('country_id' => 123)
));
```

In our final example we will be grabbing data from 3 tables. We will be grabbing a blog entry (entries table), and grabbing the topic title (topics table) and finally getting the author (users table). We will be using table aliases in this example.

```php
$result = $db->select('first', array('e' => 'entries', 't' => 'topics', 'u' => 'users'), array(
    'fields' => array(
        'E.*',
        'T.title AS topicTitle',
        'U.username',
        'U.email',
        'U.website'
    ),
    'conditions' => array(
        'E.topic_id' => 'T.id',
        'E.user_id' => 'U.id'
    )
));
```

 If you have written an advanced query and would like it to be placed here, go ahead and send me an email with the code snippet.
