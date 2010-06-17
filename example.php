<?php

// Turn on error reporting
error_reporting(E_ALL);

// Require and initialize
require_once('database.php');

// Store db info and initialize
Database::store('default', 'localhost', 'database', 'user', 'password');

$db = Database::getInstance();

// Select specific fields from the users table
$results = $db->select('all', 'users', array(
	'fields' => array('id', 'username', 'email')
));

// Get count of all users in a country
$total = $db->select('count', 'users', array(
	'conditions' => array('country_id' => 123)
));

// Insert a user into the database
$last_id = $db->insert('users', array(
	'username' => 'milesj',
	'website' => 'http://milesj.me'
));

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