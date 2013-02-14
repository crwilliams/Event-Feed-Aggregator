<?
# Copyright (c) 2013 Colin Williams / University of Southampton
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

# You should have received a copy of the GNU General Public License
# along with Event Feed Aggregator.  If not, see <http://www.gnu.org/licenses/>.
if(PHP_SAPI !== 'cli' && !isset($_GET['prog']))
{
	header('Location: feeds.html');
	die();
}

global $diary_config;
$diary_config["path"] = dirname(dirname(__FILE__));
require($diary_config["path"].'/etc/config.php');
global $graph; // bit hacky
$graph = Graphite::thaw( $diary_config["path"]."/var/frozen-graph" );

?>
<html>
<head>
<meta charset="utf-8" />
<style>
a:link, a:hover, a:visited {
	color: black;
	text-decoration: none;
}

div.programme {
	border: solid 1px black;
	margin: 5px;
	padding: 5px;
}

div.programme > div.event {
	padding-left: 50px;
}

div.warning {
	background-color: #FFFFCC;
}

div.error {
	background-color: #FFCCCC;
}

ul {
	padding: 0px;
}

ul.issues li {
	list-style-type: none;
	padding-left: 20px;
	margin: 3px;
}

ul.issues li.error {
	border: 3px solid #FF6666;
	background: url('/img/silk/icons/exclamation.png') no-repeat top left #FF9999;
}

ul.issues li.warning {
	border: 3px solid #FFFF66;
	background: url('/img/silk/icons/error.png') no-repeat top left #FFFF99;
}

a.document {
	padding-left: 5px;
}

h2 {
	margin-top: 0px;
}

td img, h2 img, h3 img {
	padding-right: 5px;
}

div.proc<?= md5('http://example.org/type#cache') ?> {
	display: inline;
}

.datagrid {
	border-collapse: collapse;
}
.datagrid th { 
	padding-top: 0.5em;
}
.datagrid td { 
	padding: 5px;
	border: solid 1px #ccc;
}
tr.script_ td {
	text-decoration: line-through;
	background-color: #fcc;
}
tr.status_0 td {
	background-color: #cfc;
}
tr.status_1 td {
	background-color: #ffc;
}
tr.status_2 td {
	background-color: #fcc;
}
tr.status_1 td.feedID {
	background: url('/img/silk/icons/error.png') no-repeat 5px #ffc;
	padding-left: 25px;
}
tr.status_2 td.feedID {
	background: url('/img/silk/icons/exclamation.png') no-repeat 5px #fcc;
	padding-left: 25px;
}

</style>
</head>
<body>
<?

$progprovdata = array();
$allprogerrors = array();
$progstates = array();

$render_mode = "all";
if(isset($_GET['prog']))
{
	$render_mode = "single";
}



if( $render_mode == "single" )
{
	processProgramme($graph->resource(urldecode($_GET['prog'])), $progprovdata, $allprogerrors, $progstates);
}
if( $render_mode == "all" )
{
	foreach($graph->allOfType('http://purl.org/prog/Programme') as $prog)
	{
		processProgramme($prog, $progprovdata, $allprogerrors, $progstates);
	}
}

foreach($progprovdata as $d)
{
	list($prog, $progmaps, $progerrors, $progerrorstate) = $d;
	$prog = $graph->resource($prog);
	$progeventprovdata = array();
	foreach($prog->all('http://purl.org/prog/has_event') as $e)
	{
		$maps = array();
		$errors = array();
		$errorstate = getProvenance($e, $maps, $errors);
		$progeventprovdata[] = array((string)$e, $maps, $errors, $errorstate);
		$progerrorstate = max($progerrorstate, $errorstate);
		$progstates[(string)$prog] = $progerrorstate;
	}
}

if( $render_mode == "all" )
{
	printFeeds($progstates);
}

