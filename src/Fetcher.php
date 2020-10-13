<?php

namespace rdx\f95;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RedirectMiddleware;
use rdx\jsdom\Node;

class Fetcher {

	const KEEP_PREFIXES = ['completed', 'onhold', 'abandoned'];

	public $source;
	public $url;

	public $developer;
	public $banner;

	public function __construct(Source $source) {
		$this->source = $source;

		$this->url = strtr(F95_URL, [
			'{name}' => 'x',
			'{id}' => $this->source->f95_id,
		]);
	}

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

		[$doc, $redirects] = $this->getRemote($guzzle, $this->url);

		$text = $this->getLdText($doc);

		[$release, $thread] = $this->getDates($text);
		$version = $this->getVersion($text);
		$this->banner = $this->getBanner($doc);
		$this->developer = $this->getDeveloper($doc, $text);
		$prefixes = $this->getPrefixes($doc);

		$this->source->update([
			'banner_url' => $this->banner,
			'developer' => $this->developer,
		]);

		return Fetch::insert([
			'source_id' => $this->source->id,
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

	protected function getDeveloper(Node $doc, string $text) {
		$body = $doc->query('.message-threadStarterPost .message-body > .bbWrapper')->innerText;
		if (preg_match('#\sDeveloper(?:/[Pp]ublisher)?: *([^\r\n]+)#', $body, $match)) {
			return trim($match[1], '- ');
		}

		if (preg_match('#\sDeveloper(?:/[Pp]ublisher)?: *([^\r\n]+)#', $text, $match)) {
			return trim($match[1], '- ');
		}
	}

	protected function getVersion(string $text) {
		if (preg_match('#\sVersion: *([^\r\n]+)#', $text, $match)) {
			$version = trim($match[1]);
			return $version;
		}
	}

	protected function formatDate(string $date) {
		return date('Y-m-d', strtotime(str_replace(' ', '', $date)));
	}

	protected function getDate(string $text, string $pattern) {
		$datePattern = '\d\d\d\d ?- ?\d\d? ?- ?\d\d?';
		return preg_match("#\s$pattern: *($datePattern)#", $text, $match) ? $this->formatDate($match[1]) : null;
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

}