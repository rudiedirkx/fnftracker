<?php

namespace rdx\f95;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RedirectMiddleware;

class Source extends Model {

	static public $_table = 'sources';

	static public function makeGuzzle() {
		return new Guzzle([
			'connect_timeout' => 3,
			'read_timeout' => 3,
			'timeout' => 3,
			'http_errors' => true,
			'cookies' => $cookies = new CookieJar(),
			'headers' => ['User-Agent' => 'FnfTracker'],
			'allow_redirects' => [
				'track_redirects' => true,
			] + RedirectMiddleware::$defaultSettings,
		]);
	}

	public function sync(Guzzle $guzzle = null) {
		$guzzle or $guzzle = self::makeGuzzle();

		$url = strtr(F95_URL, [
			'{name}' => 'x',
			'{id}' => $this->f95_id,
		]);

		$rsp = $guzzle->get($url);
		$redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER);
		$html = (string) $rsp->getBody();

		$release = preg_match('#Release Date:\s+(\d\d\d\d-\d\d?-\d\d?)#i', $html, $match) ? $match[1] : null;
		$thread = preg_match('#Thread Updated:\s+(\d\d\d\d-\d\d?-\d\d?)#i', $html, $match) ? $match[1] : null;

		return Fetch::insert([
			'source_id' => $this->id,
			'release_date' => $release,
			'thread_date' => $thread,
			'url' => end($redirects),
			'created_on' => time(),
		]);
	}

	protected function get_not_release_date() {
		return $this->last_fetch && !$this->last_fetch->release_date && $this->last_fetch->thread_date;
	}

	protected function get_released_recently() {
		if ($this->last_fetch) {
			$date = $this->last_fetch->release_date;
			if ($date) {
				$utc = strtotime($date);
				return strtotime('-' . RECENT_TIMESTR) < $utc;
			}
		}
	}

	protected function relate_last_fetch() {
		return $this->to_first(Fetch::class, 'source_id')
			->where('id in (select max(id) from fetches group by source_id)');
	}

}
