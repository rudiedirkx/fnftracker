<?php

use rdx\f95\Fetch;
use rdx\f95\Fetcher;
use rdx\f95\Source;

require 'inc.bootstrap.php';

$guzzle = Fetcher::makeGuzzle();

$priomap = array_filter(array_map(function($days) {
	return $days ? strtotime('+1 hour', strtotime("-$days days")) : null;
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

	$fetcher = new Fetcher($source);

	$anyway = $anyway ? ' (ANYWAY)' : '';
	echo "[$source->id.] $source->name$anyway\n";

	$developer = $source->developer;
	$fetch = Fetch::find($fetcher->sync($guzzle));

	if (!$fetch->release_date) {
		echo "- no release date??\n";
	}
	else {
		echo "- $fetch->release_date\n";
	}

	if (!$fetcher->developer) {
		echo "- no developer?\n";
	}
	else {
		echo "- $fetcher->developer\n";
	}

	echo "\n";
	usleep(1000 * rand(500, 1500));
}

echo date('c') . "\n\n";

echo "Skipped " . count($skipped) . " sources.\n";
