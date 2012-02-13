<?php
# Based on http://www.sean-barton.co.uk/2009/03/turning-an-array-or-object-into-xml-using-php/

function generateEventXML($array) {
	return generate_valid_xml_from_array($array, 'events', 'event');
}

function generate_xml_from_array($array, $node_name) {
	$xml = '';

	if (is_array($array) || is_object($array)) {
		foreach ($array as $key=>$value) {
			if (is_numeric($key)) {
				$key = $node_name;
			}

			$xml .= '<' . $key . '>' . generate_xml_from_array($value, $node_name) . '</' . $key . '>';
		}
	} else {
		$xml = htmlspecialchars($array, ENT_QUOTES);
	}

	return $xml;
}

function generate_valid_xml_from_array($array, $node_block='nodes', $node_name='node') {
	$xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";

	$xml .= '<' . $node_block . '>' . "\n";
	$xml .= generate_xml_from_array($array, $node_name);
	$xml .= '</' . $node_block . '>' . "\n";

	return $xml;
}

?>
