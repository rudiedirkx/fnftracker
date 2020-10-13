<?php

namespace rdx\f95;

class Source extends Model {

	const RECENTS = [RECENT0, RECENT1, RECENT2];
	const PRIORITIES = [0 => null, 1 => 8, 2 => 4, 3 => 2];

	static public $_table = 'sources';

	public function sync() {
		$fetcher = new Fetcher($this);
		return $fetcher->sync();
	}

	protected function get_prefix_class() {
		if ($this->finished) {
			return 'played';
		}

		return $this->last_fetch->prefixes;
	}

	protected function get_not_release_date() {
		return $this->last_fetch && !$this->last_fetch->release_date && $this->last_fetch->thread_date;
	}

	protected function get_recent_release() {
		if ($this->last_fetch) {
			$date = $this->last_fetch->release_date;
			if ($date) {
				$utc = strtotime($date);
				foreach (self::RECENTS as $i => $days) {
					if (strtotime("-$days days") < $utc) {
						return $i + 1;
					}
				}
			}
		}

		return 0;
	}

	protected function relate_last_fetch() {
		return $this->to_first(Fetch::class, 'source_id')
			->where('id in (select max(id) from fetches group by source_id)');
	}

}
