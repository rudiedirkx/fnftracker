<?php

namespace rdx\f95;

use DOMText;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\RedirectMiddleware;
use rdx\jsdom\Node;

class Fetcher {

	const STATUS_PREFIXES = ['completed', 'onhold', 'abandoned'];
	const KEEP_PREFIXES = [
		...self::STATUS_PREFIXES,
		'rpgm', 'unity', 'html', 'others', 'flash', 'unreal engine', 'java',
	];

	public $source;
	public $url;

	public $name;
	public $release;
	public $thread;
	public $version;
	public $prefixes;

	public $rating;
	public $developer;
	public $patreon;
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
		// $text = $this->getLdText($doc);
		$text = $this->getOPText($doc);
		if (!$text) return 0;

		$this->name = $this->getName($doc);
		[$this->release, $this->thread] = $this->getDates($text);
		$this->version = $this->getVersion($text);
		$this->banner = $this->getBanner($doc);
		$this->developer = $this->getDeveloper($doc, $text);
		$this->patreon = $this->getPatreon($doc, $text);
		$this->rating = $this->getRating($doc);
		$this->prefixes = implode(',', $this->getPrefixes($doc)) ?: null;

		$this->persistSource();

		$update = [
			'banner_url' => $this->banner,
			'f95_rating' => $this->rating,
		];
		if (!$this->source->custom_developer) {
			$update['developer'] = $this->developer;
		}
		if (!$this->source->custom_patreon) {
			$update['patreon'] = $this->patreon;
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

	protected function persistSource() {
		if (!$this->source->id) {
			$data = [
				'f95_id' => $this->source->f95_id,
				'name' => $this->name,
				'created_on' => time(),
				'priority' => max(array_keys(Source::PRIORITIES)),
			];
			$this->source->id = Source::insert($data);
		}
	}

	protected function significantlyDifferent(Release $release) {
		$previous = ($release->release_date ?? $release->thread_date) . "::$release->version::$release->prefixes";
		$current = ($this->release ?? $this->thread) . "::$this->version::$this->prefixes";
		return $previous !== $current;
	}

	protected function getName(Node $doc) {
		$h1 = $doc->query('.pageContent h1');
		foreach ($h1->childNodes as $node) {
			if ($node instanceof DOMText) {
				$name = $node->textContent;
				$name = preg_replace('#\[.+#', '', $name);
				return trim($name);
			}
		}
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

	protected function getRating(Node $doc) : ?int {
		// Voting widget
		$select = $doc->query('select[name="rating"][data-initial-rating][data-vote-content]');
		if ($select) {
			if (preg_match('#\b(\d+) Votes?\b#', $select['data-vote-content'], $match)) {
				if ($match[1] < 2) {
					return null;
				}
			}

			$rating = (float) $select['data-initial-rating'];
			if ($rating > 0) {
				return round(20 * $rating);
			}
			return null;
		}

		// Having voted stars
		$stars = $doc->query('.p-title-pageAction .bratr-rating[title]');
		if (preg_match('#(\d+(?:\.\d+)?) star#', $stars->textContent, $match)) {
			$rating = (float) $match[1];
			return round(20 * $rating);
		}

		return null;
	}

	protected function getDeveloperLinks(Node $container) : array {
		$el = $container->xpathRaw('//b[text()="Developer"]')[0] ??
			$container->xpathRaw('//b[text()="Developer:"]')[0] ??
			$container->xpathRaw('//b[text()="Developer: "]')[0] ??
			$container->xpathRaw('//b[text()="Publisher"]')[0] ??
			$container->xpathRaw('//b[text()="Developer/Publisher"]')[0] ??
			null;
		if (!$el) return [];

		$nodes = [];
		for ($i = 0; $i < 20; $i++) {
			$el = $el->nextSibling;
			if (!$el) {
				return $nodes;
			}
			if ($el->nodeName == 'br' || $el->nodeValue == 'Version') {
				return $nodes;
			}
			elseif ($el->nodeName == 'a') {
				$nodes[] = new Node($el);
			}
			elseif (count($as = (new Node($el))->queryAll('a')) == 1) {
				$nodes[] = $as[0];
			}
		}

		return [];
	}

	protected function getPatreonUsername(string $url) {
		$path = trim(preg_replace('#^https?://(www\.)?patreon\.com/#', '', $url), '/');
		if (preg_match('#^user(?:/(?:posts|about))?\?u=(\d+)($|&)#', $path, $match)) {
			return 'u:' . $match[1];
		}

		$path = trim(preg_replace('/[\?#].+$/', '', $path), '/');
		$path = preg_replace('#/(overview|posts)$#', '', $path);
		if ($path && strpos($path, '/') === false) {
			return $path;
		}
	}

	protected function getPatreon(Node $doc, string $text) {
		$container = $doc->query('.message-threadStarterPost .message-body');

		$links = $this->getDeveloperLinks($container);
		foreach ($links as $link) {
			if (preg_replace('#^www\.#', '', parse_url($link['href'], PHP_URL_HOST)) == 'patreon.com') {
				return $this->getPatreonUsername($link['href']);
			}
		}

		// $link = $container->query('a[href*="patreon.com/"]');
		// if ($link) {
		// 	return $this->getPatreonUsername($link['href']);
		// }
	}

	protected function getDeveloper(Node $doc, string $text) {
		$clean = function($name) {
			return explode(' - ', trim(preg_replace('#\s+f95zone$#i', '', trim($name, '- '))))[0];
		};
		$trim = function($name) {
			return trim(preg_replace('# (interactive|games|studios?|productions?)$#i', '', trim($name, '!?')));
		};

		$title = $doc->query('head title');
		if ($title && preg_match('#[\[\(]([^\]\)]+)[\]\)] \| F95zone$#i', $title->innerText, $match)) {
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
		$release = $this->getDate($text, '(?:Released? [Dd]ate|Game [Uu]pdated)');
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

	protected function getOPText(Node $doc) {
		$el = $doc->query('.message:not(.sticky-container) .message-content');
		$text = trim(preg_replace('# +#', ' ', trim($el->innerText ?? '')));
		return $text;
	}

}
