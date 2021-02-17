<?php

use rdx\f95\Fetcher;
use rdx\f95\Release;
use rdx\f95\Source;

require 'inc.bootstrap.php';

$guzzle = Fetcher::makeGuzzle();

$priomap = array_filter(array_map(function($days) {
	return $days ? strtotime('+1 hour', strtotime("-$days days")) : null;
}, Source::PRIORITIES));

$sources = Source::all('priority > 0 AND f95_id is not null');
Source::eager('last_release', $sources);

echo date('c') . "\n\n";

$skipped = [];
foreach ( $sources as $source ) {
	$anyway = false;
	if ( $source->last_release->last_fetch_on > $priomap[$source->priority] ) {
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
	$fetch = Release::find($fetcher->sync($guzzle, true));

	if (!$fetch) {
		echo "- CONNECTION EXCEPTION\n";
	}
	else {
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
	}

	echo "\n";
	usleep(1000 * rand(500, 1500));
}

echo date('c') . "\n\n";

echo "Skipped " . count($skipped) . " sources.\n";
