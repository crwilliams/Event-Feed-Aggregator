#!/usr/bin/php
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

global $diary_config;
$diary_config["path"] = dirname(dirname(__FILE__));
require($diary_config["path"].'/etc/config.php');
require($diary_config["path"].'/lib/xml.php');
require($diary_config["path"].'/lib/utils.php');


$procstarttime = microtime(true);

$errors = array();
set_error_handler('log_error');

// Disable reporting of deprecated features.
error_reporting(E_ALL ^ E_DEPRECATED);

declare(ticks = 1);

// Setup alarm function that is called when SIGALRM is raised.
pcntl_signal(SIGALRM, "alarm", false);

// Setup namespace prefixes.
$ns['event']	= 'http://purl.org/NET/c4dm/event.owl#';
$ns['rdfs']	= 'http://www.w3.org/2000/01/rdf-schema#';
$ns['rdf']	= 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
$ns['tl']	= 'http://purl.org/NET/c4dm/timeline.owl#';
$ns['geo']	= 'http://www.w3.org/2003/01/geo/wgs84_pos#';
$ns['foaf']	= 'http://xmlns.com/foaf/0.1/';
$ns['dcterms']	= 'http://purl.org/dc/terms/';
$ns['diary']	= 'http://id.southampton.ac.uk/ns/diary/';
$ns['deri']	= 'http://vocab.deri.ie/rooms#';
$ns['prog']	= 'http://purl.org/prog/';
$ns['prov']	= 'http://purl.org/void/provenance/ns/';
$ns['to']	= 'http://www.w3.org/2006/time#';
$ns['type']	= 'http://example.org/type#';
$ns['error']	= 'http://example.org/error#';

$feeds = 0;
// Try opening the config file.
if (($handle = fopen($diary_config["path"]."/etc/config.csv", "r")) !== FALSE) {
	// Get column names from the first line of the CSV file.
	$c = array_flip(fgetcsv($handle, 1000, ","));

	// For each remaining line...
	while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

		// skip if we're running a specific config item (for testing) and this ain't it.
		if( $argc >= 2 && $data[0] != $argv[1] ) { continue; }
		// skip if we're running normally but the config item name begins with an underscore.
		if( $argc == 1 && $data[0][0] == '_' ) { continue; }

		// Generate the options array.
		$options = array();
		foreach($c as $key => $id)
		{
			if($key != 'Script')
			{
				$options[preg_replace('/[^A-Za-z]/', '', $key)] = @$data[$id];
			}
		}

		++$feeds;
		// Process a single feed.
		processFeed($data[$c['Script']], $options);
	}
	// Close the config file.
	fclose($handle);
}
if( $feeds == 0 ) { print "No feeds processed\n"; }
exit;

/**
 * Process a single feed.
 * 
 * @param	string	$script		The name of the script to use to process the feed.
 * @param	array	$options	The options that should be passed to the script.
 */
function processFeed($script, $options)
{
	$id = $options['FeedID'];
	printInfo();

	// Check that the script parameter is set.
	if($script == "")
	{
		printInfo("Unable to process $id.  No script set.");
		return;
	}
	printInfo("Processing $id");
	printInfo("Script: $script");

	if($options['TimeLimit'] != "") {
		$limit = $options['TimeLimit'];
	} else {
		$limit = 30;
	}

	if(preg_match('/^[A-Za-z0-9]+$/', $script))
	{
		// Fork the process into parent and child.
		$pid = pcntl_fork();
		if ($pid == -1) {
			printInfo("Could not fork", true);
			die();
		} else if ($pid) {
			// This is the parent process.
			runParent($pid, $limit, $options);
		} else {
			// This is the child process.
			$code = runChild($script, $options);
			// Exit, to ensure that this child goes no further
			exit($code);
		}
	}
}

/**
 * Run the parent process.
 *
 * @param	int	$pid		The process ID of the child process.
 * @param	int	$limit		The time limit (in seconds) that the child process should be allowed to run for.
 * @param	array	$options	The options that were passed to the child process.
 */
