<?php

use rdx\f95\Cronjob;
use rdx\f95\Fetcher;
use rdx\f95\Source;

require 'inc.bootstrap.php';

header('Access-Control-Allow-Private-Network: true');
header('Access-Control-Allow-Origin: https://' . F95_HOST);
header('Content-type: application/json; charset=utf-8');

$source = Source::findForScraper($_REQUEST['id'] ?? 0, $_REQUEST['f95_id'] ?? 0);
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

$lastReleaseId = $source->last_release->id ?? 0;
$fetcher = new Fetcher($source);
$releaseId = $fetcher->syncFromHtml($html, $url);

echo json_encode([
	'source' => $source->name,
	'source_id' => (int) $source->id,
	'developer' => $source->developer,
	'release_id' => $releaseId,
	'release_date' => $fetcher->release,
	'new' => $releaseId != $lastReleaseId,
]);
