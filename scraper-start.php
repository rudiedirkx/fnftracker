<?php

use rdx\f95\Cronjob;
use rdx\f95\Fetcher;

require 'inc.bootstrap.php';

header('Access-Control-Allow-Private-Network: true');
header('Access-Control-Allow-Origin: https://' . F95_HOST);
header('Content-type: application/json; charset=utf-8');

$cronjob = new Cronjob();

$urls = [];
foreach ($cronjob->getSources() as [$source, $anyway]) {
	$fetcher = new Fetcher($source);
	$urls[] = [(int) $source->id, $source->last_release->url ?? $fetcher->url];
}

echo json_encode(['urls' => $urls]);
