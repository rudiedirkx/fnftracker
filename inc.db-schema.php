<?php

return [
	'version' => 19,
	'tables' => [
		'sources' => [
			'id' => ['pk' => true],
			'created_on' => ['unsigned' => true, 'null' => false, 'default' => 0],
			'priority' => ['unsigned' => true, 'default' => 1],
			'f95_id',
			'name',
			'description',
			'banner_url',
			'developer',
			'patreon',
			'f95_rating' => ['unsigned' => true],
			'finished' => ['type' => 'date'],
			'installed',
		],
		'releases' => [
			'id' => ['pk' => true],
			'source_id' => ['unsigned' => true, 'references' => ['sources', 'id', 'cascade']],
			'first_fetch_on' => ['unsigned' => true, 'null' => false, 'default' => 0],
			'last_fetch_on' => ['unsigned' => true, 'null' => false, 'default' => 0],
			'url',
			'release_date',
			'thread_date',
			'version',
			'prefixes',
			'f95_rating' => ['unsigned' => true],
		],
		'characters' => [
			'id' => ['pk' => true],
			'source_id' => ['unsigned' => true, 'references' => ['sources', 'id', 'cascade']],
			'name',
			'role',
			'url',
		],
	],
];
