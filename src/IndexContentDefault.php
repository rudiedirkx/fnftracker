<?php

namespace rdx\f95;

class IndexContentDefault extends IndexContent {

	public string $sourcesSorted = 'created_on';

	public function __construct() {
		$this->releases = Release::all("
			first_fetch_on > ? AND source_id in (select source_id from releases group by source_id having count(1) > 1)
			order by first_fetch_on desc
		", [strtotime('-' . RECENT0 . ' days')]);
		$_sources = Release::eager('source', $this->releases);
		Source::eager('characters', $_sources);

		$this->sourcesSql = '(created_on > ? OR f95_id IS NULL)';
		$this->sources = Source::all("$this->sourcesSql ORDER BY (f95_id is null) desc, created_on desc", [CREATED_RECENTLY_ENOUGH]);

		foreach ($this->sources as $source) {
			if ($source->f95_id) {
				$this->collapseUntracked = true;
				break;
			}
		}
	}

	public function getNoSourcesMessage() : string {
		return sprintf('No new sources in the last %s...', CREATED_RECENTLY_ENOUGH_TIMESTR);
	}

}
