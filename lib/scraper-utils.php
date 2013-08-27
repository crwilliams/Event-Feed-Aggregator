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

include $diary_config["path"].'/lib/utils.php';
include $diary_config["path"].'/lib/options.php';

/**
 * Make an array of dates between the specified date range (inclusive).
 *
 * @param	DateTime	$datefrom	The start date.
 * @param	DateTime	$dateto		The end date.
 * @param	string		$timefrom	The start time (optional).
 * @param	string		$timeto		The end time (optional).
 */
function makeDateArray($datefrom, $dateto, $timefrom = null, $timeto = null) {
	$pdate = array();
	while($datefrom <= $dateto) {
		if($timefrom != null && $timeto != null) {
			$tmpfrom = strtotime($timefrom.' '.date_format($datefrom, 'Y/m/d'));
			$tmpto = strtotime($timeto.' '.date_format($datefrom, 'Y/m/d'));
			$date['from'] = date('c', $tmpfrom);
			$date['to'] = date('c', $tmpto);
		} else {
			$date['date'] = date_format($datefrom, 'c');
		}
		$pdate[] = $date;
		date_add($datefrom, new DateInterval('P1D'));
	}
	return $pdate;
}

/**
 * Try to extract the dates from a description of the event.
 *
 * @param	stringArray	$desc	The description of the event.
 * @param	object	$item	The RSS item XML (optional)
 */
