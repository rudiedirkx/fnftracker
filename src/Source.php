<?php

namespace rdx\f95;

class Source extends Model {

	static public $_table = 'sources';

	protected function get_not_release_date() {
		return $this->last_fetch && !$this->last_fetch->release_date && $this->last_fetch->thread_date;
	}

	protected function get_released_recently() {
		if ($this->last_fetch) {
			$date = $this->last_fetch->release_date;
			if ($date) {
				$utc = strtotime($date);
				return strtotime('-' . RECENT_TIMESTR) < $utc;
			}
		}
	}

	protected function relate_last_fetch() {
		return $this->to_first(Fetch::class, 'source_id')
			->where('id in (select max(id) from fetches group by source_id)');
	}

}