function runParent($pid, $limit = 30, $options) {
	global $gpid;
	global $gfeedid;
	// Store the PID of the child.
	$gpid = $pid;
	// Store the FeedID of the child.
	$gfeedid = $options['FeedID'];
	// set an alarm, to limit the execution time.
	pcntl_alarm($limit);
	// Wait for the child to return.
	$cid = pcntl_waitpid($pid, $status);
	// Check whether the child returned correctly.
	if($cid > 0) {
		if(pcntl_wifexited($status) && pcntl_wexitstatus($status) > 0)
		{
			printInfo("Script for $gfeedid exited with status ".pcntl_wexitstatus($status), true);
		}
		if(pcntl_wifsignaled($status))
		{
			printInfo("Script for $gfeedid signalled with signal ".pcntl_wtermsig($status), true);
		}
		if(pcntl_wifstopped($status))
		{
			printInfo("Script for $gfeedid stopped with signal ".pcntl_wstopsig($status), true);
		}
	}
}

/**
 * Execute the command, capturing stdout and stderr.
 *
 * @param	string	$cmd		The command to execute.
 * @param	array	$output		The array to capture the lines of stdout in.
 * @param	array	$error		The array to capture the lines of stderr in.
 */
function myexec($cmd, &$output, &$error)
{
	$descriptorspec = array(
		1 => array("pipe", "w"),
		2 => array("pipe", "w"),
	);

	$process = proc_open($cmd, $descriptorspec, $pipes);

	if (is_resource($process)) {
		$output = stream_get_contents($pipes[1]);
		fclose($pipes[1]);

		$error = stream_get_contents($pipes[2]);
		fclose($pipes[2]);

		$return_value = proc_close($process);
	}
	else
	{
		$output = "";
		$error = "Failed to call ".$cmd;
	}
	if($output == "")
	{
		$output = array();
	}
	else
	{
		$output = explode("\n", $output);
	}
	if($error == "")
	{
		$error = array();
	}
	else
	{
		$error = explode("\n", $error);
	}
}

/**
 * Run the child process.
 *
 * @param	string	$script		The script to run.
 * @param	array	$options	The options to pass to the script.
 */
