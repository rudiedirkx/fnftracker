<?php

return [
	'version' => 13,
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
		'fetches' => [
			'id' => ['pk' => true],
			'source_id' => ['unsigned' => true, 'references' => ['sources', 'id', 'cascade']],
			'created_on' => ['unsigned' => true, 'null' => false, 'default' => 0],
			'url',
			'release_date',
			'thread_date',
			'version',
			'prefixes',
		],
	],
];
