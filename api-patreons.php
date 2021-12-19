<?php

use rdx\f95\Source;

require 'inc.bootstrap.php';

header('Access-Control-Allow-Private-Network: true');
header('Access-Control-Allow-Origin: https://www.patreon.com');
header('Content-type: application/json; charset=utf-8');

$sources = Source::all("priority >= 0 AND patreon IS NOT NULL ORDER BY priority DESC, created_on DESC");
$patreons = [];
foreach ($sources as $source) {
	$patreon = mb_strtolower($source->pretty_patreon);
	if (count($patreons[$patreon] ?? []) < 3) {
		$patreons[$patreon][] = $source->name;
	}
}

echo json_encode($patreons);
