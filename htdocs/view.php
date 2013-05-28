<!DOCTYPE html>
<?php
# Copyright (c) 2012 Christopher Gutteridge, Colin Williams / University of Southampton
# License: GPL

# This file is part of Event Feed Aggregator.

# Event Feed Aggregator is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.

# Event Feed Aggregator is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.

global $diary_config;
$diary_config["path"] = dirname(dirname(__FILE__));
require($diary_config["path"].'/etc/config.php');

?>
<html>
	<head>
		<script type="text/javascript" src="http://code.jquery.com/jquery-1.7.1.min.js"></script>
		<script type="text/javascript" src="js/filtering.js"></script>
		<link rel="stylesheet" href="css/style.css" />
		<title>Diary</title>
		<meta charset="utf-8" />
	</head>
	<body>
<?php
	printDefaultHomepage();
	if( file_exists( "analytics.php" ) )
	{
		include( "analytics.php" );
	}
?>
</body>
</html>
<?php
exit;

/*
////////////////////////////////////////////////////////////
*/
function printDefaultHomepage()
{
	global $diary_config;
	global $graph; // lets the org tree render see it easily
	$graph = Graphite::thaw( $diary_config["path"]."/var/frozen-graph" );
	#$graph->cacheDir( "/home/diary/diary.soton.ac.uk/cache");
	$graph->ns( "event", "http://purl.org/NET/c4dm/event.owl#" );
	$graph->ns( "tl", "http://purl.org/NET/c4dm/timeline.owl#" );
	
	foreach($graph->allOfType("event:Event") as $event)
	{
		if($event->has("event:time"))
        	{
                	foreach($event->all("event:time") as $time)
                	{
                        	if($time->has("tl:at"))
                        	{
					$events[$time->getString("tl:at")]["0"][] = $time;
                        	}
                        	if($time->has("tl:start"))
                        	{
                                	$start = $time->getString("tl:start");
					$events[substr($time->getString("tl:start"), 0, 10)][substr($time->getString("tl:start"), 11, 5)][] = $time;
                        		if($time->has("tl:end"))
                        		{
                        	        	$end = $time->getString("tl:end");
                        		}
                        	}
                	}
        	}
	}
		
	ksort($events);
	
	$organisers = array();
	$types = array();
	$places = array();
	foreach($events as $date => $dayevents)
	{
		if($date < date('Y-m-d'))
		{
			continue;
		}
		echo "<div class='day'>\n";
		echo "\t<h2>".date('l jS F Y', strtotime($date))."</h2>\n";
		ksort($dayevents);
		foreach($dayevents as $time => $timeevents)
		{
			foreach($timeevents as $eventtime)
			{
				formatEvent($eventtime, $date);
				getOrganisers($organisers, $eventtime->get("-event:time"));
				getTypes($types, $eventtime->get("-event:time"));
				getPlaces($places, $eventtime->get("-event:time"));
			}
		}
		echo "</div>\n";
	}

	printDropDowns($organisers, 'org', 'Show entire university', 'printOrganisationTreeOptions');
	printDropDowns($places, 'place', 'Show all locations');
	printDropDowns($types, 'type', 'Show all types');
}


/**
 * Print a drop-down box to select from a set of values.
 *
 */
function printDropDowns($values, $id, $showAllString, $processOptions) {
	asort($values);
	print "<select id='$id' onchange='showCats()'>\n";
	print "\t<option value='event'>($showAllString)</option>\n";
	if($processOptions == null) {
		foreach($values as $key => $name) {
			print "\t<option value='$key'>$name</option>\n";
		}
	} else {
		$processOptions($values);
	}
	print "</select>\n";
}

/**
 * Print a set of options representing the organisational structure.
 *
 */
function printOrganisationTreeOptions($values, $node = null, $depth = 0) {
	global $graph;
	global $diary_config;
	if($node == null) {
		$orgtree = getOrganisationTree($graph->resource($diary_config["master_org_uri"] ), array_keys($values));
		printOrganisationTreeOptions($values, $orgtree[md5($diary_config["master_org_uri"])]);
	}
	if(!isset($node['children'])) {
		return;
	}
	foreach($node['children'] as $key => $d) {
		print "\t<option value='$key'>";
		for($i = 0; $i < $depth; $i++) {
			print "- ";
		}
		print $d['name']."</option>\n";
		printOrganisationTreeOptions($values, $d, $depth + 1);
	}
}