function runChild($script, $options) {
	global $fh;
	global $diary_config;

	// Convert the options array into XML form.
	$optionsxml = generate_xml_from_array($options, 'options');
	$optionsxml = '<options>'.$optionsxml.'</options>';

	// Generate the command, with the options XML as a command line argument.
	$cmd = $diary_config["path"].'/bin/scripts/'.$script.' -o '.escapeshellarg($optionsxml);
	printInfo($cmd);

	// Execute the command.
	myexec($cmd, $output, $error);

	if(count($error) > 0)
	{
		trigger_error("Errors encountered whilst processing ".$options['FeedID'].": ".implode("\n", $error));
	}

	// Convert the response XML into an object.
	$xml = simplexml_load_string(implode("\n", $output));

	// Check that the response is valid.
	if($xml == null)
	{
		trigger_error("Bad response from ".$options['FeedID']." script: ".implode("", $output));
		return 1;
	}

	// Check the number of events.
	$eventcount = count($xml->item);
	if($eventcount == 0)
	{
		printInfo("No events found while processing ".$options['FeedID']);
		trigger_error("No events found");
	}
	else if($eventcount == 1)
	{
		printInfo("1 event found while processing ".$options['FeedID']);
	}
	else
	{
		printInfo("$eventcount events found while processing ".$options['FeedID']);
	}

	$safeid = preg_replace('/[^A-Za-z0-9]/', '', $options['FeedID']);
	// Open a file to write the output (as RDF triples) to.
	$fh = fopen($diary_config["path"].'/var/output/'.$safeid, 'w');

	tripleA('http://id.southampton.ac.uk/diary/', ns('prog', 'Programme'));
		
	$feeduri = 'http://id.southampton.ac.uk/diary/'.$safeid;
	tripleA($feeduri, ns('prog', 'Programme'));

	if($options['FeedName'] != '')
	{
		tripleL($feeduri, ns('rdfs', 'label'), $options['FeedName']);
	}

	// If the XML has events...
	if($xml->item != null)
	{
		foreach($xml->item as $event)
		{
			// Process the event.
			processEvent($event, $options['FeedID']);
			if(isset($event->uri))
			{
				$eventuri = 'http://id.southampton.ac.uk/event/'.md5($event->uri);
			}
			else
			{
				$eventuri = 'http://id.southampton.ac.uk/event/'.md5($event->link);
			}
			tripleU($feeduri, ns('prog', 'has_event'), $eventuri);
			tripleU('http://id.southampton.ac.uk/diary/', ns('prog', 'has_event'), $eventuri);

			$eventerrors = array();
			if(isset($event->errors))
			{
				foreach($event->errors->item as $eventerror)
				{
					$eventerrors[] = (string)$eventerror;
				}
			}
	
			// Determine source datasets.
			if(!is_null($event->sourceDocuments) && !is_null($event->sourceDocuments->item)) {
				foreach($event->sourceDocuments->item as $sd) {
					$sda = array('type' => null, 'src' => null, 'dst' => null, 'start' => null, 'end' => null);
					if(isset($sd->type)) {
						$sda['type'] = (string)$sd->type;
					}
					if(isset($sd->src)) {
						$sda['src'] = (string)$sd->src;
					}
					if(isset($sd->dst)) {
						$sda['dst'] = (string)$sd->dst;
					} else {
						$sda['dst'] = $eventuri;
					}
					if(isset($sd->start)) {
						$sda['start'] = (float)$sd->start;
					}
					if(isset($sd->end)) {
						$sda['end'] = (float)$sd->end;
					}
					// Output provenance information.
					provenanceEvent($sda['dst'], $sda['src'], $sda['start'], $sda['end'], $sda['type']);
					if(count($eventerrors) > 0 && $sda['dst'] == $eventuri)
					{
						provenanceEvent($eventuri.'#errors', $eventuri, $sda['start'], $sda['end'], $sda['type']);
					}
				}
			}
			if(count($eventerrors) > 0)
			{
				tripleA($eventuri.'#errors', ns('error', 'ErrorDocument'));
				foreach($eventerrors as $error)
				{
					tripleL($eventuri.'#errors', ns('error', 'hasError'), (string)$error);
				}
			}
		}
	}
	
	global $procstarttime;
	global $errors;
	foreach($xml->errors->item as $error)
	{
		$errors[] = (string)$error;
	}

	foreach($xml->sourceDocuments->item as $sd) {
		$sda = array('type' => null, 'src' => null, 'dst' => null, 'start' => null, 'end' => null);
		if(isset($sd->type)) {
			$sda['type'] = (string)$sd->type;
		}
		if(isset($sd->src)) {
			$sda['src'] = (string)$sd->src;
		}
		if(isset($sd->dst)) {
			$sda['dst'] = (string)$sd->dst;
		} else {
			$sda['dst'] = $feeduri;
		}
		if(isset($sd->start)) {
			$sda['start'] = (float)$sd->start;
		}
		if(isset($sd->end)) {
			$sda['end'] = (float)$sd->end;
		}
		// Output provenance information.
		provenanceEvent($sda['dst'], $sda['src'], $sda['start'], $sda['end'], $sda['type']);
		if(count($errors) > 0 && $sda['dst'] == $feeduri)
		{
			provenanceEvent($feeduri.'#errors', $feeduri, $sda['start'], $sda['end'], $sda['type']);
		}
	}
	
	if(count($errors) > 0)
	{
		tripleA($feeduri.'#errors', ns('error', 'ErrorDocument'));
		foreach($errors as $error)
		{
			tripleL($feeduri.'#errors', ns('error', 'hasError'), (string)$error);
print $error."\n";
			logErrorToFile( (string)$error );
		}
	}

	// Close the file.
	fclose($fh);

	return 0;
}

/**
 * Output the provenance information relating to an entity.
 *
 * @param	string		$uri		The URI of the entity that the provenance information relates to.
 * @param	array		$sources	The set of data sources that were used to generate the entity.
 * @param	DateTime	$startTime	The start time of the process.
 * @param	DateTime	$endTime	The end time of the process.
 */
