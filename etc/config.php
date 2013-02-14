<?php
# Copyright (c) 2012 Christopher Gutteridge / University of Southampton
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

require_once( "/var/wwwsites/tools/arc2/ARC2.php" );
require_once( "/var/wwwsites/tools/Graphite/Graphite.php" );
error_reporting(E_ALL ^ E_DEPRECATED);

global $diary_config;

$diary_config["verbose"] = true;

$diary_config["rapper"] = '/usr/local/bin/rapper';

$diary_config["master_feed_uri"] = 'http://id.southampton.ac.uk/diary/'; 

$diary_config["ns"] = array(
	'diaryterms'=> 'http://id.southampton.ac.uk/ns/diary/',
	'diaryvalues'=> 'http://id.southampton.ac.uk/ns/diary/',
	'localfeed'=> 'http://id.southampton.ac.uk/diary/',
	'localevent'=> 'http://id.southampton.ac.uk/event/',
	'localroom'=> 'http://id.southampton.ac.uk/room/',
	'localbuilding'=> 'http://id.southampton.ac.uk/building/',
);


# load any extra bits and bobs:

$diary_config["fn_loadExtraTriples"] = function(&$graph)
{
	global $diary_config;
	$n = $graph->load( "file:///".$diary_config["path"]."/etc/extra-triples.ttl" );
	$endpoint ="http://sparql.data.southampton.ac.uk/";
	$queries = array( 'amenityplaces','orgs','places','rooms','within' );
	foreach( $queries as $query )
	{
		$fn = $diary_config["path"]."/etc/sparql/$query";
		$sparql = join(file($fn));
		$n=$graph->loadSPARQL($endpoint, $sparql );
	}
};

# Venue resolution code

/**
 * Try to get a venue link from the venue details.
 *
 * @param	string	$venue	The venue details.
 */
$diary_config["fn_getVenueLink"] = function(&$venue)
{
	$bdef = '[0-9]+[A-Z]?';
	$bdef = $bdef.'|\('.$bdef.'\)';
	$rdef = '[0-9][0-9][0-9][0-9][0-9]*';
	$rdef = $rdef.'|\('.$rdef.'\)';

	$v = strtoupper($venue);
	if(preg_match('/(ROOM|LECTURE THEATRE) ('.$rdef.')/', $v, $roommatches) &&
		preg_match('/(BUILDING|BLDG) ('.$bdef.')+/', $v, $buildingmatches))
	{
		$uri = 'http://id.southampton.ac.uk/room/'.tidyNumber($buildingmatches[2]).'-'.tidyNumber($roommatches[2], false);
		$v = preg_replace('/(ROOM|LECTURE THEATRE) ('.$rdef.')/', '', $v);
		$v = preg_replace('/(BUILDING|BLDG) ('.$bdef.')+/', '', $v);
		$v = finalStrip($v, $uri);
		if($v == "") $venue = "";
		return $uri;
	}
	if(preg_match('/('.$bdef.')[:\/]('.$rdef.')/', $v, $matches))
	{
		$uri = 'http://id.southampton.ac.uk/room/'.tidyNumber($matches[1]).'-'.tidyNumber($matches[2], false);
		$v = preg_replace('/('.$bdef.')[:\/]('.$rdef.')/', '', $v);
		$v = finalStrip($v, $uri);
		if($v == "") $venue = "";
		return $uri;
	}
	if(preg_match('/(BUILDING|BLDG) ('.$bdef.')+/', $v, $buildingmatches) && preg_match('/LECTURE THEATRE ([A-Z])/', $v, $matches))
	{
		$uri = getURIByRoomName($buildingmatches[2], $matches[0]);
		if($uri)
		{
			$v = str_replace($matches[0], '', $v);
			$v = str_replace($buildingmatches[0], '', $v);
			$v = finalStrip($v, $uri);
			if($v == "") $venue = "";
			return $uri;
		}
	}
	if(preg_match('/AVENUE CAMPUS/', $v) && preg_match('/LECTURE THEATRE ([A-Z])/', $v, $matches))
	{
		$uri = getURIByRoomName(65, $matches[0]);
		if($uri)
		{
			$v = str_replace($matches[0], '', $v);
			$v = str_replace('AVENUE CAMPUS', '', $v);
			$v = finalStrip($v, $uri);
			if($v == "") $venue = "";
			return $uri;
		}
	}
	if(preg_match('/(BUILDING|BLDG) ('.$bdef.')+/', $v, $buildingmatches))
	{
		$uri = 'http://id.southampton.ac.uk/building/'.tidyNumber($buildingmatches[2]);
		$v = preg_replace('/(BUILDING|BLDG) ('.$bdef.')+/', '', $v);
		$v = finalStrip($v, $uri);
		if($v == "") $venue = "";
		return $uri;
	}
	foreach(getMatches() as $uri => $match)
	{
		if($match['match'][0] == '/')
		{
			$ismatch = preg_match($match['match'], $v);
		}
		else
		{
			$ismatch = (strpos($v, $match['match']) !== false);
		}
		if($ismatch)
		{
			$v = finalStrip($v, $uri);
			if($v == "") $venue = "";
			return $uri;
		}
	}
	$v = finalStrip($v, null);
	if($v == "") $venue = "";
	return "";
};



