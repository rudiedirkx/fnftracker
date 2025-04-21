<?php

namespace rdx\f95;

abstract class IndexContent {

	public bool $deleting = false;
	public bool $editing = false;
	public bool $collapseUntracked = false;
	public bool $showSourceDetectedInsteadOfChecked = false;

	public int $hiliteSource = 0;

	public string $sourcesSql;
	public string $sourcesSorted;
	public array $sources;

	public string $releasesSorted = 'first_fetch_on';
	public array $releases;

	public int $totalSources;
	public int $totalReleases;

	public function setTotals(int $sources, int $releases) : void {
		$this->totalSources = $sources;
		$this->totalReleases = $releases;
	}

	public function getSourcesCountLabel() : string {
		return count($this->sources) . ' / ' . $this->totalSources;
	}

	public function getReleasesCountLabel() : string {
		return count($this->releases) . ' / ' . $this->totalReleases;
	}

	public function eagerLoad() : void {
		Source::eager('num_releases', $this->sources);
		$this->eagerLoadSources($this->sources);

		$_sources = Release::eager('source', $this->releases);
		$this->eagerLoadSources($_sources);
	}

	protected function eagerLoadSources(array $sources) : void {
		Source::eager('last_release', $sources);
		Source::eager('characters', $sources);
		Source::eager('versions', $sources);
	}

	abstract public function getNoSourcesMessage() : string;

	static public function fromSearch(string $search) : self {
		if ( $search === '*' ) {
			return new IndexContentAll();
		}

		if ( strlen($search) ) {
			return new IndexContentSearch($search);
		}

		return new IndexContentDefault();
	}

}
