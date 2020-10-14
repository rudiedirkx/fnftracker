<?php

namespace rdx\f95;

class Fetch extends Model {

	static public $_table = 'fetches';

	protected function get_is_recent_fetch() {
		return $this->recent_fetch == 1;
	}

	protected function get_recent_release() {
		return $this->getRecentness($this->release_date);
	}

	protected function get_recent_fetch() {
		return $this->getRecentness($this->created_on);
	}

	protected function getRecentness($date) {
		$utc = is_numeric($date) ? $date : strtotime($date);
		foreach (Source::RECENTS as $i => $days) {
			if (strtotime("-$days days") < $utc) {
				return $i + 1;
			}
		}

		return 0;
	}

	protected function get_prefix() {
		return $this->prefixes;
	}

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
