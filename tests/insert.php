<?php
/**
 * Databasic
 *
 * A wrapper class for accessing, abstracting and manipulating a MySQL database.
 * 
 * @author		Miles Johnson - http://milesj.me
 * @copyright	Copyright 2006-2011, Miles Johnson, Inc.
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link		http://milesj.me/code/php/databasic
 */

// Insert a new user and return the last inserted ID (false on error)
$id = $db->insert('users', array(
	'username' => 'milesj',
	'password' => md5('password'),
	'status' => 0,
	'email' => 'email@domain.com',
	'website' => 'http://milesj.me',
	'created' => date('Y-m-d H:i:s')
));

// Insert a country
$db->insert('countries', array(
	'name' => 'United States'
));