/**
 * Tidy a number.
 *
 * @param	string	$text				The text to tidy.
 * @param	bool	$removeLeadingZeros	True to remove leading zeros.
 */
function tidyNumber($text, $removeLeadingZeros = true)
{
	$text = str_replace(array('(', ')'), '', $text);
	if($removeLeadingZeros)
	{
		$text = ltrim($text, '0');
	}
	return $text;
}

/**
 * Try to get a URI for a room, given its name and building number.
 *
 * @param	string	$buildingNumber	The number of the building that the room is located within.
 * @param	string	$roomName		The name of the room.
 */
function getURIByRoomName($buildingNumber, $roomName)
{
	$graph = new Graphite();
	
	$endpoint ="http://sparql.data.southampton.ac.uk/";
	$sparql = 'CONSTRUCT {
    ?s <http://www.w3.org/2000/01/rdf-schema#label> ?l .
} WHERE {
    ?s a <http://vocab.deri.ie/rooms#Room> .
    ?s <http://www.w3.org/2000/01/rdf-schema#label> ?l .
    ?s <http://data.ordnancesurvey.co.uk/ontology/spatialrelations/within> <http://id.southampton.ac.uk/building/'.$buildingNumber.'> .
    FILTER (REGEX(?l, "'.$roomName.'", "i")) 
}';

	$n = $graph->loadSPARQL($endpoint, $sparql);
	if($n == 1)
	{
		$res = $graph->allSubjects();
		return (string)$res[0];
	}
	else
	{
		trigger_error($n . " results found in lookup of '" . $roomName . "' in building " . $buildingNumber);
	}
	return false;
}

/**
 * Get the graph of location hierarchy.
 */