if( $render_mode == "single" ) {
	foreach($progprovdata as $d) {
		list($prog_uri, $progmaps, $progerrors, $progerrorstate) = $d;
		$prog = $graph->resource($prog_uri);
		$progerrorclass = 'noerror';
		switch($progstates[$prog_uri])
		{
			case 2:
				$progerrorclass = 'error';
				break;
			case 1:
				$progerrorclass = 'warning';
				break;
		}
	
		echo "<div class='$progerrorclass programme'>\n";
		echo "\t<h2><img src='/img/silk/icons/calendar.png' />".$prog->prettyLink()."</h2>\n";
		renderProvenance($prog, $progmaps, $progerrors, "\t");
		foreach($progeventprovdata as $d)
		{
			list($e, $maps, $errors, $errorstate) = $d;
			$errorclass = 'noerror';
			switch($errorstate)
			{
				case 2:
					$errorclass = 'error';
					break;
				case 1:
					$errorclass = 'warning';
					break;
			}
			echo "\t<div class='$errorclass event'>\n";
			echo "\t\t<h3><img src='/img/silk/icons/date.png' />".$graph->resource($e)->prettyLink()."</h3>\n";
			renderProvenance($e, $maps, $errors, "\t\t");
			echo "\t</div>\n";
		}
		echo "</div>\n";
	}
}


?>
</body>
</html>
<?

exit;############################################################ 

function renderProcess($p)
{
	$str = "";
	$p[0] = substr($p[0], 0, -7);
	$p[1] = substr($p[1], 0, -7);
	if($p[0] == $p[1])
	{
		$date = "at ".$p[0];
	}
	elseif($p[0].$p[1] == $p[0])
	{
		$date = "after ".$p[0];
	}
	elseif($p[0].$p[1] == $p[1])
	{
		$date = "by ".$p[1];
	}
	else
	{
		$date = "between ".$p[0]." and ".$p[1];
	}
	switch($p[3])
	{
		case 'http://example.org/type#getRSS':
			$proc = "Get RSS ".$date;
			$str .= "<img src='/img/silk/icons/rss.png' alt='$proc' title='$proc'/>";
			break;
		case 'http://example.org/type#getRDF':
			$proc = "Get RDF ".$date;
			$str .= "<img src='/img/silk/icons/database.png' alt='$proc' title='$proc'/>";
			break;
		case 'http://example.org/type#getHTML':
			$proc = "Get HTML ".$date;
			$str .= "<img src='/img/silk/icons/html.png' alt='$proc' title='$proc' />";
			break;
		case 'http://example.org/type#getFromSharePoint':
			$proc = "Get from SharePoint ".$date;
			$str .= "<img src='/img/silk/icons/world.png' alt='$proc' title='$proc' />";
			break;
		case 'http://example.org/type#cache':
			$proc = "From document cached ".$date;
			$str .= "<img src='/img/silk/icons/disk_multiple.png' alt='$proc' title='$proc' />";
			break;
		default:
			$str .= "<span class='process'>".$p[3]." between ".$p[0]." and ".$p[1]."</span>";
			break;
	}
	if(substr($p[2], 0, 6) != 'cache:')
	{
		$str .= "<a class='document' href='".$p[2]."'>".$p[2]."</a>";
	}
	return $str;
}
function renderProvenance($prog, $maps, $errors, $prefix)
{
	if(isset($errors[(string)$prog]))
	{
		echo $prefix."\t<ul class='issues'>\n";
		foreach($errors[(string)$prog] as $e)
		{
			if($e == 'No events found')
			{
				echo $prefix."\t\t<li class='warning'>".$e."</li>\n";
			}
			else
			{
				echo $prefix."\t\t<li class='error'>".$e."</li>\n";
			}
		}
		echo $prefix."\t</ul>\n";
	}
	if(isset($maps[(string)$prog]))
	{
		foreach($maps[(string)$prog] as $p)
		{
			echo $prefix."<div class='proc".md5($p[3])."'>\n";
			echo $prefix."\t".renderProcess($p)."\n";
			renderProvenance($p[2], $maps, $errors, $prefix."\t");
			echo $prefix."</div>\n";
		}
	}
}

