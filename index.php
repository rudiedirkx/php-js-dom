<?php

use rdx\jsdom\Node;

require 'vendor/autoload.php';

header('Content-type: text/plain; charset=utf-8');

$doc = Node::create(file_get_contents('pathe.html'));
// print_r($doc->children());

$section = $doc->query('section.schedule-simple');
// print_r($section);

// var_dump(count($section->children('div')));
// echo "\n";

$movies = $section->queryAll('.schedule-simple__item');
// var_dump(count($movies));
// print_r($movies);
foreach ($movies as $movie) {
	$a = $movie->query('h4 > a');

	$title = $a->getText();
	var_dump($title);

	$href = $a['href'];
	var_dump($href);

	echo "\n";
}
