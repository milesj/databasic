<?php
/**
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/php/databasic
 */

// Turn on error reporting
error_reporting(E_ALL);

function debug($var) {
    echo '<pre>' . print_r($var, true) . '</pre>';
}

// Require and initialize
include_once '../Databasic.php';

// Store DB info and initialize
\mjohnson\databasic\Databasic::store('default', 'localhost', 'test', 'root', '');

$db = \mjohnson\databasic\Databasic::getInstance();
$id = null;

// Include our DB calls
include_once 'create.php';
include_once 'insert.php';
include_once 'select.php';
include_once 'update.php';
include_once 'delete.php';

// Output logged information
debug('Executed: '. $db->getExecuted());
debug('Affected: '. $db->getAffected());
debug($db->getQueries());