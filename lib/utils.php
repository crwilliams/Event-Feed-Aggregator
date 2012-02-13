<?
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

date_default_timezone_set('Europe/London');
require_once $diary_config["path"].'/lib/xml.php';
require_once $diary_config["path"].'/lib/simple_html_dom.php';

function tidyNumber($text)
{
	return str_replace(array('(', ')'), '', $text);
}

function getVenueLink(&$venue)
{
	$bdef = '[0-9]+[A-Z]?';
	$bdef = $bdef.'|\('.$bdef.'\)';
	$rdef = '[0-9][0-9][0-9][0-9][0-9]*';
	$rdef = $rdef.'|\('.$rdef.'\)';

	$v = strtoupper($venue);
	if(preg_match('/(ROOM|LECTURE THEATRE) ('.$rdef.')/', strtoupper($venue), $roommatches) &&
		preg_match('/(BUILDING|BLDG) ('.$bdef.')+/', strtoupper($venue), $buildingmatches))
	{
		$v = preg_replace('/(ROOM|LECTURE THEATRE) ('.$rdef.')/', '', $v);
		$v = preg_replace('/(BUILDING|BLDG) ('.$bdef.')+/', '', $v);
		$v = finalStrip($v);
		if($v == "") $venue = "";
		return 'http://id.southampton.ac.uk/room/'.tidyNumber($buildingmatches[2]).'-'.tidyNumber($roommatches[2]);
	}
	if(preg_match('/('.$bdef.')[:\/]('.$rdef.')/', strtoupper($venue), $matches))
	{
		$v = preg_replace('/('.$bdef.')[:\/]('.$rdef.')/', '', $v);
		$v = finalStrip($v);
		if($v == "") $venue = "";
		return 'http://id.southampton.ac.uk/room/'.tidyNumber($matches[1]).'-'.tidyNumber($matches[2]);
	}
	if(preg_match('/(BUILDING|BLDG) ('.$bdef.')+/', strtoupper($venue), $buildingmatches))
	{
		$v = preg_replace('/(BUILDING|BLDG) ('.$bdef.')+/', '', $v);
		$v = finalStrip($v);
		if($v == "") $venue = "";
		return 'http://id.southampton.ac.uk/building/'.tidyNumber($buildingmatches[2]);
	}
	if(preg_match('/TURNER SIMS/', strtoupper($venue)))
	{
		$v = finalStrip($v);
		if($v == "") $venue = "";
		return 'http://id.southampton.ac.uk/building/52';
	}
	if(preg_match('/AVENUE CAMPUS/', strtoupper($venue)))
	{
		$v = finalStrip($v);
		if($v == "") $venue = "";
		return 'http://id.southampton.ac.uk/site/3';
	}
	if(preg_match('/HIGHFIELD CAMPUS/', strtoupper($venue)))
	{
		$v = finalStrip($v);
		if($v == "") $venue = "";
		return 'http://id.southampton.ac.uk/site/1';
	}
	if(preg_match('/(NATIONAL OCEANOGRAPHY CENTRE|NOCS)/', strtoupper($venue)))
	{
		$v = finalStrip($v);
		if($v == "") $venue = "";
		return 'http://id.southampton.ac.uk/site/6';
	}
	if(preg_match('/(WINCHESTER SCHOOL OF ART|WSA)/', strtoupper($venue)))
	{
		$v = finalStrip($v);
		if($v == "") $venue = "";
		return 'http://id.southampton.ac.uk/site/4';
	}
	$v = finalStrip($v);
	if($v == "") $venue = "";
	return "";
}

function finalStrip($v)
{
	$v = preg_replace('/TURNER SIMS( CONCERT HALL)?/', '', $v);
	$v = preg_replace('/AVENUE CAMPUS/', '', $v);
	$v = preg_replace('/HIGHFIELD CAMPUS/', '', $v);
	$v = preg_replace('/(NATIONAL OCEANOGRAPHY CENTRE|NOCS)/', '', $v);
	$v = preg_replace('/(WINCHESTER SCHOOL OF ART|WSA)/', '', $v);
	$v = preg_replace('/(UNIVERSITY OF )?SOUTHAMPTON/', '', $v);
	$v = preg_replace('/SO17 1BJ/', '', $v);
	$v = preg_replace('/UNITED KINGDOM/', '', $v);
	$v = preg_replace('/ALL WELCOME/', '', $v);// Southampton Education School sometimes put this in their venue details!
	$v = trim(preg_replace('/[^A-Za-z0-9]/', '', $v));
	return $v;
}

function logError($error, $feedid=null)
{
	if($feedid == null)
	{
		global $options;
		$feedid = $options->FeedID;
	}
	$file = fopen('/home/diary/var/log/'.$feedid.'.log', 'a');
	fputs($file, date('c')."\t".$error."\n");
	fclose($file);
}

function getRSS(&$url, $timeout=1)
{
	return getFromURL($url, $timeout, 'simplexml_load_file', 'simplexml_load_string');
}

function getHTML(&$url, $timeout=1)
{
	return getFromURL($url, $timeout, 'file_get_html', 'str_get_html');
}

function getFromURL(&$url, $timeout, $getfromfile, $getfromstring)
{
	$cacheid = md5($url);
	$filename = '/home/diary/var/cache/'.$cacheid;
	if(file_exists($filename) && filesize($filename) > 0 && filemtime($filename)+($timeout*60*60) > time())
	{
		return $getfromfile($filename);
	}
	else
	{
		$hostname = parse_url($url, PHP_URL_HOST);
		if(!dns_check_record($hostname, 'A') && !dns_check_record($hostname, 'AAAA'))
		{
			if(preg_match('/^([a-z]+)\.(soton|southampton)\.ac\.uk$/', $hostname, $matches))
			{
				if($matches[1] != "www")
				{
					logError("Hostname $hostname has no DNS entry, trying to modify URL.");
					$url = str_replace($hostname, "www.".$matches[2].".ac.uk/".$matches[1], $url);
					return getFromURL($url, $timeout, $getfromfile, $getfromstring);
				}
			}
			else
			{
				logError("Hostname $hostname has no DNS entry, unable to modify URL");
				file_put_contents($filename . ".err", $url."\n");
				return "";
			}
		}
		$data = @file_get_contents($url);
		$code = getResponseCode($http_response_header);
		if($code != 200 && $code != 301 && $code != 302)
		{
			logError("Failed to fetch $url.  Response code: $code");
		}
		else
		{
			$cs = getCharacterSet($http_response_header);
			if($cs == "")
			{
				$data = mb_convert_encoding($data, "ASCII");
			}
			else
			{
				$data = mb_convert_encoding($data, "ASCII", $cs);
			}
			file_put_contents($filename, $data);
			return $getfromstring($data);
		}
	}
}

function getResponseCode($http_response_header) {
	$statusline = explode(' ', $http_response_header[0], 3);
	return $statusline[1];
}

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
?>
