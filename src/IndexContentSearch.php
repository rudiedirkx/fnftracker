<?php

namespace rdx\f95;

class IndexContentSearch extends IndexContent {

	protected string $sourcesOrder;

	public function __construct(public string $search) {
		$this->prepareSql();

		$this->sources = Source::all("$this->sourcesSql ORDER BY $this->sourcesOrder, (f95_id is null) desc, priority DESC, LOWER(REGEXP_REPLACE('^(the|a) ', '', name)) ASC");
		$ids = array_column($this->sources, 'id');

		$changesLimit = count($this->sources) <= 3 ? 101 : 11;
		$this->releases = Release::all("
			source_id in (?) AND source_id in (select source_id from releases group by source_id having count(1) > 1)
			order by first_fetch_on desc
			limit $changesLimit
		", [count($ids) ? $ids : 0]);
	}

	public function prepareSql() {
		$parts = preg_split('#\s+#', $this->search);

		$sql = [];
		$order = [];
		$sorted = null;
		$search = [];
		foreach ($parts as $part) {
			if (preg_match('#^p=(\d+)$#', $part, $match)) {
				$sql[] = Source::$_db->replaceholders("priority = ?", [$match[1]]);
			}
			elseif (preg_match('#^r=(\d+)$#', $part, $match)) {
				$sql[] = "(select count(1) from releases where source_id = sources.id) = " . (int) $match[1];
			}
			elseif (preg_match('#^id=(\d+)$#', $part, $match)) {
				$sql[] = "f95_id = " . (int) $match[1];
			}
			elseif (in_array($part[0], ['-', '+']) && in_array($column = ltrim($part, '-+'), ['finished'])) {
				$sql[] = "$column is not null";
				$order[] = "$column " . ($part[0] === '-' ? 'desc' : 'asc');
				$sorted or $sorted = $column;
			}
			elseif ($part === 'delete') {
				$this->deleting = true;
			}
			else {
				$search[] = $part;
			}
		}

		if (count($search)) {
			$searches = array_map(function($search) {
				$search = '%' . trim($search) . '%';
				return Source::$_db->replaceholders("(name LIKE ? OR developer LIKE ? OR patreon LIKE ? OR description LIKE ?)", [$search, $search, $search, $search]);
			}, explode('|', implode(' ', $search)));
			$sql[] = '(' . implode(' OR ', $searches) . ')';
		}

		$this->sourcesSql = implode(' AND ', $sql) ?: '1=1';
		$this->sourcesOrder = implode(', ', $order) ?: '1';
		$this->sourcesSorted = $sorted ?? 'name';
	}

}
