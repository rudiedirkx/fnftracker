<?php

return [
	'version' => 7,
	'tables' => [
		'sources' => [
			'id' => ['pk' => true],
			'active' => ['type' => 'int', 'default' => 1],
			'f95_id',
			'name',
			'banner_url',
		],
		'fetches' => [
			'id' => ['pk' => true],
			'source_id' => ['unsigned' => true, 'references' => ['sources', 'id']],
			'created_on' => ['unsigned' => true, 'null' => false, 'default' => 0],
			'url',
			'release_date',
			'thread_date',
			'version',
			'prefixes',
		],
	],
];
