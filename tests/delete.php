<?php
/**
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/php/databasic
 */

// Delete a single row
$db->delete('users', array('id' => $id));

// Delete multiple rows (pass null or false to remove the limit)
$db->delete('users', array('status' => 0), null);

// Empty our database
$db->truncate('users');
$db->truncate('countries');