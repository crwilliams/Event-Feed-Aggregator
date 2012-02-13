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
?>
<html>
<head>
<script type="text/javascript" src="http://code.jquery.com/jquery-1.7.1.min.js"></script>
<script type="text/javascript">
var showCats = function()
{
	$('div.day').show();
	$('div.event').hide();
	$('div.event').each(function(index, value) {
		if($(this).hasClass($('#org').val()) && $(this).hasClass($('#type').val()) && $(this).hasClass($('#place').val()))
		{
			$(this).show();
		}
	});
	$('div.day').each(function(index, value) {
		if($(this).children().filter(':visible').size() == 1)
		{
			$(this).hide();
		}
	});
}
</script>
<style>
h3 {
	float:left;
	font-size:1.5em;
	margin:0px;
}
div.speakers {
}
div.description {
	font-size:1.2em;
	text-align:justify;
}
div.event {
	position: relative;
	border: solid 3px navy;
	padding: 5px;
	margin: 5px;
}
div.event-info {
	position: relative;
	float: right;
	width: 30%;
	height: 100%;
	margin: 10px;
	padding: 10px;
	margin-top: 0px;
	margin-right: 0px;
}
</style>
<title>University of Southampton | Diary</title>
<meta charset="utf-8" />
</head>
<body>
<?php
require_once( "/var/wwwsites/phplib/arc/ARC2.php" );
require_once( "/var/wwwsites/phplib/Graphite.php" );

$basetime=microtime(true);
$graph = Graphite::thaw( "/home/diary/var/data.php" );
print "<!-- LOAD diary: ".(microtime(true)-$basetime)." -->";
$basetime=microtime(true);

$graph->cacheDir("/home/diary/diary.soton.ac.uk/cache");

$graph->ns( "event", "http://purl.org/NET/c4dm/event.owl#" );
$graph->ns( "tl", "http://purl.org/NET/c4dm/timeline.owl#" );

function getPlaceLabel($place)
{
	$str = "";
	// Try to get a rdfs:label which is not simply the building/room number
	foreach($place->all("rdfs:label") as $label)
	{
		if(!preg_match('/^[0-9]+[A-Z] \/ [0-9]+$/', $label))
		{
			if(substr($place, 0, 34) == 'http://id.southampton.ac.uk/event/')
				$str = $label;
			else
				$str = "<a href='".$place."'>" . $label . "</a>";
		}
	}
	// If that fails, use any label
	if($str == "")
	{
		if(substr($place, 0, 34) == 'http://id.southampton.ac.uk/event/')
			$str = $place->label();
		else
			$str = "<a href='".$place."'>" . $place->label() . "</a>";
	}
	if($place->has("http://data.ordnancesurvey.co.uk/ontology/spatialrelations/within"))
	{
		$within = $place->get("http://data.ordnancesurvey.co.uk/ontology/spatialrelations/within");
		$str .= ", ".getPlaceLabel($within);
	}
	return $str;
}

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
		continue;
	echo "<div class='day'>";
	echo "<h2>".date('l jS F Y', strtotime($date))."</h2>";
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
	echo "</div>";
}

$orgtree = getOrganisationTree($graph->resource("http://id.southampton.ac.uk/"), array_keys($organisers));

asort($organisers);
print "<select id='org' onchange='showCats()' style='position:fixed; top:5px; right:5px;'>";
print "<option value='event'>(Show entire university)</option>";
printChildrenOptions($orgtree[md5('http://id.southampton.ac.uk/')]);
print "</select>";

asort($places);
print "<select id='place' onchange='showCats()' style='position:fixed; top:25px; right:5px;'>";
print "<option value='event'>(Show all locations)</option>";
foreach($places as $key => $name)
{
	print "<option value='$key'>$name</option>";
}
print "</select>";

asort($types);
print "<select id='type' onchange='showCats()' style='position:fixed; top:45px; right:5px;'>";
print "<option value='event'>(Show all types)</option>";
foreach($types as $key => $name)
{
	print "<option value='$key'>$name</option>";
}
print "</select>";

function printChildrenOptions($node, $depth = 0) {
	if(!isset($node['children']))
		return;
	foreach($node['children'] as $key => $d)
	{
		print "<option value='$key'>";
		for($i = 0; $i < $depth; $i++)
			print "- ";
		print $d['name']."</option>";
		printChildrenOptions($d, $depth + 1);
	}
}

function formatEvent($time, $date)
{
	$event = $time->get("-event:time");
	$organisers = array();
	getOrganisers($organisers, $event);
	$types = array();
	getTypes($types, $event);
	$places = array();
	getPlaces($places, $event);

	print "<div class='event ".implode(" ", array_keys($organisers))." ".implode(" ", array_keys($types))." ".implode(" ", array_keys($places))."'>";
	print "<h3>".$event->label()."</h3>";
	print "<div class='event-info'>";
	if( $event->has( "event:homepage" ) )
	{
		print "<a href='".$event->get( "event:homepage" )."'>Visit event homepage</a>";
	}
	if( $time->has( "tl:start" ) && substr($time->getString("tl:start"), 0, 10) == $date )
	{
		print "<div>";
		print formatTime($time->getString( "tl:start" ), $date);
		if( $time->has( "tl:end" ) )
		{
			print " - ".formatTime($time->getString( "tl:end" ), $date);
		}
		print "</div>";
	}
	outputPlaces($event, "Place");
	outputPlaces($event, "Additional Place Info");
	$organisers = getAgents($event, "Organiser");
	if(count($organisers) > 0)
	{
		sort($organisers);
		print "<div class='organisers'>Organised by: ";
		foreach($organisers as $organiser)
		{
			print $organiser." ";
		}
		print "</div>";
	}
	print "</div>";
	print "<div style='clear:left'></div>";
	$speakers = getAgents($event, "Speaker");
	if(count($speakers) > 0)
	{
		print "<div class='speakers'>Speaker".((count($speakers) > 1) ? "s" : "").": ";
		foreach(getAgents($event, "Speaker") as $speaker)
		{
			print $speaker." ";
		}
		print "</div>";
	}
	if( $event->has( "dct:description" ) )
	{
		print "<div class='description'>".$event->getString( "dct:description" )."</div>";
	}
	print "<div style='clear:both'></div>";
	print "</div>";
}

function formatTime($time, $date) {
	if(substr($time, 0, 10) != $date)
	{
		return substr($time, 0, 10)." ".substr($time, 11, 5);
	}
	return substr($time, 11, 5);
}

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
				print "<div>$typel".$place->link()."</div>";
			}
			else
			{
				print "<div>$typel".getPlaceLabel($place)."</div>";
			}
		}
	}
}

function getTypes(&$types, $event)
{
	foreach( $event->all("http://id.southampton.ac.uk/ns/diary/event-type") as $type )
	{
		$typename = str_replace('http://id.southampton.ac.uk/ns/diary/', '', (string)$type);
		if(trim($typename) != "")
			$types[md5((string)$type)] = trim(preg_replace('/([A-Z])/', ' \1', $typename));
	}
	return $types;
}

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

function sortOrgTree($a, $b) {
	if($a['name'] == $b['name']) return 0;
	return ($a['name'] < $b['name']) ? -1 : 1;
}

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

print "<!-- ";
print "OTHER: ".(microtime(true)-$basetime)." -->";
?>
</body>
</html>
