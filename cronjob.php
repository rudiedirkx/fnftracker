<?php

use rdx\f95\Cronjob;
use rdx\f95\Fetcher;
use rdx\f95\Release;
use rdx\f95\Source;

require 'inc.bootstrap.php';

$cronjob = new Cronjob();
$guzzle = Fetcher::makeGuzzle();

echo date('c') . "\n\n";

foreach ( $cronjob->getSources() as [$source, $anyway] ) {
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
