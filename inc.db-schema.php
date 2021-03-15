<?php

return [
	'version' => 16,
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
