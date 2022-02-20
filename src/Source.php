<?php

namespace rdx\f95;

use GuzzleHttp\Exception\TransferException;

class Source extends Model {

	const DRAFT_PRIORITY = 80;

	const RECENTS = [RECENT0, RECENT1, RECENT2];
	const PRIORITIES = [0 => null, 1 => 8, 2 => 4, 3 => 2];

	static public $_table = 'sources';

	static public function makeSearchSql(string $query) {
		$parts = preg_split('#\s+#', $query);

		$sql = $order = [];
		$sorted = null;
		$search = [];
		foreach ($parts as $part) {
			if (preg_match('#^p=(\d+)$#', $part, $match)) {
				$sql[] = self::$_db->replaceholders("priority = ?", [$match[1]]);
			}
			elseif (preg_match('#^r=(\d+)$#', $part, $match)) {
				$sql[] = "(select count(1) from releases where source_id = sources.id) = " . (int) $match[1];
			}
			elseif (in_array($part[0], ['-', '+']) && in_array($column = ltrim($part, '-+'), ['finished'])) {
				$sql[] = "$column is not null";
				$order[] = "$column " . ($part[0] === '-' ? 'desc' : 'asc');
				$sorted or $sorted = $column;
			}
			else {
				$search[] = $part;
			}
		}

		if (count($search)) {
			$searches = array_map(function($search) {
				$search = '%' . trim($search) . '%';
				return self::$_db->replaceholders("(name LIKE ? OR developer LIKE ? OR patreon LIKE ? OR description LIKE ?)", [$search, $search, $search, $search]);
			}, explode('|', implode(' ', $search)));
			$sql[] = '(' . implode(' OR ', $searches) . ')';
		}

		return (object) [
			'source_where' => implode(' AND ', $sql) ?: '1=1',
			'source_sorted' => $sorted ?? 'name',
			'source_order' => implode(' AND ', $order) ?: '1=1',
		];
	}

	static public function findForScraper($id, $f95_id) {
		if ($id) {
			return self::find($id);
		}

		if ($f95_id) {
			if ($exists = self::first("f95_id = ? order by priority desc", [$f95_id])) {
				return $exists;
			}

			return new self([
				'f95_id' => $f95_id,
			]);
		}
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

	protected function get_custom_patreon() {
		return substr($this->patreon, 0, 1) === '=';
	}

	protected function get_pretty_patreon() {
		return ltrim($this->patreon, '=');
	}

	protected function get_patreon_path() {
		if (preg_match('#^u:(\d+)$#', $this->patreon, $match)) {
			return 'user?u=' . $match[1];
		}

		return $this->patreon;
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