function getDates(&$desc,$item=null)
{
	$pdate = array();
	if(count($desc) == 0)
	{
		return array();
	}

	// Remove any unexpected characters from the string before doing the comparisons.
	$compstr = preg_replace('/[^A-Za-z0-9 ,:\[\]-]+/', ' ', trim($desc[0]));

	// If the string if of one of the forms:
	// 'd mmm yyyy' (ie a single date)
	// 'hh:mm, d mmm yyyy' (ie a time and date)
	// 'hh:mm - hh:mm, d mmm yyyy' (ie a time range and a date)
	// ...
	if(preg_match('/^\[(([0-2]?[0-9]:[0-5][0-9])( - ([0-2]?[0-9]:[0-5][0-9]))?, )?(([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9]))\]$/', $compstr, $matches))
	{
		$date = array();
		if($matches[2] == "" && $matches[4] == "")
		{
			// No times specified.
			$date['date'] = date('c', strtotime($matches[6].' '.$matches[7].' '.$matches[8]));
		}
		else
		{
			$date['from'] = date('c', strtotime($matches[2].' '.$matches[6].' '.$matches[7].' '.$matches[8]));
			if($matches[4] != "")
			{
				// There is an end time to the event.
				$date['to'] = date('c', strtotime($matches[4].' '.$matches[6].' '.$matches[7].' '.$matches[8]));
			}
		}
		$pdate[] = $date;
		// Remove the line from the description.
		array_shift($desc);

		return $pdate;
	}

	// If the string is of the form 'hh:mm, d mmm yyyy - hh:mm, d mmm yyyy' (ie a time and date range)...
	if(preg_match('/^\[([0-2]?[0-9]:[0-5][0-9]), (([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9])) - ([0-2]?[0-9]:[0-5][0-9]), (([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9]))\]$/', $compstr, $matches))
	{
		// This event is assumed to take place from the specified time on the start day until the specified time on the end day.
		$date['from'] = date('c', strtotime($matches[1].' '.$matches[3].' '.$matches[4].' '.$matches[5]));
		$date['to'] = date('c', strtotime($matches[6].' '.$matches[8].' '.$matches[9].' '.$matches[10]));
		$pdate[] = $date;
		// Remove the line from the description.
		array_shift($desc);
		return $pdate;
	}

	// If the string is of the form 'hh:mm - hh:mm, d - d mmm yyyy' (ie a time range and a date range in a single month)...
	if(preg_match('/^\[([0-2]?[0-9]:[0-5][0-9]) - ([0-2]?[0-9]:[0-5][0-9]), ([1-3]?[0-9]) - ([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9])\]$/', $compstr, $matches))
	{
		// This event is assumed to take place during the specified time range on each day in the day range.
		$pdate = makeDateArray(new DateTime($matches[3].' '.$matches[5].' '.$matches[6]), new DateTime($matches[4].' '.$matches[5].' '.$matches[6]),
				$matches[1], $matches[2]);
		// Remove the line from the description.
		array_shift($desc);
		return $pdate;
	}

	// If the string is of the form 'hh:mm - hh:mm, d mmm yyyy - d mmm yyyy' (ie a time range and a date range)...
	if(preg_match('/^\[([0-2]?[0-9]:[0-5][0-9]) - ([0-2]?[0-9]:[0-5][0-9]), ([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9]) - ([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9])\]$/', $compstr, $matches))
	{
		// This event is assumed to take place on each day in the day range (time unspecified).
		$pdate = makeDateArray(new DateTime($matches[3].' '.$matches[4].' '.$matches[5]), new DateTime($matches[6].' '.$matches[7].' '.$matches[8]), $matches[1], $matches[2]);
		// Remove the line from the description.
		array_shift($desc);
		return $pdate;
	}

	// If the string is of the form 'd mmm yyyy - d mmm yyyy' (ie a date range)...
	if(preg_match('/^\[([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9]) - ([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9])\]$/', $compstr, $matches))
	{
		// This event is assumed to take place on each day in the day range (time unspecified).
		$pdate = makeDateArray(new DateTime($matches[1].' '.$matches[2].' '.$matches[3]), new DateTime($matches[4].' '.$matches[5].' '.$matches[6]));
		// Remove the line from the description.
		array_shift($desc);
		return $pdate;
	}

	// If the string is of the form 'd - d mmm yyyy' (ie a date range in a single month)...
	if(preg_match('/^\[([1-3]?[0-9]) - ([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9])\]$/', $compstr, $matches))
	{
		// This event is assumed to take place on each day in the day range (time unspecified).
		$pdate = makeDateArray(new DateTime($matches[1].' '.$matches[3].' '.$matches[4]), new DateTime($matches[2].' '.$matches[3].' '.$matches[4]));
		// Remove the line from the description.
		array_shift($desc);
		return $pdate;
	}

	// If the string is of the form 'hh:mm d mmm yyyy - d mmm yyyy' (ie time and a date range)...
	if(preg_match('/^\[([0-2]?[0-9]:[0-5][0-9]), ([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9]) - ([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9])\]$/', $compstr, $matches))
	{
		// This event is assumed to take place on each day in the day range, at the specified time.
		$pdate = makeDateArray(new DateTime($matches[2].' '.$matches[3].' '.$matches[4]), new DateTime($matches[5].' '.$matches[6].' '.$matches[7]), $matches[1]);
		// Remove the line from the description.
		array_shift($desc);
		return $pdate;
	}

	// If the string is of the form 'hh:mm, d - d mmm yyyy' (ie time and a date range in a single month)...
	if(preg_match('/^\[([0-2]?[0-9]:[0-5][0-9]), ([1-3]?[0-9]) - ([1-3]?[0-9]) ([A-Z][a-z][a-z])[a-z]* (20[0-9][0-9])\]$/', $compstr, $matches))
	{
		// This event is assumed to take place on each day in the day range, at the specified time.
		$pdate = makeDateArray(new DateTime($matches[2].' '.$matches[4].' '.$matches[5]), new DateTime($matches[3].' '.$matches[4].' '.$matches[5]), $matches[1]);
		// Remove the line from the description.
		array_shift($desc);
		return $pdate;
	}

	// If the string is of the form DOW d mmm - h:mm am [h:mm am]
	// This horror is used by the turner sims
	// Friday 20 September  - 7:30 pm 10:00 pm ...
	#if( preg_match( '/^\s*[A-Z][a-z]*\s+([123]?[0-9])\s+([A-Z][a-z][a-z])[a-z]*\s+-\s+(1?[0-9]):([012345]?[0-9])\s+(am|pm)(\s+(1?[0-9]):([012345]?[0-9])\s+(am|pm))?', $compstr, $matches ) )
	if( preg_match( '/^\s*[A-Z][a-z]*\s+([123]?[0-9])\s+([A-Z][a-z][a-z])[a-z]*\s+-\s+(1?[0-9]):([012345]?[0-9])\s+(am|pm)(\s+(1?[0-9]):([012345]?[0-9])\s+(am|pm))?/', $compstr, $matches ) )
	{
		if( $item && $item->pubDate && preg_match( '/(20\d\d)/', $item->pubDate, $pmatches ))
		{
			$year = $pmatches[1];
			$date = array();

			$from = sprintf( "%02d:%02d", $matches[3]+($matches[5]=="pm"?12:0), $matches[4] );
			$date['from'] = date('c', strtotime($from.' '.$matches[1].' '.$matches[2].' '.$year ));

			if( isset( $matches[6]) )
			{
				$to = sprintf( "%02d:%02d", $matches[7]+($matches[9]=="pm"?12:0), $matches[8] );
				$date['to'] = date('c', strtotime($to.' '.$matches[1].' '.$matches[2].' '.$year ));
			}
			$pdate[] = $date;
			array_shift($desc);
			return $pdate; 
		}
	}

#    [0] => Thursday 26 September  - 7:30 pm 10:00 pm
#    [1] => 26
#    [2] => Sep
#    [3] => 7
#    [4] => 30
#    [5] => pm
#    [6] =>  10:00 pm
#    [7] => 10
#    [8] => 00
#    [9] => pm


	$pdate = array();
	return $pdate;
}

