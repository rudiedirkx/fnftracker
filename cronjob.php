<?php

use rdx\f95\Fetch;
use rdx\f95\Source;

require 'inc.bootstrap.php';

$guzzle = Source::makeGuzzle();

$priomap = array_filter(array_map(function($time) {
	return $time ? strtotime('+1 hour', strtotime("-$time")) : null;
}, Source::PRIORITIES));

$sources = Source::all('priority > 0');

echo date('c') . "\n\n";

$skipped = [];
foreach ( $sources as $source ) {
	if ( $source->last_fetch->created_on > $priomap[$source->priority] ) {
		$skipped[] = $source;
		continue;
	}

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

echo date('c') . "\n\n";

echo "Skipped " . count($skipped) . " sources.\n";
