<?php

namespace rdx\f95;

class Release extends Model {

	const STATUS_PREFIXES = ['completed', 'onhold', 'abandoned'];

	static public $_table = 'releases';

	protected function get_fetch_recency() {
		if (date('Y-m-d', $this->first_fetch_on) == TODAY) {
			return 2;
		}

		return $this->recent_fetch == 1 ? 1 : 0;
	}

	protected function get_recent_release() {
		return $this->getRecentness($this->release_date);
	}

	protected function get_recent_fetch() {
		return $this->getRecentness($this->first_fetch_on);
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

	protected function get_status_prefix_class() {
		foreach (explode(',', $this->prefixes) as $prefix) {
			if (in_array($prefix, self::STATUS_PREFIXES)) {
				return $prefix;
			}
		}
	}

	protected function get_software_prefix_label() {
		foreach (explode(',', $this->prefixes) as $prefix) {
			if (!in_array($prefix, self::STATUS_PREFIXES)) {
				return explode(' ', $prefix)[0];
			}
		}
	}

	protected function get_cleaned_version() {
		$version = trim(explode(' (', $this->version)[0]);
		if (preg_match('#^v[\d\.]+$#', $version)) {
			$version = substr($version, 1);
		}
		if ($version !== $this->version) {
			$version .= '*';
		}
		return $version;
	}

	protected function relate_source() {
		return $this->to_one(Source::class, 'source_id');
	}

}
