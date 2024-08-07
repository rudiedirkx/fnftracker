<?php

namespace rdx\f95;

class IndexContentSearch extends IndexContent {

	protected string $sourcesOrder;
	protected ?int $sourcesLimit = null;
	protected int $releasesLimit;
	protected bool $onlyMultiReleases = true;

	public function __construct(public string $search) {
		$this->prepareSql();

		if (isset($this->sources, $this->releases)) return;

		$limit = $this->sourcesLimit ? 'limit ' . $this->sourcesLimit : '';
		$this->sources = Source::all("$this->sourcesSql ORDER BY $this->sourcesOrder, (f95_id is null) desc, priority DESC, LOWER(REGEXP_REPLACE('^(the|a) ', '', name)) ASC $limit");
		$ids = array_column($this->sources, 'id');

		$this->releasesLimit = count($this->sources) <= 3 ? 101 : 11;
		$multi = $this->onlyMultiReleases ? 1 : 0;
		$this->releases = Release::all("
			source_id in (?) AND source_id in (select source_id from releases group by source_id having count(1) > $multi)
			order by first_fetch_on desc
			limit $this->releasesLimit
		", [count($ids) ? $ids : 0]);
	}

	public function getReleasesCountLabel() : string {
		$num = count($this->releases) >= $this->releasesLimit ? ($this->releasesLimit - 1) . '+' : count($this->releases);
		return $num . ' / ' . $this->totalReleases;
	}

	protected function prepareSql() : void {
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
			elseif (preg_match('#^rating=(\d+)$#', $part, $match)) {
				$sql[] = "round((cast(f95_rating as float)) / 10) = " . (int) $match[1];
			}
			elseif (in_array($part[0], ['-', '+']) && in_array($column = ltrim($part, '-+'), ['finished', 'created_on'])) {
				$sql[] = "$column is not null";
				$order[] = "$column " . ($part[0] === '-' ? 'desc' : 'asc');
				$sorted or $sorted = $column;
				if (!$this->sourcesLimit) $this->sourcesLimit = 100;
			}
			elseif ($part === '-last_checked') {
				$order[] = "(select max(last_fetch_on) from releases where source_id = sources.id) desc";
				$sorted or $sorted = trim($part, '-');
				$sql[] = '1=1';
			}
			elseif (ltrim($part, '-+') === 'last_release') {
				$dir = $part[0] === '-' ? 'desc' : 'asc';
				$order[] = "(select max(release_date) from releases where source_id = sources.id) $dir";
				$sorted or $sorted = trim($part, '-');
				$sql[] = '1=1';
				if (!$this->sourcesLimit) $this->sourcesLimit = 100;
			}
			elseif (in_array($part, ['=prefix', '=prefixed'])) {
				if (!$this->sourcesLimit) $this->sourcesLimit = 100;
				$this->prepareSqlForPrefixed($sql);
				return;
			}
			elseif ($part === '=characters') {
				$sql[] = "sources.id in (select source_id from characters)";
			}
			elseif ($part === '=all') {
				$this->sourcesLimit = 9999;
			}
			elseif ($part === '=') {
				// Ignore
			}
			elseif ($part === '=del') {
				$this->deleting = true;
			}
			elseif ($part === '=edit') {
				$this->editing = true;
				$this->onlyMultiReleases = false;
			}
			else {
				$search[] = $part;
			}
		}

		if (count($search)) {
			$searches = array_map(function($search) {
				$search = '%' . trim($search) . '%';
				return Source::$_db->replaceholders("(name LIKE ? OR developer LIKE ? OR patreon LIKE ? OR description LIKE ?)", [$search, $search, $search, $search]);
			}, array_filter(explode('|', implode(' ', $search))));
			$sql[] = '(' . implode(' OR ', $searches) . ')';
		}

		$this->sourcesSql = implode(' AND ', $sql) ?: '1=0';
		$this->sourcesOrder = implode(', ', $order) ?: '1=1';
		$this->sourcesSorted = $sorted ?? 'name';
	}

	protected function prepareSqlForPrefixed(array $sql) : void {
		$this->showSourceDetectedInsteadOfChecked = true;

		$this->releasesLimit = 1;
		$this->releases = [];

		$this->sourcesOrder = 'r.first_fetch_on';
		$this->sourcesSorted = 'first_fetch_on';
		$prefixesSql = implode(' OR ', array_map(fn($prefix) => sprintf("r.prefixes LIKE '%s'", $prefix), Fetcher::STATUS_PREFIXES));
		$sourcesSql = implode(' AND ', $sql) ?: '1=1';
		$this->sourcesSql = <<<SQL
			SELECT sources.*
			FROM sources
			JOIN (
				select source_id, max(id) id
				from releases
				group by source_id
			) x ON x.source_id = sources.id
			JOIN releases r ON r.id = x.id
			WHERE ($sourcesSql) AND ($prefixesSql)
			ORDER BY $this->sourcesOrder DESC
			LIMIT $this->sourcesLimit
		SQL;
		$this->sources = Source::query($this->sourcesSql);
	}

	public function getNoSourcesMessage() : string {
		return 'No sources match these terms...';
	}

}
