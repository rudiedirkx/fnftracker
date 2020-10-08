<?php

namespace rdx\f95;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RedirectMiddleware;
use rdx\jsdom\Node;

class Source extends Model {

	const PRIORITIES = [0 => null, 1 => '8 days', 2 => '4 days', 3 => '2 days'];
	const KEEP_PREFIXES = ['completed', 'onhold', 'abandoned'];

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

		[$doc, $redirects] = $this->getRemote($guzzle, $url);

		$text = $this->getLdText($doc);

		[$release, $thread] = $this->getDates($text);
		$version = $this->getVersion($text);
		$banner = $this->getBanner($doc);
		$developer = $this->getDeveloper($doc);
		$prefixes = $this->getPrefixes($doc);

		$this->update([
			'banner_url' => $banner,
			'developer' => $developer,
		]);

		return Fetch::insert([
			'source_id' => $this->id,
			'release_date' => $release,
			'thread_date' => $thread,
			'version' => $version,
			'prefixes' => implode(',', $prefixes) ?: null,
			'url' => end($redirects),
			'created_on' => time(),
		]);
	}

	protected function getPrefixes(Node $doc) {
		$els = $doc->queryAll('h1 .labelLink');

		$prefixes = [];
		foreach ($els as $el) {
			if (in_array($prefix = strtolower(trim($el->textContent, '[]')), self::KEEP_PREFIXES)) {
				$prefixes[] = $prefix;
			}
		}

		return $prefixes;
	}

	protected function getBanner(Node $doc) {
		$banner = $doc->query('.message-threadStarterPost a > .bbImage');
		return $banner->parent()['href'];
	}

	protected function getDeveloper(Node $doc) {
		$body = $doc->query('.message-threadStarterPost .message-body > .bbWrapper')->innerText;
		return preg_match('#\sDeveloper:\s*([^\r\n]+)#', $body, $match) ? trim($match[1], '- ') : null;
	}

	protected function getVersion(string $text) {
		if (preg_match('#\sVersion:\s*([^\r\n]+)#', $text, $match)) {
			$version = trim($match[1]);
			return $version;
		}
	}

	protected function formatDate(string $date) {
		return date('Y-m-d', strtotime(str_replace(' ', '', $date)));
	}

	protected function getDate(string $text, string $pattern) {
		$datePattern = '\d\d\d\d ?- ?\d\d? ?- ?\d\d?';
		return preg_match("#\s$pattern:\s*($datePattern)#", $text, $match) ? $this->formatDate($match[1]) : null;
	}

	protected function getDates(string $text) {
		$release = $this->getDate($text, 'Release [Dd]ate');
		$thread = $this->getDate($text, 'Thread [Uu]pdated') ?? $this->getDate($text, 'Updated');
		return [$release, $thread];
	}

	protected function getRemote(Guzzle $guzzle, string $url) {
		$rsp = $guzzle->get($url);
		$redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER);
		$html = (string) $rsp->getBody();
		return [
			Node::create($html),
			$redirects,
		];
	}

	protected function getLdText(Node $doc) {
		$el = $doc->query('script[type="application/ld+json"]');
		$data = json_decode($el->textContent, true);
		return $data['articleBody'];
	}

	protected function get_last_prefix() {
		return $this->last_fetch->prefixes ?? '';
	}

	protected function get_not_release_date() {
		return $this->last_fetch && !$this->last_fetch->release_date && $this->last_fetch->thread_date;
	}

	protected function get_recent_release() {
		if ($this->last_fetch) {
			$date = $this->last_fetch->release_date;
			if ($date) {
				$utc = strtotime($date);
				foreach (RECENT_TIMESTR as $i => $time) {
					if (strtotime("-$time") < $utc) {
						return $i + 1;
					}
				}
			}
		}

		return 0;
	}

	protected function relate_last_fetch() {
		return $this->to_first(Fetch::class, 'source_id')
			->where('id in (select max(id) from fetches group by source_id)');
	}

}
