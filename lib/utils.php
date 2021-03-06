<?
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

date_default_timezone_set('Europe/London');
require_once $diary_config["path"].'/lib/xml.php';
require_once $diary_config["path"].'/lib/simple_html_dom.php';

// Setup namespace prefixes.
$ns = $diary_config["ns"];

$ns['event']	= 'http://purl.org/NET/c4dm/event.owl#';
$ns['rdfs']		= 'http://www.w3.org/2000/01/rdf-schema#';
$ns['rdf']		= 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
$ns['tl']		= 'http://purl.org/NET/c4dm/timeline.owl#';
$ns['geo']		= 'http://www.w3.org/2003/01/geo/wgs84_pos#';
$ns['foaf']		= 'http://xmlns.com/foaf/0.1/';
$ns['dcterms']	= 'http://purl.org/dc/terms/';
$ns['deri']		= 'http://vocab.deri.ie/rooms#';
$ns['prog']		= 'http://purl.org/prog/';
$ns['prov']		= 'http://purl.org/void/provenance/ns/';
$ns['to']		= 'http://www.w3.org/2006/time#';
$ns['type']		= 'http://example.org/type#';
$ns['error']	= 'http://example.org/error#';

/**
 * Set a fixed error level.
 *
 * @param	int	$errno
 */
function set_error_level($errno = null)
{
	global $fixed_errno;
	$fixed_errno = $errno;
}

/**
 * Log an error.
 *
 * @param	int		$errno
 * @param	string	$errstr
 * @param	string	$errfile
 * @param	int		$errline
 * @param	array	$errcontext
 */
function log_error($errno, $errstr, $errfile, $errline, $errcontext = null) {
	global $issues;
	global $fixed_errno;
	if(!is_null($fixed_errno))
	{
		$errno = $fixed_errno;
	}
	$issues[] = array(
		'level' => $errno,
		'message' => $errstr,
		'file' => $errfile,
		'line' => $errline,
	);
}

/**
 * Log an error to file.
 *
 * @param	string	$error	The error string.
 * @param	string	$feedid	The ID of the feed that the error relates to.
 */
function logErrorToFile($error, $feedid=null)
{
	if($feedid == null)
	{
		global $options;
		$feedid = $options->FeedID;
	}
	global $diary_config;
	$file = fopen($diary_config["path"].'/var/log/'.$feedid.'.log', 'a');
	fputs($file, date('c')."\t".$error."\n");
	fclose($file);
}

/**
 * Retrigger errors.
 *
 * @param	array	$errors		The errors to retrigger.
 * @param	string	$filename	The filename of the script which generated these errors.
 * @param	int		$linenumber	The line number within the script which generated these errors.
 */
function retriggerErrors($errors, $filename, $linenumber)
{
	foreach($errors as $error)
	{
		$return = "";

		switch ($error->level) {
			case LIBXML_ERR_WARNING:
				$return .= "Warning $error->code: ";
				break;
			case LIBXML_ERR_ERROR:
				$return .= "Error $error->code: ";
				break;
			case LIBXML_ERR_FATAL:
				$return .= "Fatal Error $error->code: ";
				break;
		}

		$return .= trim($error->message) . " Line: $error->line, Column: $error->column";

		if ($error->file) {
			$return .= "  File: $error->file";
		}

		log_error(E_USER_ERROR, $return, $filename, $linenumber);
	}
}

/**
 * Get an RSS feed.
 *
 * @param	string	$url	The URL of the RSS feed.
 * @param	int		$timeout	The cache timeout in hours.
 */	
function getRSS(&$url, $timeout=1)
{
	$starttime = microtime(true);
	$prev_error_state = libxml_use_internal_errors(true);
	list($rss, $sourcedocuments, $src) = getFromURL($url, $timeout, 'simplexml_load_file', 'simplexml_load_string', 'libxml_get_errors');
	libxml_use_internal_errors($prev_error_state);
	$sourcedocuments[] = makeProvenanceInfo('getRSS', (string)$src, null, $starttime, microtime(true));
	return array($rss, $sourcedocuments);
}

