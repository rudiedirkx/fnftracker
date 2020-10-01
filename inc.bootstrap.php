<?php

use rdx\f95\Model;

require 'vendor/autoload.php';
require 'env.php';

header('Content-type: text/plain; charset=utf-8');

$db = db_sqlite::open(array('database' => DB_FILE));
if ( !$db ) {
	exit('No database connecto...');
}

$db->ensureSchema(require 'inc.db-schema.php');

Model::$_db = $db;
