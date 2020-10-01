<?php

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RedirectMiddleware;
use rdx\f95\Fetch;
use rdx\f95\Source;

require 'inc.bootstrap.php';

$guzzle = new Guzzle([
	'connect_timeout' => 3,
	'read_timeout' => 3,
	'timeout' => 3,
	'http_errors' => true,
	'cookies' => $cookies = new CookieJar(),
	'headers' => ['User-Agent' => 'FnfTracker'],
	'allow_redirects' => [
		'track_redirects' => true,
	] + RedirectMiddleware::$defaultSettings,
]);

$sources = Source::all('active = 1');

foreach ($sources as $source) {
	$url = strtr(F95_URL, [
		'{name}' => 'x',
		'{id}' => $source->f95_id,
	]);
	echo "$source->id. $source->name - $url\n";

	$rsp = $guzzle->get($url);
	$redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER);
	$html = (string) $rsp->getBody();

	$release = preg_match('#Release Date:\s+(\d\d\d\d-\d\d?-\d\d?)#i', $html, $match) ? $match[1] : null;
	$thread = preg_match('#Thread Updated:\s+(\d\d\d\d-\d\d?-\d\d?)#i', $html, $match) ? $match[1] : null;

	if (!$release) {
		echo "- no match??\n";
	}
	else {
		echo "- $release\n";
	}

	Fetch::insert([
		'source_id' => $source->id,
		'release_date' => $release,
		'thread_date' => $thread,
		'url' => end($redirects),
		'created_on' => time(),
	]);

	echo "\n";
}
