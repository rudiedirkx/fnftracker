<?php

namespace rdx\f95;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\RedirectMiddleware;
use rdx\jsdom\Node;

class Fetcher {

	const KEEP_PREFIXES = [
		'completed', 'onhold', 'abandoned',
		'rpgm', 'unity', 'html', 'others', 'flash', 'unreal engine',
	];

	public $source;
	public $url;

	public $release;
	public $thread;
	public $version;
	public $prefixes;

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
			'headers' => ['User-Agent' => F95_FETCHER_UA],
			'allow_redirects' => [
				'track_redirects' => true,
			] + RedirectMiddleware::$defaultSettings,
		]);
	}

	public function sync(Guzzle $guzzle = null, $catch = false) : int {
		$guzzle or $guzzle = self::makeGuzzle();

		try {
			[$html, $url] = $this->getRemote($guzzle, $this->url);
		}
		catch (TransferException $ex) {
			if (!$catch) {
				throw $ex;
			}
			return 0;
		}

		return $this->syncFromHtml($html, $url);
	}

	public function syncFromHtml(string $html, string $url = null) : int {
		$doc = Node::create($html);

		$text = $this->getLdText($doc);

		[$this->release, $this->thread] = $this->getDates($text);
		$this->version = $this->getVersion($text);
		$this->banner = $this->getBanner($doc);
		$this->developer = $this->getDeveloper($doc, $text);
		$this->prefixes = implode(',', $this->getPrefixes($doc)) ?: null;

		$update = ['banner_url' => $this->banner];
		if (!$this->source->custom_developer) {
			$update['developer'] = $this->developer;
		}
		$this->source->update($update);

		$previous = $this->source->last_release;
		if (!$previous || $this->significantlyDifferent($previous)) {
			return Release::insert([
				'source_id' => $this->source->id,
				'release_date' => $this->release,
				'thread_date' => $this->thread,
				'version' => $this->version,
				'prefixes' => $this->prefixes,
				'url' => $url,
				'first_fetch_on' => time(),
				'last_fetch_on' => time(),
			]);
		}

		$previous->update([
			'last_fetch_on' => time(),
			'prefixes' => $this->prefixes,
			'thread_date' => $this->thread,
		]);
		return $previous->id;
	}

	protected function significantlyDifferent(Release $release) {
		$previous = ($release->release_date ?? $release->thread_date) . "::$release->version::$release->prefixes";
		$current = ($this->release ?? $this->thread) . "::$this->version::$this->prefixes";
		return $previous !== $current;
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

	protected function getBanner(Node $doc) : ?string {
		$img = $doc->query('.message-threadStarterPost .lbContainer [data-src]');
		if ($img) {
			return $img['data-src'];
		}

		$banner = $doc->query('.message-threadStarterPost a > .bbImage');
		if ($banner) {
			return $banner->parent()['href'];
		}

		return null;
	}

	protected function getDeveloper(Node $doc, string $text) {
		$clean = function($name) {
			return explode(' - ', trim(preg_replace('#\s+f95zone$#i', '', trim($name, '- '))))[0];
		};
		$trim = function($name) {
			return trim(preg_replace('# (games|studios?)$#i', '', $name));
		};

		$title = $doc->query('head title');
		if ($title && preg_match('#\[([^\]]+)\] \| F95zone$#i', $title->innerText, $match)) {
			return $trim($match[1]);
		}

		$body = $doc->query('.message-threadStarterPost .message-body > .bbWrapper')->innerText;
		if (preg_match('#\sDeveloper(?:/[Pp]ublisher)?: *([^\r\n]+)#', $body, $match)) {
			return $trim($clean($match[1]));
		}

		if (preg_match('#\sDeveloper(?:/[Pp]ublisher)?: *([^\r\n]+)#', $text, $match)) {
			return $trim($clean($match[1]));
		}
	}

	protected function getVersion(string $text) {
		if (preg_match('#\sVersion: *([^\r\n]+)#', $text, $match)) {
			$version = trim($match[1]);
			return $version;
		}
	}

	protected function formatDate(string $date) {
		return ($utc = strtotime($date)) ? date('Y-m-d', $utc) : null;
	}

	protected function getDate(string $text, string $textPpattern) {
		$datePattern = '\d\d\d\d ?- ?\d\d? ?- ?\d\d?';
		if (preg_match("#\s$textPpattern: *($datePattern)#", $text, $match)) {
			return $this->formatDate(str_replace(' ', '', $match[1]));
		}

		$datePattern = '\d\d? ?- ?[a-zA-Z]{3} ?- ?\d\d\d\d';
		if (preg_match("#\s$textPpattern: *($datePattern)#", $text, $match)) {
			return $this->formatDate(str_replace('-', ' ', $match[1]));
		}

		$datePattern = '[a-zA-Z]{3} \d\d?,? \d\d\d\d';
		if (preg_match("#\s$textPpattern: *($datePattern)#", $text, $match)) {
			return $this->formatDate(str_replace('-', ' ', $match[1]));
		}

		$datePattern = '(\d\d?)/(\d\d?)/(\d\d\d\d)';
		if (preg_match("#\s$textPpattern: *$datePattern#", $text, $match)) {
			return $this->formatDate("{$match[3]}-{$match[2]}-{$match[1]}") ?? $this->formatDate("{$match[3]}-{$match[1]}-{$match[2]}");
		}
	}

	protected function getDates(string $text) {
		$release = $this->getDate($text, '(?:Release [Dd]ate|Game [Uu]pdated)');
		$thread = $this->getDate($text, 'Thread [Uu]pdated') ?? $this->getDate($text, 'Updated');
		return [$release, $thread];
	}

	protected function getRemote(Guzzle $guzzle, string $url) {
		$rsp = $guzzle->get($url);
		$redirects = $rsp->getHeader(RedirectMiddleware::HISTORY_HEADER);
		$html = (string) $rsp->getBody();
		return [$html, end($redirects)];
	}

	protected function getLdText(Node $doc) {
		$el = $doc->query('script[type="application/ld+json"]');
		$data = json_decode($el->textContent, true);
		return $data['articleBody'];
	}

}
