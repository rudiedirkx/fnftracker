<?php

use rdx\f95\Cronjob;
use rdx\f95\Fetcher;
use rdx\f95\Source;

require 'inc.bootstrap.php';

header('Content-type: text/html; charset=utf-8');

$html = file_get_contents(__DIR__ . '/db/test.html');
$fetcher = new Fetcher(new Source(['id' => -1]), true);
$releaseId = $fetcher->syncFromHtml($html, null);
dd($fetcher);
