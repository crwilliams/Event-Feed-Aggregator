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
require($diary_config["path"].'/lib/utils.php');
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

div.proc<?= md5(ns('type', 'cache')) ?> {
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
	foreach($graph->allOfType(ns('prog', 'Programme')) as $prog)
	{
		processProgramme($prog, $progprovdata, $allprogerrors, $progstates);
	}
}

foreach($progprovdata as $d)
{
	list($prog, $progmaps, $progerrors, $progerrorstate) = $d;
	$prog = $graph->resource($prog);
	$progeventprovdata = array();
	foreach($prog->all(ns('prog', 'has_event')) as $e)
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
<p>Icons licensed as CC-by 2.5 from <a href='http://www.famfamfam.com/lab/icons/silk/'>http://www.famfamfam.com/lab/icons/silk/</a></p>
</body>
</html>
<?

exit;############################################################ 

function renderProcess($p)
{
	$date = formatDate($p[0], $p[1]);
	$str = getProcessString($p[3], $date);
	if(substr($p[2], 0, 6) != 'cache:')
	{
		$str .= "<a class='document' href='".$p[2]."'>".$p[2]."</a>";
	}
	return $str;
}

function formatDate($start, $end)
{
	$start = substr($start, 0, -7);
	$end = substr($end, 0, -7);
	if($start == $end)
	{
		return "at ".$start;
	}
	elseif($start.$end == $start)
	{
		return "after ".$start;
	}
	elseif($start.$end == $end)
	{
		return "by ".$end;
	}
	else
	{
		return "between ".$start." and ".$end;
	}
}

function getProcessString($pred, $date)
{
	switch($pred)
	{
		case ns('type', 'getRSS'):
			$proc = "Get RSS ".$date;
			return "<img src='/img/silk/icons/rss.png' alt='$proc' title='$proc'/>";
		case ns('type', 'getRDF'):
			$proc = "Get RDF ".$date;
			return "<img src='/img/silk/icons/database.png' alt='$proc' title='$proc'/>";
		case ns('type', 'getHTML'):
			$proc = "Get HTML ".$date;
			return "<img src='/img/silk/icons/html.png' alt='$proc' title='$proc' />";
		case ns('type', 'getFromSharePoint'):
			$proc = "Get from SharePoint ".$date;
			return "<img src='/img/silk/icons/world.png' alt='$proc' title='$proc' />";
		case ns('type', 'cache'):
			$proc = "From document cached ".$date;
			return "<img src='/img/silk/icons/disk_multiple.png' alt='$proc' title='$proc' />";
		default:
			return "<span class='process'>".$pred." ".$date."</span>";
	}
}

function renderProvenance($prog, $maps, $errors, $prefix)
{
	if(isset($errors[(string)$prog]))
	{
		echo $prefix."\t<ul class='issues'>\n";
		foreach($errors[(string)$prog] as $e)
		{
			$str = $e['message']." File: ".$e['file']." Line: ".$e['line'];
			switch($e['level'])
			{
				case 2:
					echo $prefix."\t\t<li class='error'>".$str."</li>\n";
					break;
				case 1:
					echo $prefix."\t\t<li class='warning'>".$str."</li>\n";
					break;
				case 0:
				default:
					echo $prefix."\t\t<li class='notice'>".$str."</li>\n";
					break;
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
	foreach($doc->all('-'.ns('prov', 'resultingDataset')) as $pe)
	{
		$src = $pe->all(ns('prov', 'sourceDataset'));
		$process = $pe->get(ns('prov', 'processType'));
		$start = str_replace(array("[NULL]", "T"), array("", " "), $pe->get(ns('to', 'hasBeginning')));
		$end = str_replace(array("[NULL]", "T"), array("", " "), $pe->get(ns('to', 'hasEnd')));
		foreach($src as $s)
		{
			$entry = array((string)$start, (string)$end, (string)$s, (string)$process);
			if(!@in_array($entry, $maps[(string)$doc]))
			{
				@$maps[(string)$doc][] = $entry;
			}
			processErrors($s, $errors, $errorstate);
			$errorstate = max($errorstate, getProvenance($s, $maps, $errors));
		}
	}
	@asort($maps[(string)$doc]);
	processErrors($doc, $errors, $errorstate);
	return $errorstate;
}

function processErrors($source, &$errors, &$errorstate)
{
	foreach($source->all('-'.ns('prov', 'sourceDataset')) as $provenanceEvent)
	{
		foreach($provenanceEvent->all(ns('prov', 'resultingDataset')) as $dest)
		{
			if($dest->isType(ns('error', 'IssueDocument')))
			{
				foreach($dest->all(ns('error', 'hasIssueLine')) as $line)
				{
					$file = $line->get(ns('error', 'hasFilename'));
					$linenumber = $line->get(ns('error', 'hasLineNumber'));
					foreach(array('hasError' => 2, 'hasWarning' => 1, 'hasNotice' => 0) as $pred => $state)
					{
						foreach($line->all(ns('error', $pred)) as $issue)
						{
							$issue = trim($issue);
							@$errors[(string)$source][] = array('level' => $state, 'message' => $issue, 'file' => $file, 'line' => $linenumber);
							$errorstate = max($errorstate, $state);
						}
					}
				}
			}
		}
	}
}

function processProgramme($prog, &$progprovdata, &$allprogerrors, &$progstates)
{
	if( !$prog->has('-'.ns('prov', 'resultingDataset')) )
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
	asort($feeds);
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
		print "<td class='feedID'>";
		print "<a href='feeds.html.php?prog=".urlencode($uri)."'>".$feed['FeedID']." <br /><small>(see details)</small></a>";
		print "</td>";
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
		print "<img src='/img/silk/icons/date_magnify.png' alt='Event Instances' title='Events Instances' />";
		print str_pad(@$eventinstancecount[$uri], 1, 0);
		print "<br />";
		print "<img src='/img/silk/icons/date_next.png' alt='Future Event Instances' title='Future Event Instances' />";
		print str_pad(@$eventfutureinstancecount[$uri], 1, 0);
		print "</td>";
		print "</tr>";
		++$i;
	}
	print " </table>";
}

function getCounts()
{
	global $graph;
	foreach($graph->allOfType(ns('event', 'Event')) as $event)
	{
		foreach($event->all('-'.ns('prog', 'has_event')) as $feed)
		{
			@$eventcount[(string)$feed]++;
		}
		if($event->has(ns('event', 'time')))
		{
			foreach($event->all(ns('event', 'time')) as $time)
			{
				foreach($event->all('-'.ns('prog', 'has_event')) as $feed)
				{
					@$eventinstancecount[(string)$feed]++;
					if($time->has(ns('tl', 'at')))
					{
						if($time->getString(ns('tl', 'at')) > date('Y-m-d'))
						{
							@$eventfutureinstancecount[(string)$feed]++;
						}
					}
					elseif($time->has(ns('tl', 'start')))
					{
						if(substr($time->getString(ns('tl', 'start')), 0, 10) > date('Y-m-d'))
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