/**
 * Tidy a speaker's name.
 *
 * @param	string	$text	The text to tidy.
 */
function tidySpeaker($text)
{
	// Remove everything beyond the first comma then space or double space.
	return preg_replace('/[, ] .*/', '', trim($text));
}

/**
 * Try to get additional information regarding the event.
 *
 * @param	string	$link	The page which provides details about the event.
 */
function getExtraInfo(&$link)
{
	$l = $link;
	list($dom, $sourcedocuments) = getHTML($l, 24);
	$link = $l;
	if($dom == null) { return array(); }
	$base = parse_url($link, PHP_URL_SCHEME).'://'.parse_url($link, PHP_URL_HOST);
	$div = $dom->find('div[id=content]', 0);
	if($div == null) { return array(); }
	$div = $div->find('div.iw_component', 0);
	if($div == null) { return array(); }
	$div = $div->find('div', 0);
	if($div == null) { return array(); }
	$venue = "";
	$venuelink = "";
	$speaker = "";
	$speakerlink = "";
	foreach($div->children() as $c)
	{
		if(strtolower($c->plaintext) == 'venue')
		{
			$venue = $c->next_sibling()->plaintext;
			$venuelink = getVenueLink($venue);
		}
		if(strtolower($c->plaintext) == 'speaker information')
		{
			$speakerel = $c->next_sibling();
			if($slink = $speakerel->find('a', 0))
			{
				$speaker = tidySpeaker($slink->innertext);
				$speakerlink = $slink->href;
				if($speakerlink[0] == '/')
				{
					$speakerlink = $base.$speakerlink;
				}
				if($speakerlink == 'http://')
				{
					$speakerlink = "";
				}
			}
			else if($span = $speakerel->find('span', 0))
			{
				$speaker = tidySpeaker($span->innertext);
			}
		}
	}
	return array(
		'venue' => str_replace(array("\n", "\r"), " ", $venue),
		'venuelink' => str_replace(array("\n", "\r"), " ", $venuelink),
		'speaker' => str_replace(array("\n", "\r"), " ", $speaker),
		'speakerlink' => str_replace(array("\n", "\r"), " ", $speakerlink),
		'sourceDocuments' => $sourcedocuments,
	);
}

/**
 * Try to get a venue link from the venue details.
 *
 * @param	string	$venue	The venue details.
 */
function getVenueLink(&$venue)
{
	global $diary_config;
	if( $diary_config["fn_getVenueLink"] )
	{
		$fn = $diary_config["fn_getVenueLink"];
		return $fn(&$venue);
	}
	return "";
}

function initialiseScraper()
{
	global $issues;
	global $data;
	$issues = array();
	$data = array();
	set_error_handler('log_error');
}

function processItem($item, $options, $sourcedocuments)
{
	global $issues;
	$feedissues = $issues;
	$issues = array();
	$res = process($item, $options, $sourcedocuments);
	$res['issues'] = $issues;
	$issues = $feedissues;
	return $res;
}