function provenanceEvent($uri, $source, $startTime = null, $endTime = null, $type = null) {
	$provuri = (string)$uri.'#provenance-'.md5($uri."|".$source."|".$startTime."|".$endTime."|".$type);
	if(is_null($type)) {
		$type = (string)$uri.'#process';
	} else {
		$type = ns('type', (string)$type);
	}
	
	tripleA($provuri, ns('prov', 'ProvenanceEvent'));

	tripleU($provuri, ns('prov', 'processType'), (string)$type);

	tripleU($provuri, ns('prov', 'sourceDataset'), (string)$source);
	
	tripleU($provuri, ns('prov', 'resultingDataset'), (string)$uri);
	
	if(!is_null($startTime)) {
		tripleL($provuri, ns('to', 'hasBeginning'), DateTime::createFromFormat('U u', floor($startTime)." ".str_pad((1000000*$startTime)%1000000, 6, "0", STR_PAD_LEFT))->format('Y-m-d\TH:i:s.u'), "http://www.w3.org/2001/XMLSchema#dateTime");
	}
	
	if(!is_null($endTime)) {
		tripleL($provuri, ns('to', 'hasEnd'), DateTime::createFromFormat('U u', floor($endTime)." ".str_pad((1000000*$endTime)%1000000, 6, "0", STR_PAD_LEFT))->format('Y-m-d\TH:i:s.u'), "http://www.w3.org/2001/XMLSchema#dateTime");
	}
}

/**
 * Print an information message (possibly only if in verbose mode).
 *
 * @param	string	$string	The string to print.
 * @param	bool	$force	True if the string should be printed even when not in verbose mode.
 */
function printInfo($string="", $force=false) {
	global $diary_config;
	if( $force || @$diary_config["verbose"] ) {
		echo $string."\n";
	}
}

/**
 * Alarm function.
 *
 * @param	int	$signo	The signal that caused the alarm to be raised.
 */
function alarm($signo) {
	global $gpid;
	global $gfeedid;
	if($gpid > 0)
	{
		printInfo("Going to terminate script for $gfeedid", true);
		if(posix_kill($gpid, SIGKILL))
		{
			printInfo("Script for $gfeedid killed successfully", true);
		}
		else
		{
			printInfo("Failed to kill script for $gfeedid", true);
			die();
		}
	}
}

/**
 * Write a line to the output file.
 *
 * @param	string	$string	The string to write to the file.
 */
function writeToFile($string) {
	global $fh;
	fwrite($fh, $string."\n");
}

/**
 * Output an RDF triple with a URI object.
 *
 * @param	string	$subject	The URI of the subject.
 * @param	string	$predicate	The URI of the predicate.
 * @param	string	$object		The URI of the object.
 */
function tripleU($subject, $predicate, $object) {
	writeToFile("<$subject> <$predicate> <$object> .");
}

/**
 * Output an RDF triple with a literal object.
 *
 * @param	string	$subject	The URI of the subject.
 * @param	string	$predicate	The URI of the predicate.
 * @param	string	$object		The literal value of the object.
 */
function tripleL($subject, $predicate, $object, $object_type = null ) {
	if( isset( $object_type ) ) { 
		writeToFile("<$subject> <$predicate> \"".str_replace(array("\n", "\"", "\\"), array('\n', '\"', '\\'), trim($object, " "))."\"^^<$object_type> .");
	} else {
		writeToFile("<$subject> <$predicate> \"".str_replace(array("\n", "\"", "\\"), array('\n', '\"', '\\'), trim($object, " "))."\" .");
	}
}

/**
 * Output an RDF triple defining the type of an entity.
 *
 * @param	string	$subject	The URI of the subject.
 * @param	string	$type		The type of the subject.
 */
function tripleA($subject, $type) {
	tripleU($subject, ns("rdf", "type"), $type);
}

/**
 * Expand a URI given using a prefix.
 *
 * @param	string	$prefix		The prefix identifier.
 * @param	string	$e		The entity name.
 */
function ns($prefix, $e) {
	global $ns;
	if(isset($ns[$prefix])) {
		return $ns[$prefix].$e;
	} else {
		return $prefix.":".$e;
	}
}

/**
 * Process a single event, outputting RDF triples to the relevant file.
 * 
 * @param	object	$event	The event to process.
 * @param	string	$feedid	The ID of the feed.
 */
