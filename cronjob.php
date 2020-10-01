<?php

use rdx\f95\Fetch;
use rdx\f95\Source;

require 'inc.bootstrap.php';

$sources = Source::all('active = 1');

foreach ($sources as $source) {
	$url = str_replace('{id}', $source->f95_id, F95_URL);
	echo "$source->id. $source->name - $url\n";

	$html = file_get_contents($url);
	if (!preg_match('#Release Date:\s+(\d\d\d\d-\d\d?-\d\d?)#i', $html, $match)) {
		echo "- no match??\n\n";
		continue;
	}

	$date = $match[1];
	echo "- $date\n";

	Fetch::insert([
		'source_id' => $source->id,
		'date' => $date,
		'created_on' => time(),
	]);

	echo "\n";
}
