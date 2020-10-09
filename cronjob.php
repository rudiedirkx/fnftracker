<?php

use rdx\f95\Fetch;
use rdx\f95\Source;

require 'inc.bootstrap.php';

$guzzle = Source::makeGuzzle();

$priomap = array_filter(array_map(function($days) {
	return $time ? strtotime('+1 hour', strtotime("-$days days")) : null;
}, Source::PRIORITIES));

$sources = Source::all('priority > 0');

echo date('c') . "\n\n";

$skipped = [];
foreach ( $sources as $source ) {
	$anyway = false;
	if ( $source->last_fetch->created_on > $priomap[$source->priority] ) {
		if ( rand(0, 100)/100 < CRON_DO_ANYWAY ) {
			$anyway = true;
		}
		else {
			$skipped[] = $source;
			continue;
		}
	}

	$anyway = $anyway ? ' (ANYWAY)' : '';
	echo "$source->id. $source->name$anyway\n";

	$developer = $source->developer;
	$fetch = Fetch::find($source->sync($guzzle));

	if (!$fetch->release_date) {
		echo "- no release date??\n";
	}
	else {
		echo "- $fetch->release_date\n";
	}

	if (!$source->developer) {
		echo "- no developer?\n";
	}
	elseif ($source->developer == $developer) {
		echo "- $source->developer (unchanged)\n";
	}
	else {
		echo "- $source->developer (new)\n";
	}

	exit;

	echo "\n";
	usleep(1000 * rand(500, 1500));
}

echo date('c') . "\n\n";

echo "Skipped " . count($skipped) . " sources.\n";
