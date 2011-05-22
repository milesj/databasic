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

// Create our database tables
$db->create('users', array(
	'id' => array(
		'type' => 'int',
		'key' => 'primary',
		'options' => array('auto_increment' => true),
	),
	'status' => array(
		'type' => 'int',
		'length' => 3,
		'options' => array('default' => 0)
	),
	'username' => array(
		'type' => 'text',
		'length' => 30
	),
	'email' => array(
		'type' => 'text',
		'length' => 50
	),
	'password' => array(
		'type' => 'text',
		'length' => 30
	),
	'website' => array(
		'type' => 'text',
		'length' => 30
	),
	'gender' => array(
		'type' => 'enum',
		'length' => array('male', 'female'),
		'options' => array('default' => 'male')
	),
	'country_id' => array(
		'type' => 'int',
		'length' => 5
	),
	'created' => array(
		'type' => 'datetime',
		'options' => array('null' => true)
	),
	'modified' => array(
		'type' => 'datetime',
		'options' => array('null' => true)
	),
));

$db->create('countries', array(
	'id' => array(
		'type' => 'int',
		'key' => 'primary',
		'options' => array('auto_increment' => true),
	),
	'name' => array(
		'type' => 'text',
		'length' => 50
	)
));