function getProvenance($doc, &$maps, &$errors)
{
	$errorstate = 0;
	foreach($doc->all('-http://purl.org/void/provenance/ns/resultingDataset') as $pe)
	{
		$src = $pe->all('http://purl.org/void/provenance/ns/sourceDataset');
		$process = $pe->get('http://purl.org/void/provenance/ns/processType');
		$start = str_replace(array("[NULL]", "T"), array("", " "), $pe->get('http://www.w3.org/2006/time#hasBeginning'));
		$end = str_replace(array("[NULL]", "T"), array("", " "), $pe->get('http://www.w3.org/2006/time#hasEnd'));
		foreach($src as $s)
		{
			$entry = array((string)$start, (string)$end, (string)$s, (string)$process);
			if(!@in_array($entry, $maps[(string)$doc]))
			{
				@$maps[(string)$doc][] = $entry;
			}
			foreach($s->all('-http://purl.org/void/provenance/ns/sourceDataset') as $pe2)
			{
				foreach($pe2->all('http://purl.org/void/provenance/ns/resultingDataset') as $dst)
				{
					if($dst->isType('http://example.org/error#ErrorDocument'))
					{
						foreach($dst->all('http://example.org/error#hasError') as $e)
						{
							$e = trim($e);
							@$errors[(string)$s][] = $e;
							if($e == 'No events found')
							{
								$errorstate = max($errorstate, 1);
							}
							else
							{
								$errorstate = max($errorstate, 2);
							}
						}
					}
				}
			}
			$errorstate = max($errorstate, getProvenance($s, $maps, $errors));
		}
	}
	@asort($maps[(string)$doc]);
	foreach($doc->all('-http://purl.org/void/provenance/ns/sourceDataset') as $pe)
	{
		foreach($pe->all('http://purl.org/void/provenance/ns/resultingDataset') as $dst)
		{
			if($dst->isType('http://example.org/error#ErrorDocument'))
			{
				foreach($dst->all('http://example.org/error#hasError') as $e)
				{
					$e = trim($e);
					if(!@in_array($e, $ignorederrors[(string)$doc]))
					{
						@$errors[(string)$doc][] = $e;
						if($e == 'No events found')
						{
							$errorstate = max($errorstate, 1);
						}
						else
						{
							$errorstate = max($errorstate, 2);
						}
					}
				}
			}
		}
	}
	return $errorstate;
}

function processProgramme($prog, &$progprovdata, &$allprogerrors, &$progstates)
{
	if( !$prog->has('-http://purl.org/void/provenance/ns/resultingDataset') )
	{
		return;
	}
	
	$progmaps = array();
	$progerrors = array();
	$progerrorstate = getProvenance($prog, $progmaps, $progerrors);
	$progstates[(string)$prog] = $progerrorstate;
	foreach($progerrors as $s => $errors)
	{
		foreach($errors as $error)
		{
			@$allprogerrors[$s][] = $error;
		}
	}
	$progprovdata[] = array((string)$prog, $progmaps, $progerrors, $progerrorstate);
}

