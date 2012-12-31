<?php
# Copyright (c) 2012 Colin Williams / University of Southampton
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
require('dev/inc/functions.php');

$fields = array(
'FeedID',
'Site',
'FeedName',
'FeedURL',
'FacultyUnitGroup',
'Type',
'Notes',
'Script',
'Tags',
'TimeLimit');

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
<!DOCTYPE html>
<html>
<style>
.datagrid {
	border-collapse: collapse;
}
.datagrid th { 
	padding-top: 0.5em;
}
.datagrid td { 
	padding: 5px;
	border: solid 1px #ccc;
	background-color: #efe;
}
tr.script_ td {
	text-decoration: line-through;
	background-color: #fee;
}
</style>
<h1>Current Sources of Data for the Events Calendar</h1>
<table class='datagrid'>
<?php
list($eventcount, $eventinstancecount, $eventfutureinstancecount) = getCounts();
$i = 0;
foreach( $feeds as $feed )
{
	if( $feed["Script"] == "" ) { continue; }
	if( $i % 10 == 0 ) 
	{
		print "<tr>";
		foreach( $fields as $field_name ) { print "<th>$field_name</th>"; }
		print "<th>Events</th>";
		print "<th>Event Instances</th>";
		print "<th>Future Event Instances</th>";
		print "</tr>";
	}
	print "<tr class='script_".$feed["Script"]."'>";
	foreach( $fields as $field_name ) { 
		if( ($field_name=="FeedURL" || $field_name == "FacultyUnitGroup") && substr($feed[$field_name], 0, 4) == "http" )
		{
			print "<td><a href='".$feed[$field_name]."'>".$feed[$field_name]."</a></td>"; 
		}
		else
		{
			print "<td>".$feed[$field_name]."</td>"; 
		}
	}
	print "<td>".$eventcount['http://id.southampton.ac.uk/diary/'.preg_replace('/[^A-Za-z0-9]/', '', $feed['FeedID'])]."</td>";
	print "<td>".$eventinstancecount['http://id.southampton.ac.uk/diary/'.preg_replace('/[^A-Za-z0-9]/', '', $feed['FeedID'])]."</td>";
	print "<td>".$eventfutureinstancecount['http://id.southampton.ac.uk/diary/'.preg_replace('/[^A-Za-z0-9]/', '', $feed['FeedID'])]."</td>";
	print "</tr>";
	++$i;
}
print "</table>";
print "</html>";

function getCounts()
{
	$graph = getGraph();
        foreach($graph->allOfType("event:Event") as $event)
        {
		foreach($event->all("-http://purl.org/prog/has_event") as $feed)
		{
			$eventcount[(string)$feed]++;
		}
                if($event->has("event:time"))
                {
                        foreach($event->all("event:time") as $time)
                        {
				foreach($event->all("-http://purl.org/prog/has_event") as $feed)
				{
					$eventinstancecount[(string)$feed]++;
                                	if($time->has("tl:at"))
                                	{
						//echo $time->getString("tl:at");
						if($time->getString("tl:at") > date('Y-m-d'))
						{
							$eventfutureinstancecount[(string)$feed]++;
						}
                                	}
                                	elseif($time->has("tl:start"))
                                	{
						//echo $time->getString("tl:start");
						if(substr($time->getString("tl:start"), 0, 10) > date('Y-m-d'))
						{
							$eventfutureinstancecount[(string)$feed]++;
						}
                                	}
				}
                        }
                }
        }
	return array($eventcount, $eventinstancecount, $eventfutureinstancecount);
}

