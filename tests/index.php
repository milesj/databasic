<?php

// Turn on error reporting
error_reporting(E_ALL);

function debug($var) {
    echo '<pre>'. print_r($var, true) .'</pre>';
}

// Require and initialize
require_once('database.php');

// Store db info and initialize
Database::store('default', 'localhost', 'database', 'user', 'password');

$db = Database::getInstance();

// Select specific fields from the users table
$results = $db->select('all', 'users', array(
	'fields' => array('id', 'username', 'email')
));

debug($results);

// Get count of all users in a country
$total = $db->select('count', 'users', array(
	'conditions' => array('country_id' => 123)
));

debug($total);

// Insert a user into the database
$last_id = $db->insert('users', array(
	'username' => 'milesj',
	'website' => 'http://milesj.me'
));

debug($last_id);

// Selecting from multiple tables
$results = $db->select('first', array('users', 'profile'), array(
    'conditions' => array(
        'username' => 'milesj'
    ),
	'fields' => array(
        'Users' => array('id', 'username', 'email', 'profile_id'),
        'Profile' => array('signature', 'avatar')
    )
));

debug($results);

// Output logged information
debug('Executed: '. $db->getExecuted());
debug('Affected: '. $db->getAffected());
debug($db->getQueries());