function processEvent($event, $feedid)
{
	$provInfo = array();
	$external_uri = "";
	if( isset($event->uri) )
	{
		$external_uri = $event->uri;
	}
	else
	{
		$external_uri = $event->link;
	}


	# then we  hash the external URI to make a local one...

	if(trim($external_uri) != "" )
	{
		$uri = 'http://id.southampton.ac.uk/event/'.md5($external_uri);
	}
	else
	{
		printInfo("Event with name ".str_replace("\n", "", $event->title)." has no link or uri.");
		trigger_error("Event with name ".str_replace("\n", "", $event->title)." has no link or uri.");
		return;
	}

	// Process basic info.
	tripleA($uri, ns("event", "Event"));
	tripleL($uri, ns("rdfs", "label"), str_replace("\n", "", $event->title));
	if(trim($event->desc) != "")
	{
		tripleL($uri, ns("dcterms", "description"), $event->desc, "http://purl.org/xtypes/Fragment-PlainText");
	}
	if(trim($event->htmldesc) != "")
	{
		tripleL($uri, ns("dcterms", "description"), $event->htmldesc, "http://purl.org/xtypes/Fragment-XHTML");
	}
	if(trim($event->link) != "" )
	{
		tripleU($uri, ns("foaf", "homepage"), $event->link);
	}

	// Process event type.
	if(isset($event->type) && $event->type != "")
	{
		tripleU($uri, ns("diary", "event-type"), ns("diary", $event->type));
	}

	// Process host.
	if(isset($event->host) && $event->host != "")
	{
		tripleU($uri, ns("event", "agent"), $event->host);
	}

	// Process tags.
	if(isset($event->tags))
	{
		foreach($event->tags->event as $tag)
		{
			tripleU($uri, ns("diary", "tag"), ns("diary", $tag));
		}
	}

	// Process dates.
	$i = 0;
	foreach($event->date->item as $d)
	{
		$i++;
		// The event has a 'from' datetime.
		if(isset($d->from))
		{
			tripleU($uri, ns("event", "time"), "$uri#time-$i");
			tripleA("$uri#time-$i", ns("tl", "Interval"));
			tripleL("$uri#time-$i", ns("tl", "start"), $d->from, "http://www.w3.org/2001/XMLSchema#dateTime" );
			// The event also has a 'to' datetime.
			if(isset($d->to))
			{
				tripleL("$uri#time-$i", ns("tl", "end"), $d->to, "http://www.w3.org/2001/XMLSchema#dateTime");
			}
		}
		// The event has a simple 'date' (with no time).
		elseif(isset($d->date))
		{
			tripleU($uri, ns("event", "time"), "$uri#time-$i");
			tripleA("$uri#time-$i", ns("tl", "Instant"));
			tripleL("$uri#time-$i", ns("tl", "at"), substr($d->date, 0, 10), "http://www.w3.org/2001/XMLSchema#date");
		}
	}

	// Process venue.
	if(isset($event->venuelink))
	{
		tripleU($uri, ns("event", "place"), $event->venuelink);
		$roomprefix = "http://id.southampton.ac.uk/room/";
		if(substr($event->venuelink, 0, strlen($roomprefix)) == $roomprefix)
		{
			$roomid = substr($event->venuelink, strlen($roomprefix));
			$roomid = explode('-', $roomid);
			tripleA($event->venuelink, ns("deri", "Room"));
			tripleL($event->venuelink, ns("rdfs", "label"), implode(" / ", $roomid));
			tripleU($event->venuelink, "http://data.ordnancesurvey.co.uk/ontology/spatialrelations/within", "http://id.southampton.ac.uk/building/".$roomid[0]);
		}
	}

	// TODO: Currently, venues with a defined URI (which may be at the campus level) also have a separate
	//       'SpatialThing' entity created.  This is in order to make the full venue details available.
	//	 If this behaviour is undesired, change this 'if' to an 'else if'.
	if(isset($event->venue))
	{
		tripleU($uri, ns("event", "place"), "$uri#place");
		tripleA("$uri#place", ns("geo", "SpatialThing"));
		tripleL("$uri#place", ns("rdfs", "label"), $event->venue);
	}

	// Process speaker.
	if(isset($event->speaker))
	{
		tripleU($uri, ns("event", "agent"), "$uri#speaker");
		tripleA("$uri#speaker", ns("foaf", "Person"));
		tripleL("$uri#speaker", ns("foaf", "name"), $event->speaker);
		if(isset($event->speakerlink))
		{
			tripleU("$uri#speaker", ns("foaf", "homepage"), $event->speakerlink);
		}
	}

	return $provInfo;
}

?>
