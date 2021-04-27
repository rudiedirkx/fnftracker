<?php

namespace rdx\f95;

class Cronjob {

	protected $priomap;
	protected $sources;
	protected $skipped = [];

	public function __construct() {
		$this->priomap = array_filter(array_map(function($days) {
			return $days ? strtotime('+5 hours', strtotime("-$days days")) : null;
		}, Source::PRIORITIES));

		$this->sources = Source::all('priority > 0 AND f95_id is not null');
		Source::eager('last_release', $this->sources);
	}

	public function getSources() {
		shuffle($this->sources);

		foreach ( $this->sources as $source ) {
			$anyway = false;
			if ( $source->last_release->last_fetch_on > $this->priomap[$source->priority] ) {
				if ( rand(0, 100)/100 < CRON_DO_ANYWAY ) {
					$anyway = true;
				}
				else {
					$this->skipped[] = $source;
					continue;
				}
			}

			yield [$source, $anyway];
		}

	}

}
