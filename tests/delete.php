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

// Delete a single row
$db->delete('users', array('id' => $id));

// Delete multiple rows (pass null or false to remove the limit)
$db->delete('users', array('status' => 0), null);