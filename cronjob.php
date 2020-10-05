<?php

use rdx\f95\Fetch;
use rdx\f95\Source;

require 'inc.bootstrap.php';

$guzzle = Source::makeGuzzle();

$sources = Source::all('active = 1');

echo date('c') . "\n\n";

foreach ($sources as $source) {
	echo "$source->id. $source->name\n";

	$fetch = Fetch::find($source->sync($guzzle));

	if (!$fetch->release_date) {
		echo "- no match??\n";
	}
	else {
		echo "- $fetch->release_date\n";
	}

	echo "\n";
	usleep(1000 * rand(500, 1500));
}

echo date('c') . "\n";
