<?php

function aro_group( array $objects, string $property ) : array {
	$groups = [];
	foreach ($objects as $object) {
		$groups[$object->$property][] = $object;
	}
	return $groups;
}

function get_url( $path, $query = array() ) {
	$query = $query ? '?' . http_build_query($query) : '';
	$fragment = '';
	if (count($x = explode('#', $path, 2)) > 1) {
		$path = $x[0];
		$fragment = '#' . $x[1];
	}
	$path = $path ? $path . '.php' : basename($_SERVER['SCRIPT_NAME']);
	return $path . $query . $fragment;
}

function do_json( array $data ) {
	header('Content-type: application/json; charset=utf-8');
	echo json_encode($data);
	exit;
}

function do_redirect( $path, $query = array() ) {
	$url = get_url($path, $query);
	header('Location: ' . $url);
}

function html_asset( $src ) {
	$buster = '?_' . filemtime($src);
	return $src . $buster;
}

function html( $text ) {
	return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8') ?: htmlspecialchars((string)$text, ENT_QUOTES, 'ISO-8859-1');
}
