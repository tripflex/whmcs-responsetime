<?php
/*
    Response Time Pro - Gives a detailed report and data feed on your average
    support times.
    Copyright (C) 2010-2012 WHMCS Addon

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
@error_reporting(0);
@ini_set("register_globals","off");

define("ROOTDIR",dirname(__FILE__)."/../");
require(ROOTDIR."dbconnect.php");

// This variable is for how long till the cache refreshes. Default: 1hour
$cachelength = 3600;

// Ignore ticket replies from escalations
$escalationIgnore = array();
$result = mysql_query("SELECT `addreply` FROM `tblticketescalations`");
while ($row = mysql_fetch_array($result)) {
	$escalationIgnore[] = $row["addreply"];	
}

$timeFrom = strtotime($_GET["from"]);

$format = $_GET["format"];
if (!$format) $format = "*days Day(s) *hours Hour(s) *minutes Minute(s) and *seconds Second(s)";

$averageTotal = 0;
$averageTotalCount = 0;
$median = array();

$cacherecord = md5($_SERVER["QUERY_STRING"]."AVERAGESUPPORTWIDGET");
$cachetime =  time();
$result = mysql_query("SELECT `cached` FROM `tblcache` WHERE `cacheid`='".mysql_real_escape_string($cacherecord)."' AND `cachetime`>".$cachetime) or checkcachetable();
while ($row = mysql_fetch_array($result)) {
	$cached = $row["cached"];
}
// If there is a cache then output cached data
if ($cached) {
	echo $cached;
} else {
	// if department forced find department id
	if ($_GET["department"] && !(int)$_GET["department"]) {
		$result = mysql_query("SELECT `id` FROM `tblticketdepartments` WHERE `name`='".mysql_real_escape_string($_GET["department"])."' LIMIT 1");
		while ($row = mysql_fetch_array($result)) {
			$departmentSelection = (int)$row["id"];
		}
	} elseif ($_GET["department"] && (int)$_GET["department"]) {
		$departmentSelection = (int)$_GET["department"];
	}
	
	if ($departmentSelection)
		$result = mysql_query("SELECT `id`, `date`, `admin` FROM `tbltickets` WHERE `did`=".(int)$departmentSelection);
	else
		$result = mysql_query("SELECT `id`, `date`, `admin` FROM `tbltickets`");
	while ($row = mysql_fetch_array($result)) {
		$currentTimeReference = 0;
		$average = 0;
		$averageCount = 0;
		
		$result2 = mysql_query("SELECT `admin`, `date`, `message` FROM `tblticketreplies` WHERE `tid`='".$row["id"]."' ORDER BY `id`");
		
		if (empty($row["admin"])) $currentTimeReference = strtotime($row["date"]);
	
		if ($timeFrom && $timeFrom <= $currentTimeReference || !$timeFrom) {
			while ($row2 = mysql_fetch_array($result2)) {
				
				if ($row2["admin"] && $currentTimeReference && !in_array($row2["message"], $escalationIgnore)) {
					$average += (strtotime($row2["date"]) - $currentTimeReference);
					$averageCount++;
					$currentTimeReference = 0;
				} elseif (!$currentTimeReference) {
					$currentTimeReference = strtotime($row2["date"]);
				}
			}
			
			$seconds = floor($average/$averageCount);
			if ($seconds) {
				$averageTotal += $seconds;
				$averageTotalCount++;
				
				$median[count($median)] = $seconds;
			}
		}
	}
	
	$seconds = floor($averageTotal/$averageTotalCount);
	
	if ($_GET["median"] == "yes" || $_GET["median"] == "true") {
		sort($median, SORT_NUMERIC);
		$middleMan = floor(count($median)/2);
		$seconds = $median[$middleMan];
	}
	
	if ($seconds) {
		$minutes = floor($seconds/60);
		$hours = floor($minutes/60);
		$days = floor($hours/24);
		
		$hours -= ($days*24);
		$minutes -= (($hours*60)+($days*24*60));
		$seconds -= (($days*24*60*60)+($hours*60*60)+($minutes*60));
		$format = str_replace("*days", $days, $format);
		$format = str_replace("*hours", $hours, $format);
		$format = str_replace("*minutes", $minutes, $format);
		$format = str_replace("*seconds", $seconds, $format);
		$output = "document.write('".addslashes($format)."');";
	} else {
		if ($_GET["notime"]) $format = $_GET["notime"];
		$seconds = 0;
		$minutes = 0;
		$hours = 0;
		$days = 0;
		
		$format = str_replace("*days", $days, $format);
		$format = str_replace("*hours", $hours, $format);
		$format = str_replace("*minutes", $minutes, $format);
		$format = str_replace("*seconds", $seconds, $format);
		$output = "document.write('".addslashes($format)."');";
	}
	echo $output;
	
	$query = "INSERT INTO `tblcache` (`cached`, `cacheid`, `cachetime`) VALUES ('".mysql_real_escape_string($output)."', '".$cacherecord."', '".(time()+$cachelength)."')";
	mysql_query($query) or checkcachetable($query);
}

function checkcachetable($run) {
	$result = mysql_query("CREATE TABLE IF NOT EXISTS `tblcache` (
  `id` int(11) NOT NULL auto_increment,
  `cached` text NOT NULL,
  `cacheid` text NOT NULL,
  `cachetime` int(11) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
");
	if ($run && $result) mysql_query($run);
}