<?php

use rdx\jsdom\Node;

require 'vendor/autoload.php';

header('Content-type: text/plain; charset=utf-8');

$doc = Node::create(file_get_contents('https://www.pathe.nl/bioscoop/city'));
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

	$deeperA = $a->query('a');
// var_dump($deeperA ? "$deeperA->tagName[class=" . $deeperA['class'] . "][title=" . $deeperA['title'] . "]" : NULL);
	$closestDiv = $a->closest('div.schedule-simple__content');
// var_dump($closestDiv ? "$closestDiv->tagName[class=" . $closestDiv['class'] . "][title=" . $closestDiv['title'] . "]" : NULL);
	$firstA = $closestDiv->query('a');
// var_dump($firstA ? "$firstA->tagName[class=" . $firstA['class'] . "][title=" . $firstA['title'] . "]" : NULL);

	$title = $a->innerText;
	var_dump($title);

	$href = $a['href'];
	var_dump($href);

	echo "\n";
}
