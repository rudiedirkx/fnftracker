<?php

return [
	'version' => 8,
	'tables' => [
		'sources' => [
			'id' => ['pk' => true],
			'priority' => ['unsigned' => true, 'default' => 1],
			'f95_id',
			'name',
			'banner_url',
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
