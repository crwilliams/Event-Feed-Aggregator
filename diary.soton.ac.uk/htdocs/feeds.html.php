<?php
# Copyright (c) 2012-2013 Colin Williams / University of Southampton
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

require('../../etc/config.php');

$feeds = array();

if (($handle = fopen($diary_config["path"]."/etc/config.csv", "r")) !== FALSE) {
	// Get column names from the first line of the CSV file.
	$c = array_flip(fgetcsv($handle, 1000, ","));

	// For each remaining line...
	while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

		// skip if we're running a specific config item (for testing) and this ain't it.
		if( $argc == 2 && $data[0] != $argv[1] ) { continue; }

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
?>
<h1>Current Sources of Data for the Events Calendar</h1>
<table class='datagrid'>
<?php
list($eventcount, $eventinstancecount, $eventfutureinstancecount) = getCounts();
$i = 0;
foreach( $feeds as $feed )
{
	$uri = 'http://id.southampton.ac.uk/diary/'.preg_replace('/[^A-Za-z0-9]/', '', $feed['FeedID']);
	if( $feed["Script"] == "" ) { continue; }
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
	print "<tr class='script_".$feed["Script"]." status_".$progstates[$uri]."'>";
	print "<td class='feedID'><a href='/prov.html.php?prog=".urlencode($uri)."'>".$feed['FeedID']." <br /><small>(see details)</small></a></td>";
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
?>
</table>
<?php

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
?>