/**
 * Format a single event.
 *
 */
function formatEvent($time, $date)
{
	$event = $time->get("-event:time");
	$organisers = array();
	getOrganisers($organisers, $event);
	$types = array();
	getTypes($types, $event);
	$places = array();
	getPlaces($places, $event);

	print "<div class='event ".implode(" ", array_keys($organisers))." ".implode(" ", array_keys($types))." ".implode(" ", array_keys($places))."'>\n";
	print "\t<h3>".$event->label()."</h3>\n";
	print "\t<div class='event-info'>\n";
	if( $event->has( "foaf:homepage" ) )
	{
		print "\t\t<a href='".$event->get( "foaf:homepage" )."'>Visit event homepage</a>\n";
	}
	if( $time->has( "tl:start" ) && substr($time->getString("tl:start"), 0, 10) == $date )
	{
		print "\t\t<div>";
		print formatTime($time->getString( "tl:start" ), $date);
		if( $time->has( "tl:end" ) )
		{
			print " - ".formatTime($time->getString( "tl:end" ), $date);
		}
		print "</div>\n";
	}
	outputPlaces($event, "Place");
	outputPlaces($event, "Additional Place Info");
	$organisers = getAgents($event, "Organiser");
	if(count($organisers) > 0)
	{
		sort($organisers);
		print "\t\t<div class='organisers'>Organised by: ";
		foreach($organisers as $organiser)
		{
			print $organiser." ";
		}
		print "</div>\n";
	}
	print "\t</div>\n";
	print "\t<div style='clear:left'></div>\n";
	$speakers = getAgents($event, "Speaker");
	if(count($speakers) > 0)
	{
		print "\t<div class='speakers'>Speaker".((count($speakers) > 1) ? "s" : "").": ";
		foreach(getAgents($event, "Speaker") as $speaker)
		{
			print $speaker." ";
		}
		print "</div>\n";
	}
	if( $event->has( "dct:description" ) )
	{
		print "\t<div class='description'>".$event->getString( "dct:description" )."</div>\n";
	}
	print "\t<div style='clear:both'></div>\n";
	print "</div>\n";
}

/**
 * Format a time.
 *
 */
function formatTime($time, $date) {
	if(substr($time, 0, 10) != $date)
	{
		return substr($time, 0, 10)." ".substr($time, 11, 5);
	}
	return substr($time, 11, 5);
}

/**
 * Output all places associated with an event.
 *
 */
function outputPlaces($event, $filter=null)
{
	if( $event->has( "event:place" ) )
	{
		foreach( $event->all( "event:place" ) as $place )
		{
			if($place->isType("http://vocab.deri.ie/rooms#Room") || $place->isType("http://vocab.deri.ie/rooms#Building") || $place->isType("http://www.w3.org/ns/org#Site"))
				$type = "Place";
			else
				$type = "Additional Place Info";
			if(!is_null($filter) && $filter != $type)
				continue;
			$typel = $type.": ";
			if($type == "Place")
			{
				$typel = "at ";
			}
			elseif($type == "Additional Place Info")
			{
				$style = "";
			}
			if($place->label() == '[NULL]')
			{
				print "\t\t<div>$typel".$place->link()."</div>\n";
			}
			else
			{
				print "\t\t<div>$typel".getPlaceLabel($place)."</div>\n";
			}
		}
	}
}

/**
 * Get the types of an event.
 *
 */
function getTypes(&$types, $event)
{
	global $diary_config;	
	$event_type_term = $diary_config["ns"]["diaryterms"]."event-type";

	foreach( $event->all($event_type_term) as $type )
	{
		$typename = substr( (string)$type, strlen( $diary_config["ns"]["diaryvalues"] ) );
		if(trim($typename) != "")
		{
			$types[md5((string)$type)] = trim(preg_replace('/([A-Z])/', ' \1', $typename));
		}
	}
	return $types;
}

/**
 * Get the organisation tree, rooted at the given node, filtered according to the filter.
 *
 */
