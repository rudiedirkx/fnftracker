<?php

use rdx\f95\Cronjob;
use rdx\f95\Fetcher;

require 'inc.bootstrap.php';

header('Access-Control-Allow-Origin: https://f95zone.to');
header('Content-type: application/json; charset=utf-8');

$cronjob = new Cronjob();

$urls = [];
foreach ($cronjob->getSources() as [$source, $anyway]) {
	$fetcher = new Fetcher($source);
	$urls[] = [(int) $source->id, $fetcher->url];
}

echo json_encode(['urls' => $urls]);
