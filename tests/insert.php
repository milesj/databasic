<?php
/**
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
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