/**
 * Get an HTML page.
 *
 * @param	string	$url		The URL of the HTML page.
 * @param	int		$timeout	The cache timeout in hours.
 */
function getHTML(&$url, $timeout=1)
{
	$starttime = microtime(true);
	list($html, $sourcedocuments, $src) = getFromURL($url, $timeout, 'file_get_html', 'str_get_html');
	$sourcedocuments[] = makeProvenanceInfo('getHTML', (string)$src, null, $starttime, microtime(true));
	return array($html, $sourcedocuments);
}

/**
 * Get an HTML page.
 *
 * @param	string		$url			The URL of the document.
 * @param	int			$timeout		The cache timeout in hours.
 * @param	function	$getfromfile	Function to call to load the data from a file.
 * @param	function	$getfromstring	Function to call to load the data from a string.
 * @param	function	$errorfunction	Function to call for each error.
 */
function getFromURL(&$url, $timeout, $getfromfile, $getfromstring, $errorfunction = null)
{
	$cacheid = md5($url);
	global $diary_config;
	$filename = $diary_config["path"].'/var/cache/'.$cacheid;
	if(file_exists($filename) && filesize($filename) > 0 && filemtime($filename)+($timeout*60*60) > time())
	{
		$sourcedocuments = array(makeProvenanceInfo('cache', (string)$url, 'cache:'.$cacheid, null, filemtime($filename)));
		$data = $getfromfile($filename);  $lineno = __LINE__;
		if($data === false && !is_null($errorfunction))
		{
			retriggerErrors($errorfunction(), __FILE__, $lineno);
		}
		return array($data, $sourcedocuments, 'cache:'.$cacheid);
	}
	else
	{
		$hostname = parse_url($url, PHP_URL_HOST);
		if($hostname == "")
		{
			trigger_error("URL $url has no hostname, ignoring.");
			return;
		}
		$data = file_get_contents($url);
		if($data !== false)
		{
			file_put_contents($filename, $data);
			$data = $getfromstring($data);  $lineno = __LINE__;
			if($data === false && !is_null($errorfunction))
			{
				retriggerErrors($errorfunction(), __FILE__, $lineno);
			}
			return array($data, array(), $url);
		}
		else
		{
			if(!is_null($errorfunction))
			{
				retriggerErrors($errorfunction(), "?", "?");
			}
			return;
		}
	}
}

/**
 * Get the character set from a HTTP response header.
 *
 * @param	array	$http_response_header	The HTTP response header.
 */
function getCharacterSet($http_response_header) {
	$contenttype = "";
	foreach($http_response_header as $headerline)
	{
		$headerlineparts = explode(':', $headerline, 2);
		if(trim($headerlineparts[0]) == 'Content-Type')
		{
			$contenttype = trim($headerlineparts[1]);
			break;
		}
	}
	foreach(explode(';', $contenttype) as $ct)
	{
		$ctparts = explode('=', $ct);
		if(trim($ctparts[0] == 'charset'))
		{
			return trim($ctparts[1]);
		}
	}
	return "";
}

/**
 * Make array containing provenance information.
 *
 * @param	string	$type	URI of process type.
 * @param	string	$src	URI of source document.
 * @param	string	$dst	URI of destination document.
 * @param	float	$start	Start time.
 * @param	float	$end	End time.
 */
function makeProvenanceInfo($type, $src, $dst, $start, $end) {
	$pi = array();
	if($type != null) {
		$pi['type'] = $type;
	}
	if($src != null) {
		$pi['src'] = $src;
	}
	if($dst != null) {
		$pi['dst'] = $dst;
	}
	if($start != null) {
		$pi['start'] = $start;
	}
	if($end != null) {
		$pi['end'] = $end;
	}
	return $pi;
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
