<?php

namespace rdx\f95;

class Fetch extends Model {

	static public $_table = 'fetches';

	protected function get_cleaned_version() {
		$version = trim(explode(' (', $this->version)[0]);
		if (preg_match('#^v[\d\.]+$#', $version)) {
			$version = substr($version, 1);
		}
		return $version;
	}

	protected function relate_source() {
		return $this->to_one(Source::class, 'source_id');
	}

}
