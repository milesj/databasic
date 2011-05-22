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

// Update a single row
$db->update('users', 
	array('status' => 1, 'modified' => 'NOW()'), 
	array('id' => $id)
);

// Update multiple rows (pass null or false to remove the limit)
$db->update('users', 
	array('status' => 1), 
	array('status' => 0),
	null
);