function printFeeds($progstates)
{
	$feeds = array();

	global $diary_config;
	if (($handle = fopen($diary_config["path"]."/etc/feeds.csv", "r")) !== FALSE) {
		// Get column names from the first line of the CSV file.
		$c = array_flip(fgetcsv($handle, 1000, ","));
	
		// For each remaining line...
		while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
	
			// skip if we're running a specific config item (for testing) and this ain't it.
			if( @$argc == 2 && $data[0] != $argv[1] ) { continue; }
	
			// Generate the options array.
			$options = array();
			foreach($c as $key => $id)
			{
				$options[preg_replace('/[^A-Za-z]/', '', $key)] = @$data[$id];
			}
	
			// Process a single feed.
			$feeds[]=$options;
		}
		// Close the config file.
		fclose($handle);
	}

	print "<h1>Current Sources of Data for the Events Calendar</h1>\n";
	print "<table class='datagrid'>\n";

	global $diary_config;
	list($eventcount, $eventinstancecount, $eventfutureinstancecount) = getCounts();
	$i = 0;
	foreach( $feeds as $feed )
	{
		if( $feed["Script"] == "" ) { continue; }

		$uri = $diary_config["ns"]["localfeed"].preg_replace('/[^A-Za-z0-9]/', '', $feed['FeedID']);
		if( $i % 10 == 0 ) 
		{
			print "<tr>";
			print "<th>FeedID</th>";
			print "<th>Feed Name / URL / Group</th>";
			print "<th>Site</th>";
			print "<th>Type</th>";
			print "<th>Script</th>";
			print "<th>Extra</th>";
			print "<th>Counts</th>";
			print "</tr>";
		}
		print "<tr class='script_".$feed["Script"]." status_".@$progstates[$uri]."'>";
		print "<td class='feedID'><a href='feeds.html.php?prog=".urlencode($uri)."'>".$feed['FeedID']." <br /><small>(see details)</small></a></td>";
		print "<td>";
		print $feed['FeedName'];
		print "<br />";
		if(substr($feed['FeedURL'], 0, 4) == "http")
		{
			print "<a href='".$feed['FeedURL']."'>".$feed['FeedURL']."</a>";
		}
		else
		{
			print $feed['FeedURL']; 
		}
		print "<br />";
		if(substr($feed['FacultyUnitGroup'], 0, 4) == "http")
		{
			print "<a href='".$feed['FacultyUnitGroup']."'>".$feed['FacultyUnitGroup']."</a>";
		}
		else
		{
			print $feed['FacultyUnitGroup']; 
		}
		print "</td>"; 
		print "<td>".$feed['Site']."</td>"; 
		print "<td>".$feed['Type']."</td>"; 
		print "<td>".$feed['Script']."</td>"; 
		print "<td>";
		if(trim($feed['Notes']) != "")
		{
			echo "<img src='/img/silk/icons/note.png' alt='Note' title='Note' />".$feed['Notes']."<br />";
		}
		if(trim($feed['Tags']) != "")
		{
			echo "<img src='/img/silk/icons/tag_blue.png' alt='Tag' title='Tag' />".$feed['Tags']."<br />";
		}
		if(trim($feed['TimeLimit']) != "")
		{
			echo "<img src='/img/silk/icons/clock_red.png' alt='Time Limit' title='Time Limit' />".$feed['TimeLimit']."<br />";
		}
		print "</td>";
		print "<td>";
		print "<img src='/img/silk/icons/date.png' alt='Events' title='Events' />".str_pad(@$eventcount[$uri], 1, 0);
		print "<br />";
		print "<img src='/img/silk/icons/date_magnify.png' alt='Event Instances' title='Events Instances' />".str_pad(@$eventinstancecount[$uri], 1, 0);
		print "<br />";
		print "<img src='/img/silk/icons/date_next.png' alt='Future Event Instances' title='Future Event Instances' />".str_pad(@$eventfutureinstancecount[$uri], 1, 0);
		print "</td>";
		print "</tr>";
		++$i;
	}
	print " </table>";
}

function getCounts()
{
	$graph = getGraph();
        foreach($graph->allOfType("event:Event") as $event)
        {
		foreach($event->all("-http://purl.org/prog/has_event") as $feed)
		{
			@$eventcount[(string)$feed]++;
		}
                if($event->has("event:time"))
                {
                        foreach($event->all("event:time") as $time)
                        {
				foreach($event->all("-http://purl.org/prog/has_event") as $feed)
				{
					@$eventinstancecount[(string)$feed]++;
                                	if($time->has("tl:at"))
                                	{
						//echo $time->getString("tl:at");
						if($time->getString("tl:at") > date('Y-m-d'))
						{
							@$eventfutureinstancecount[(string)$feed]++;
						}
                                	}
                                	elseif($time->has("tl:start"))
                                	{
						//echo $time->getString("tl:start");
						if(substr($time->getString("tl:start"), 0, 10) > date('Y-m-d'))
						{
							@$eventfutureinstancecount[(string)$feed]++;
						}
                                	}
				}
                        }
                }
        }
	return array($eventcount, $eventinstancecount, $eventfutureinstancecount);
}

function getGraph()
{
	global $graph;
	$graph->ns( "event", "http://purl.org/NET/c4dm/event.owl#" );
	$graph->ns( "tl", "http://purl.org/NET/c4dm/timeline.owl#" );
	return $graph;
}
