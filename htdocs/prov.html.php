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
include '/var/wwwsites/tools/arc2/ARC2.php';
include '/var/wwwsites/tools/Graphite/Graphite.php';
$graph = Graphite::thaw('../../var/data.php');

$progprovdata = array();
$allprogerrors = array();
$progstates = array();

function processProgramme($prog, &$progprovdata, &$allprogerrors, &$progstates)
{
	if($prog->has('-http://purl.org/void/provenance/ns/resultingDataset'))
	{
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
}

$render = false;
if(!isset($_GET['prog']))
{
	foreach($graph->allOfType('http://purl.org/prog/Programme') as $prog)
	{
		processProgramme($prog, $progprovdata, $allprogerrors, $progstates);
	}
}
else
{
	$render = true;
	processProgramme($graph->resource(urldecode($_GET['prog'])), $progprovdata, $allprogerrors, $progstates);
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
	$progerrorclass = 'noerror';
	switch($progerrorstate)
	{
		case 2:
			$progerrorclass = 'error';
			break;
		case 1:
			$progerrorclass = 'warning';
			break;
	}
	if($render)
	{
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
if(!$render)
{
	if(PHP_SAPI === 'cli')
	{
		include './feeds.html.php';
	}
	else
	{
		echo "<a href='/feeds.html'>List of Feeds</a>";
	}
}

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
?>
</body>
</html>
