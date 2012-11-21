<?php
/**
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
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