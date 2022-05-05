<?php

namespace rdx\f95;

class IndexContentAll extends IndexContent {

	public string $sourcesSorted = 'name';

	public function __construct() {
		$this->sourcesSql = '1=1';
		$this->sources = Source::all("$this->sourcesSql ORDER BY (f95_id is null) desc, priority DESC, LOWER(REGEXP_REPLACE('^(the|a) ', '', name)) ASC");

		$this->releases = [];
	}

}
