<?php

namespace rdx\f95;

use GuzzleHttp\Exception\TransferException;

class Source extends Model {

	const DRAFT_PRIORITY = 80;

	const RECENTS = [RECENT0, RECENT1, RECENT2];
	const PRIORITIES = [0 => null, 1 => 8, 2 => 4, 3 => 2];

	static public $_table = 'sources';

	public function sync(int $attempts = 1) {
		if (!$this->f95_id) return;

		$fetcher = new Fetcher($this);
		$throw = null;
		for ($i = 0; $i < $attempts; $i++) {
			try {
				return $fetcher->sync();
			}
			catch (TransferException $ex) {
				$throw = $ex;
				sleep(1);
			}
		}

		throw $throw;
	}

	protected function get_draft_or_priority() {
		return $this->f95_id ? $this->priority : self::DRAFT_PRIORITY;
	}

	protected function relate_versions() {
		return $this->to_many_scalar('version', Release::$_table, 'source_id')
			->where('version IS NOT NULL GROUP BY source_id, version');
	}

	protected function get_custom_developer() {
		return substr($this->developer, 0, 1) === '=';
	}

	protected function get_pretty_developer() {
		return ltrim($this->developer, '=');
	}

	protected function get_status_prefix_class() {
		if ($this->finished) {
			return 'played';
		}

		return $this->last_release->status_prefix_class ?? '';
	}

	protected function get_not_release_date() {
		return $this->last_release && !$this->last_release->release_date && $this->last_release->thread_date;
	}

	protected function get_old_last_change() {
		if (!$this->last_release) {
			return 0;
		}

		if ($date = ($this->last_release->release_date ?? $this->last_release->thread_date)) {
			if ($date <= date('Y-m-d', strtotime('-2 years'))) {
				return 2;
			}
			elseif ($date <= date('Y-m-d', strtotime('-1 year'))) {
				return 1;
			}
		}

		return 0;
	}

	protected function relate_last_release() {
		return $this->to_first(Release::class, 'source_id')
			->where('id in (select max(id) from releases group by source_id)');
	}

}
