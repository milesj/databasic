<?php
/**
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/php/databasic
 */

// Get a count of all users
debug($db->select('count', 'users'));

// Get a count of all users in a country
debug($db->select('count', 'users', array(
	'conditions' => array('country_id' => 123)
)));

// Get a specific user
debug($db->select('first', 'users', array(
	'conditions' => array('id' => $id)
)));

// Get the last 10 users
debug($db->select('all', 'users', array(
	'order' => array('created' => 'DESC'),
	'limit' => 10
)));

// Get users with multiple dates
debug($db->select('all', 'users', array(
	'conditions' => array(
        'created >=' => '2012-02-10',
        'created <=' => '2012-02-24',
	)
)));

// Get a specific user and related country info
debug($db->select('first', array('users', 'countries'), array(
	'fields' => array('Users.id', 'Users.username', 'Countries.name AS country'),
	'conditions' => array('Users.id' => $id)
)));

debug($db->select('first', array('User' => 'users', 'Country' => 'countries'), array(
	'fields' => array(
		'User' => array('id', 'username'),
		'Country' => array('name AS country')
	),
	'conditions' => array('User.id' => $id)
)));