function getOrganisationTree($node, $filter)
{
	$tree = array();
	foreach($node->all("http://www.w3.org/ns/org#hasSubOrganization") as $child)
	{
		$subtree = getOrganisationTree($child, $filter);
		if(count($subtree) > 0)
		{
			foreach($subtree as $k => $v)
			{
				$tree[md5((string)$node)]['children'][$k] = $v;
			}
		}
	}
	if(count($tree) > 0 || in_array(md5((string)$node), $filter))
	{
		@uasort($tree[md5((string)$node)]['children'], 'sortOrgTree');
		$tree[md5((string)$node)]['name'] = $node->label();
	}
	return $tree;
}

/**
 * Compare elements in the organisation tree.
 *
 */
function sortOrgTree($a, $b) {
	if($a['name'] == $b['name']) return 0;
	return ($a['name'] < $b['name']) ? -1 : 1;
}

/**
 * Get the organisers of an event.
 *
 */
function getOrganisers(&$organisers, $event)
{
	if( $event->has( "event:agent" ) )
	{
		foreach( $event->all( "event:agent" ) as $agent )
		{
			if(!$agent->isType("http://www.w3.org/ns/org#Organization"))
			{
				continue;
			}
			$organisers[md5((string)$agent)] = $agent->label();
			while($agent->has("-http://www.w3.org/ns/org#hasSubOrganization"))
			{
				$agent = $agent->get("-http://www.w3.org/ns/org#hasSubOrganization");
				$organisers[md5((string)$agent)] = $agent->label();
			}
		}
	}
}

/**
 * Get the location of an event.
 *
 */
function getPlaces(&$places, $event)
{
	if( $event->has( "event:place" ) )
	{
		foreach( $event->all( "event:place" ) as $place )
		{
			$site = getSite($place);
			if($site != null)
			{
				$places[md5((string)$site)] = $site->label();
			}
		}
	}
}

/**
 * Get the site that a place belongs to.
 *
 */
function getSite($place)
{
	if($place->isType("http://www.w3.org/ns/org#Site"))
	{
		return $place;
	}
	else if($place->has("http://data.ordnancesurvey.co.uk/ontology/spatialrelations/within"))
	{
		return getSite($place->get("http://data.ordnancesurvey.co.uk/ontology/spatialrelations/within"));
	}
	else
	{
		return null;
	}
}

/**
 * Get the agents related to an event.
 *
 */
function getAgents($event, $filter=null)
{
	$agents = array();
	if( ! $event->has( "event:agent" ) ) { return $agents; }

	foreach( $event->all( "event:agent" ) as $agent )
	{
		if($agent->isType("http://www.w3.org/ns/org#Organization"))
		{
			$type = "Organiser";
		}
		else
		{
			$type = "Speaker";
		}

		if(!is_null($filter) && $filter != $type) { continue; }

		if( !$agent->hasLabel() )
		{
			$agents[] = $agent->link();
		}
		else
		{
			if($agent->has("foaf:homepage"))
			{
				$agents[] = "<a href='".$agent->get("foaf:homepage")."'>".$agent->label()."</a>";
			}
			else
			{
				$agents[] = $agent->label();
			}
		}
	}
	return $agents;
}

function getPlaceLabel($place)
{
	$str = "";
	global $diary_config;
	// Try to get a rdfs:label which is not simply the building/room number.
	foreach($place->all("rdfs:label") as $label)
	{
		if(!preg_match('/^[0-9]+[A-Z] \/ [0-9]+$/', $label))
		{
			# if the URI is in the local event namespace
			if( strpos( $place, $diary_config["ns"]["localevent"] ) === 0 )
			{
				$str = $label;
			}
			else
			{
				$str = "<a href='".$place."'>" . $label . "</a>";
			}
		}
	}
	// If that fails, use any label.
	if($str == "")
	{
		# if the URI is in the local event namespace
		if( strpos( $place, $diary_config["ns"]["localevent"] ) === 0 )
		{
			$str = $place->label();
		}
		else
		{
			$str = "<a href='".$place."'>" . $place->label() . "</a>";
		}
	}
	if($place->has("http://data.ordnancesurvey.co.uk/ontology/spatialrelations/within"))
	{
		$within = $place->get("http://data.ordnancesurvey.co.uk/ontology/spatialrelations/within");
		$str .= ", ".getPlaceLabel($within);
	}
	return $str;
}