function getLocationHierarchy()
{
	$graph = new Graphite();
	
	$endpoint ="http://sparql.data.southampton.ac.uk/";
	$sparql = 'PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
PREFIX spacerel: <http://data.ordnancesurvey.co.uk/ontology/spatialrelations/>

CONSTRUCT {
    ?a spacerel:within ?b .
    ?a rdfs:label ?al .
    ?b rdfs:label ?bl .
} WHERE {
    ?a spacerel:within ?b .
    ?a rdfs:label ?al .
    ?b rdfs:label ?bl .
    ?a a ?type .
    FILTER ( ?type = <http://vocab.deri.ie/rooms#Room> ||
             ?type = <http://vocab.deri.ie/rooms#Building> ||
             ?type = <http://www.w3.org/ns/org#Site> )
}';

	$graph->loadSPARQL($endpoint, $sparql);
	return $graph;
}

function getMatches()
{
	$matches = array();
	//Non-university Venues
	$matches['http://id.southampton.ac.uk/point-of-service/chilworth-manor-hotel'] = 
		array(
			'match'		=> 'CHILWORTH MANOR',
			'remove'	=> array('CHILWORTH MANOR', 'CHILWORTH', 'SO16 7PT')
		);
	$matches['http://id.southampton.ac.uk/point-of-service/the-talking-heads'] = 
		array(
			'match'		=> 'THE TALKING HEADS',
			'remove'	=> 'THE TALKING HEADS'
		);
	$matches['http://id.southampton.ac.uk/point-of-service/devere-grand-harbour-hotel'] = 
		array(
			'match'		=> '/DE ?VERE GRAND HARBOUR/',
			'remove'	=> array('/DE ?VERE GRAND HARBOUR( HOTEL)?/', 'WEST QUAY ROAD', 'SO15 1AG')
		);
	$matches['http://id.southampton.ac.uk/point-of-service/st-michaels-church'] = 
		array(
			'match'		=> 'ST MICHAEL\'S CHURCH',
			'remove'	=> array('ST MICHAEL\'S CHURCH', 'BUGLE STREET')
		);
	$matches['http://id.southampton.ac.uk/point-of-service/chawton-house-library'] = 
		array(
			'match'		=> 'CHAWTON HOUSE LIBRARY',
			'remove'	=> array('CHAWTON HOUSE LIBRARY', 'CHAWTON', 'ALTON', 'GU34 1SJ')
		);

	//University Buildings
	$matches['http://id.southampton.ac.uk/building/50'] = 
		array(
			'match'		=> 'JOHN HANSARD GALLERY',
			'remove'	=> 'JOHN HANSARD GALLERY'
		);
	$matches['http://id.southampton.ac.uk/building/52'] = 
		array(
			'match'		=> 'TURNER SIMS',
			'remove'	=> '/TURNER SIMS( CONCERT HALL)?/'
		);

	//University Sites
	$matches['http://id.southampton.ac.uk/site/1'] = 
		array(
			'match'		=> 'HIGHFIELD CAMPUS',
			'remove'	=> array('/HIGHFIELD( CAMPUS)?/', 'UNIVERSITY ROAD', '/SO17 ?1BJ/')
		);
	$matches['http://id.southampton.ac.uk/site/3'] = 
		array(
			'match'		=> 'AVENUE CAMPUS',
			'remove'	=> array('AVENUE CAMPUS', 'HIGHFIELD ROAD', 'SO17 1BF')
		);
	$matches['http://id.southampton.ac.uk/site/4'] = 
		array(
			'match'		=> '/(WINCHESTER SCHOOL OF ART|WSA)/',
			'remove'	=> array('/(WINCHESTER SCHOOL OF ART|WSA)/', 'SO23 8DL')
		);
	$matches['http://id.southampton.ac.uk/site/6'] = 
		array(
			'match'		=> '/(NATIONAL OCEANOGRAPHY CENTRE|NOCS)/',
			'remove'	=> array('/(NATIONAL OCEANOGRAPHY CENTRE|NOCS)/', 'SO14 3ZH')
		);
	return $matches;
}

/**
 * Get the path of location URIs and names that the given URI falls within.
 *
 * @param	string	$uri		The URI of the location.
 * @param	array	$listuris	The array of URIs already in the path.
 * @param	array	$listnames	The array of names already in the path.
 */

$locationhierarchy = getLocationHierarchy();

function getLocationPath($uri, &$listuris = array(), &$listnames = array())
{
	global $locationhierarchy;
	$res = $locationhierarchy->resource($uri);

	$listuris[] = $uri;
	$listnames[] = $res->all('http://www.w3.org/2000/01/rdf-schema#label');

	if($res->has('http://data.ordnancesurvey.co.uk/ontology/spatialrelations/within'))
	{
		getLocationPath($res->get('http://data.ordnancesurvey.co.uk/ontology/spatialrelations/within'), $listuris, $listnames);
	}
	return array($listuris, $listnames);
}

/**
 * Remove unneccessary additional information from the venue details.
 *
 * @param	string	$v	The venue details.
 * @param	string	$uri	The URI of the venue.
 */
function finalStrip($v, $uri)
{
	if(!is_null($uri))
	{
		list($locationuris, $locationnames) = getLocationPath($uri);
		$locs = array();
		foreach($locationnames as $locations)
		{
			foreach($locations as $location)
			{
				$locs[] = (string)$location;
			}
		}
		$locationnames = $locs;
	}
	else
	{
		$locationuris = array();
		$locationnames = array();
	}


	foreach(getMatches() as $uri => $match)
	{
		if(in_array($uri, $locationuris))
		{
			if(!is_array($match['remove']))
			{
				$match['remove'] = array($match['remove']);
			}
			foreach($match['remove'] as $remove)
			{
				if($remove[0] == '/')
				{
					$v = preg_replace($remove, '', $v);
				}
				else
				{
					$v = str_replace($remove, '', $v);
				}
			}
		}
	}

	foreach($locationnames as $name)
	{
		$v = str_replace(strtoupper($name) . ' BUILDING', '', $v);
		$v = str_replace(strtoupper($name), '', $v);
	}

	$v = str_replace('UNIVERSITY OF SOUTHAMPTON', '', $v);
	$v = str_replace('SOUTHAMPTON', '', $v);
	$v = str_replace('HAMPSHIRE', '', $v);
	$v = str_replace('UNITED KINGDOM', '', $v);
	$v = str_replace('UK', '', $v);
	$v = str_replace('ALL WELCOME', '', $v);// Southampton Education School sometimes put this in their venue details!

	$v = trim(preg_replace('/[^A-Za-z0-9]/', '', $v));
	return $v;
}

