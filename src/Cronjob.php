<?php

namespace rdx\f95;

class Cronjob {

	protected array $priomap;
	protected array $checking;

	public function __construct() {
		$this->priomap = array_filter(array_map(function($days) {
			return $days ? date('Y-m-d', strtotime('+5 hours', strtotime("-$days days"))) : null;
		}, Source::PRIORITIES));
	}

	public function getSources() {
		$sources = Source::all("priority > 0 AND f95_id is not null AND f95_id <> '1'");
		Source::eager('last_release', $sources);
		shuffle($sources);

		$this->checking = array_map(fn() => [0, 0], $this->priomap);

		$anyway = CRON_DO_ANYWAY * 100;
		foreach ( $sources as $source ) {
			if ( date('Y-m-d', $source->last_release->last_fetch_on ?? 1) <= $this->priomap[$source->priority] ) {
				$this->checking[$source->priority][0]++;
				yield [$source, false];
			}
			elseif ( rand(0, 100) < $anyway ) {
				$this->checking[$source->priority][1]++;
				yield [$source, true];
			}
		}

	}

}
