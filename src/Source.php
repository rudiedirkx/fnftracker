<?php

namespace rdx\f95;

use GuzzleHttp\Exception\TransferException;

class Source extends Model {

	const DRAFT_PRIORITY = 80;

	const RECENTS = [RECENT0, RECENT1, RECENT2];
	const PRIORITIES = [
		// importance => recheck in days
		0 => null,
		1 => 8,
		2 => 4,
		3 => 2,
	];

	static public $_table = 'sources';

	static public function findForScraper($id, $f95_id) {
		if ($id) {
			return self::find($id);
		}

		if ($f95_id) {
			if ($exists = self::first("f95_id = ? order by priority desc", [$f95_id])) {
				return $exists;
			}

			return new self([
				'id' => 0,
				'f95_id' => $f95_id,
			]);
		}
	}

	static public function numPerDay(array $prioSources) : int {
		unset($prioSources[0]);
		$p3 = ($prioSources[3] ?? 0) / self::PRIORITIES[3];
		$p2 = ($prioSources[2] ?? 0) / self::PRIORITIES[2];
		$p1 = ($prioSources[1] ?? 0) / self::PRIORITIES[1];
		$time = $p3 + $p2 + $p1;
		$anyway = (array_sum($prioSources) - $time) * CRON_DO_ANYWAY;
		return round($time + $anyway);
	}

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

	protected function get_created_recency() {
		if ($this->created_on > LAST_24_HOURS) {
			return 2;
		}

		if ($this->created_on > CREATED_RECENTLY_ENOUGH) {
			return 1;
		}

		return 0;
	}

	protected function get_title_title() {
		$parts = [];
		if ($this->description) $parts[] = $this->description;
		if (count($this->characters)) $parts[] = count($this->characters) . ' characters';
		return implode(' | ', $parts);
	}

	protected function get_title_class() {
		$parts = [];
		if ($this->description) $parts[] = 'wdesc';
		if (count($this->characters)) $parts[] = 'wchars';
		return implode(' ', $parts);
	}

	protected function get_draft_or_priority() {
		return $this->f95_id ? $this->priority : self::DRAFT_PRIORITY;
	}

	protected function relate_versions() {
		return $this->to_many_scalar('version', Release::$_table, 'source_id')
			->where('version IS NOT NULL GROUP BY source_id, version');
	}

	protected function get_custom_developer() {
		return substr($this->developer ?? '', 0, 1) === '=';
	}

	protected function get_pretty_developer() {
		return ltrim($this->developer ?? '', '=');
	}

	protected function get_custom_patreon() {
		return substr($this->patreon ?? '', 0, 1) === '=';
	}

	protected function get_pretty_patreon() {
		return ltrim($this->patreon ?? '', '=');
	}

	protected function get_patreon_path() {
		if (preg_match('#^u:(\d+)$#', $this->patreon, $match)) {
			return 'user?u=' . $match[1];
		}

		return $this->pretty_patreon;
	}

	protected function get_status_prefix_class() {
		$played = $this->finished ? ' played' : '';
		return ($this->last_release->status_prefix_class ?? '') . $played;
	}

	protected function get_not_release_date() {
		return $this->last_release->not_release_date ?? false;
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

	protected function relate_num_releases() {
		return $this->to_count(Release::$_table, 'source_id');
	}

	protected function relate_characters() {
		return $this->to_many(Character::class, 'source_id')->order('name asc');
	}

}
