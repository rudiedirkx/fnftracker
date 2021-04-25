<?php

use rdx\f95\Cronjob;
use rdx\f95\Fetcher;
use rdx\f95\Source;

require 'inc.bootstrap.php';

header('Access-Control-Allow-Origin: https://f95zone.to');
header('Content-type: application/json; charset=utf-8');

$source = Source::find($_REQUEST['id'] ?? 0);
if (!$source) {
	echo json_encode(['error' => "Invalid source"]);
	exit;
}

$file = $_FILES['html'] ?? null;
if (!$file) {
	echo json_encode(['error' => "Invalid HTML"]);
	exit;
}
$html = file_get_contents($file['tmp_name']);
// file_put_contents(__DIR__ . '/db/test.html', $html);

$url = $_REQUEST['url'] ?? null;

$fetcher = new Fetcher($source);
$releaseId = $fetcher->syncFromHtml($html, $url);

echo json_encode([
	'source' => $source->name,
	'id' => (int) $source->id,
	'release' => $releaseId,
	'date' => $fetcher->release,
]);
