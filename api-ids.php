<?php

use rdx\f95\Source;

require 'inc.bootstrap.php';

header('Access-Control-Allow-Private-Network: true');
header('Access-Control-Allow-Origin: https://' . F95_HOST);
header('Content-type: application/json; charset=utf-8');

$ids = isset($_GET['ids']) ? array_filter(is_array($_GET['ids']) ? $_GET['ids'] : explode(',', $_GET['ids'])) : [];
$where = count($ids) ? $db->replaceholders("f95_id IN (?)", [$ids]) : '1=1';
$sources = Source::all("priority >= 0 AND f95_id IS NOT NULL AND $where ORDER BY f95_id");
$ids = array_unique(array_map(fn($id) => intval($id), array_column($sources, 'f95_id')));
sort($ids, SORT_NUMERIC);

echo json_encode(['ids' => $ids]);
