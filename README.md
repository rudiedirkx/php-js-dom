JS-like DOM traversal in PHP.

Uses PHP's native `DOMDocument` and Symfony's `CssSelector`.

	use rdx\jsdom\Node;

	$doc = Node::create(file_get_contents('pathe.html'));

	// Find 1 element. Returns Node|null.
	$section = $doc->query('section.schedule-simple');

	// Find all elements. Returns array.
	$movies = $section->queryAll('.schedule-simple__item');
	foreach ($movies as $movie) {
		// Every element is a Node.
		$a = $movie->query('h4 > a');

		// Every element has an innerText/textContent.
		$title = $a->getText();

		// Attributes are array access.
		$href = $a['href'];
	}
