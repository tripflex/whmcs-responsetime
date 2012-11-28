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

// Ignore ticket replies from escalations
$escalationIgnore = array();
$result = mysql_query("SELECT `addreply` FROM `tblticketescalations`");
while ($row = mysql_fetch_array($result)) {
	$escalationIgnore[] = $row["addreply"];	
}
		
if (!$_ADMINLANG["responseTime"]["ticketstatus"]) $_ADMINLANG["responseTime"]["ticketstatus"] = "Closed";


# The title of your report
if (!$_ADMINLANG["responseTime"]["title"]) $reportdata["title"] = "Response Time Pro";
else $reportdata["title"] = $_ADMINLANG["responseTime"]["title"];
# The description of your report
$reportdata["description"] .= "<style type=\"text/css\">a.rtp_tab {
-moz-border-radius:5px 5px 5px 5px;
-moz-box-shadow:0 0 3px;
-webkit-box-shadow:0 0 3px#888;
-webkit-border-radius:5px 5px 5px 5px;
border-radius:5px 5px 5px 5px;
box-shadow:0 0 3px;
background-color:#D1D1D1;
border:1px solid #999999;
color:#666666;
display:inline-block;
margin:3px 5px 3px 0;
padding:5px;
}
a.rtp_selected {
background-color: white;
}
a.rtp_tab:hover {
background-color:#999999;
border:1px solid #666666;
color:white;
}</style>";
if (!$_ADMINLANG["responseTime"]["description"]) {
	$reportdata["description"] .= "This report retrieves information about your average support time.<br />Response Time Pro offers a few different more indepth reports.<br /><br />";
	$reportdata["description"] .= "Select your report: ";
	if ($_GET["tab"] == 0)
		$reportdata["description"] .= "<a class=\"rtp_tab rtp_selected\" href=\"reports.php?report=response_time_pro&tab=0&from=".$_GET["from"]."\">General Report</a>";
	else
		$reportdata["description"] .= "<a class=\"rtp_tab\" href=\"reports.php?report=response_time_pro&tab=0&from=".$_GET["from"]."\">General Report</a>";
	
	if ($_GET["tab"] == 1)
		$reportdata["description"] .= "<a class=\"rtp_tab rtp_selected\" href=\"reports.php?report=response_time_pro&tab=1&from=".$_GET["from"]."\">Department Report</a>";
	else
		$reportdata["description"] .= "<a class=\"rtp_tab\" href=\"reports.php?report=response_time_pro&tab=1&from=".$_GET["from"]."\">Department Report</a>";
	
	if ($_GET["tab"] == 2)
		$reportdata["description"] .= "<a class=\"rtp_tab rtp_selected\" href=\"reports.php?report=response_time_pro&tab=2&from=".$_GET["from"]."\">Client Report</a>";
	else
		$reportdata["description"] .= "<a class=\"rtp_tab\" href=\"reports.php?report=response_time_pro&tab=2&from=".$_GET["from"]."\">Client Report</a>";
	$reportdata["description"] .= "<div style=\"border-top: 1px solid #CCC; height: 1px; overflow: hidden;\">&nbsp;</div>";
}
else $reportdata["description"] = $_ADMINLANG["responseTime"]["description"];

$timeFrom = strtotime($_GET["from"]);
//$reportdata["description"] = $timeFrom;

switch ($_GET["tab"]) {
case 1:
// Department Report
$reportdata["headertext"] .= "This report generates your ticket response time based on specific departments.";

$pageStart = ($_GET["page"]*30);
$inputPage = 0;

$dptResult = mysql_query("SELECT `name`, `id` FROM `tblticketdepartments`");
while ($dptRow = mysql_fetch_array($dptResult)) {
	$averageTotal = 0;
	$averageTotalCount = 0;
	
	$resAverageTotal = 0;
	$resAverageTotalCount = 0;
		
	$unanswered = 0;
	$unansweredClosed = 0;
	
	$median = array();
	$resMedian = array();
	
	$result = mysql_query("SELECT `id`, `date`, `lastreply`, `status`, `admin` FROM `tbltickets` WHERE `did`='".(int)$dptRow["id"]."' ORDER BY `id` ASC");
	while ($row = mysql_fetch_array($result)) {
		$currentTimeReference = 0;
		$average = 0;
		$averageCount = 0;
		
		$result2 = mysql_query("SELECT `admin`, `date`, `message` FROM `tblticketreplies` WHERE `tid`='".(int)$row["id"]."' ORDER BY `id` ASC");
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
				
				$dt = strtotime($row["date"]);
				$lr =  strtotime($row["lastreply"]);
				if ($row["status"] == $_ADMINLANG["responseTime"]["ticketstatus"] && $lr > $dt) {
				  $res = $lr - $dt;
				  $resMedian[count($resMedian)] = $res;
				  $resAverageTotal += $res;
				  $resAverageTotalCount++;
			  }
				
			} else {
				$unanswered++;
				if ($row["status"] == $_ADMINLANG["responseTime"]["ticketstatus"]) $unansweredClosed++;
			}

		}
	}
	
	$seconds = floor($averageTotal/$averageTotalCount);
	$resSeconds = floor($resAverageTotal/$resAverageTotalCount);
	
	if ($_GET["median"] == "no") {
		$_SESSION["reports"]["median"] = false;
	}
	
	if ($_GET["median"] == "yes" || $_SESSION["reports"]["median"]) {
		sort($median, SORT_NUMERIC);
		sort($resMedian, SORT_NUMERIC);
		$middleMan = floor(count($median)/2);
		$seconds = $median[$middleMan];
		$resSeconds = $resMedian[floor(count($resMedian)/2)];
		$_SESSION["reports"]["median"] = true;
	}
	
	if ($seconds) {
		$inputPage++;
		if ($pageStart < $inputPage && $inputPage <= ($pageStart+30)) {
			$minutes = floor($seconds/60);
			$hours = floor($minutes/60);
			$days = floor($hours/24);
			
			$hours -= ($days*24);
			$minutes -= (($hours*60)+($days*24*60));
			$seconds -= (($days*24*60*60)+($hours*60*60)+($minutes*60));
			
	

			if (!$_ADMINLANG["responseTime"]["avgRTime"]) $_thisTempLang["avgRTime"] = sprintf("%s Day(s) %s Hour(s) %s Minute(s) and %s Second(s)", $days, $hours, $minutes, $seconds);
			else $_thisTempLang["avgRTime"] = sprintf($_ADMINLANG["responseTime"]["avgRTime"], $days, $hours, $minutes, $seconds);
			$response = $_thisTempLang["avgRTime"]."<br />";	
		
			// Calculate Resolution Time
			$seconds = $resSeconds;
			$minutes = floor($seconds/60);
			$hours = floor($minutes/60);
			$days = floor($hours/24);
			
			$hours -= ($days*24);
			$minutes -= (($hours*60)+($days*24*60));

			$seconds -= (($days*24*60*60)+($hours*60*60)+($minutes*60));
			

			if (!$_ADMINLANG["responseTime"]["avgResTime"]) $_thisTempLang["avgResTime"] = sprintf("%s Day(s) %s Hour(s) %s Minute(s) and %s Second(s)", $days, $hours, $minutes, $seconds);
			else $_thisTempLang["avgResTime"] = sprintf($_ADMINLANG["responseTime"]["avgResTime"], $days, $hours, $minutes, $seconds);
			$resolution = $_thisTempLang["avgResTime"]."<br />";	
		
		
			$reportdata["tablevalues"][] = array("<a href=\"reports.php?report=response_time_pro&from=".$_GET["from"]."&tab=0&reference=departments&reference_variable=".$dptRow["id"]."\">".$dptRow["name"]."</a>", $response, $resolution);
		}
	}
}

# Header text - this gets displayed above the report table of data
if (!$_ADMINLANG["responseTime"]["tableheader"]) 
	$_ADMINLANG["responseTime"]["tableheader"] = "Average Response Time By Department";
	
# Report Table of Data Column Headings - should be an array of values
	if ($_ADMINLANG["responseTime"]["tableheadings"])
		$reportdata["tableheadings"] = $_ADMINLANG["responseTime"]["tableheadings"];
	else
		$reportdata["tableheadings"] = array("Department","Average Response Time","Resolution Time");

	break;
case 2:
// Client Report
$reportdata["headertext"] .= "This report generates your ticket response time based on specific clients.";

$reportdata["headertext"] .= "<br /><form method=\"get\" action=\"reports.php\">Client Search: <input type=\"hidden\" name=\"report\" value=\"response_time_pro\" /><input type=\"hidden\" name=\"from\" value=\"".$from."\" /><input type=\"hidden\" name=\"tab\" value=\"".$_GET["tab"]."\" /><input type=\"text\" name=\"client\" value=\"".$_GET["client"]."\" /><input type=\"submit\" value=\"Search\" /></form>";

$pageStart = ($_GET["page"]*30);
$inputPage = 0;

if ($_GET["client"] && (int)$_GET["client"])
	$clientResult = mysql_query("SELECT `firstname`, `lastname`, `id` FROM `tblclients` WHERE `id`=".(int)$_GET["client"]);
elseif ($_GET["client"]) {
	$client = array();
	$client = explode(" ", mysql_real_escape_string($_GET["client"]));
	foreach ($client as $c) {
		$queryAddition .= " OR `firstname` LIKE '%".$c."%'  OR `lastname` LIKE '%".$c."%'";
	}
	
	$clientResult = mysql_query("SELECT `firstname`, `lastname`, `id` FROM `tblclients` WHERE `firstname` LIKE '%".mysql_real_escape_string($_GET["client"])."%' OR `lastname` LIKE '%".mysql_real_escape_string($_GET["client"])."%' OR `email` LIKE '%".mysql_real_escape_string($_GET["client"])."%'". $queryAddition);
} else
	$clientResult = mysql_query("SELECT `firstname`, `lastname`, `id` FROM `tblclients`");
while ($clientRow = mysql_fetch_array($clientResult)) {
	$averageTotal = 0;
	$averageTotalCount = 0;
	
	$resAverageTotal = 0;
	$resAverageTotalCount = 0;
		
	$unanswered = 0;
	$unansweredClosed = 0;
	
	$median = array();
	$resMedian = array();
	$result = mysql_query("SELECT `id`, `date`, `lastreply`, `status`, `admin` FROM `tbltickets` WHERE `userid`='".(int)$clientRow["id"]."' ORDER BY `id` ASC");
	while ($row = mysql_fetch_array($result)) {
		$currentTimeReference = 0;
		$average = 0;
		$averageCount = 0;
		
		$result2 = mysql_query("SELECT `admin`, `date`, `message` FROM `tblticketreplies` WHERE `tid`='".(int)$row["id"]."' ORDER BY `id` ASC");
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
				
				$dt = strtotime($row["date"]);
				$lr =  strtotime($row["lastreply"]);
				if ($row["status"] == $_ADMINLANG["responseTime"]["ticketstatus"] && $lr > $dt) {
				  $res = $lr - $dt;
				  $resMedian[count($resMedian)] = $res;
				  $resAverageTotal += $res;
				  $resAverageTotalCount++;
			  }
				
			} else {
				$unanswered++;
				
				if ($row["status"] == $_ADMINLANG["responseTime"]["ticketstatus"]) $unansweredClosed++;
			}

		}
	}
	
	$seconds = floor($averageTotal/$averageTotalCount);
	$resSeconds = floor($resAverageTotal/$resAverageTotalCount);
	
	if ($_GET["median"] == "no") {
		$_SESSION["reports"]["median"] = false;
	}
	
	if ($_GET["median"] == "yes" || $_SESSION["reports"]["median"]) {
		sort($median, SORT_NUMERIC);
		sort($resMedian, SORT_NUMERIC);
		$middleMan = floor(count($median)/2);
		$seconds = $median[$middleMan];
		$resSeconds = $resMedian[floor(count($resMedian)/2)];
		$_SESSION["reports"]["median"] = true;
	}
	
	if ($seconds) {
		$inputPage++;
		if ($pageStart < $inputPage && $inputPage <= ($pageStart+30)) {
			$minutes = floor($seconds/60);
			$hours = floor($minutes/60);
			$days = floor($hours/24);
			
			$hours -= ($days*24);
			$minutes -= (($hours*60)+($days*24*60));
			$seconds -= (($days*24*60*60)+($hours*60*60)+($minutes*60));
			
	
			if (!$_ADMINLANG["responseTime"]["avgRTime"]) $_thisTempLang["avgRTime"] = sprintf("%s Day(s) %s Hour(s) %s Minute(s) and %s Second(s)", $days, $hours, $minutes, $seconds);
			else $_thisTempLang["avgRTime"] = sprintf($_ADMINLANG["responseTime"]["avgRTime"], $days, $hours, $minutes, $seconds);
			$response = $_thisTempLang["avgRTime"]."<br />";	
			
			// Calculate Resolution Time
			$seconds = $resSeconds;
			$minutes = floor($seconds/60);
			$hours = floor($minutes/60);
			$days = floor($hours/24);
			
			$hours -= ($days*24);
			$minutes -= (($hours*60)+($days*24*60));
			$seconds -= (($days*24*60*60)+($hours*60*60)+($minutes*60));
			

			if (!$_ADMINLANG["responseTime"]["avgResTime"]) $_thisTempLang["avgResTime"] = sprintf("%s Day(s) %s Hour(s) %s Minute(s) and %s Second(s)", $days, $hours, $minutes, $seconds);
			else $_thisTempLang["avgResTime"] = sprintf($_ADMINLANG["responseTime"]["avgResTime"], $days, $hours, $minutes, $seconds);
			$resolution = $_thisTempLang["avgResTime"]."<br />";	
		
		
		
			$reportdata["tablevalues"][] = array("<a href=\"reports.php?report=response_time_pro&from=".$_GET["from"]."&tab=0&reference=client&reference_variable=".$clientRow["id"]."\">".$clientRow["firstname"]." ".$clientRow["lastname"]."</a>", $response, $resolution);
		}
	}
}

# Header text - this gets displayed above the report table of data
if (!$_ADMINLANG["responseTime"]["tableheader"]) 
	$_ADMINLANG["responseTime"]["tableheader"] = "Average Response Time By Client";
	
# Report Table of Data Column Headings - should be an array of values
	if ($_ADMINLANG["responseTime"]["tableheadings"])
		$reportdata["tableheadings"] = $_ADMINLANG["responseTime"]["tableheadings"];
	else
		$reportdata["tableheadings"] = array("Client","Average Response Time","Resolution Time");

	break;
case 0:
default:

$averageTotal = 0;
$averageTotalCount = 0;

$resAverageTotal = 0;
$resAverageTotalCount = 0;
	
$unanswered = 0;
$unansweredClosed = 0;

$pageStart = ($_GET["page"]*30);
$inputPage = 0;
$median = array();
$resMedian = array();

if ($_GET["reference"] == "departments" && $_GET["reference_variable"]) {
	$reportdata["headertext"] .= "<strong>";
	
	$result = mysql_query("SELECT `id`, `date`, `lastreply`, `status`, `admin` FROM `tbltickets` WHERE `did`=".(int)$_GET["reference_variable"]." ORDER BY `id` ASC");
	if ($_ADMINLANG["responseTime"]["departmentFilter"]) $reportdata["headertext"] .=  sprintf($_ADMINLANG["responseTime"]["departmentFilter"], $_GET["reference_variable"]);
	else $reportdata["headertext"] .= "Filtered by Department ID ".$_GET["reference_variable"];
	
	$reportdata["headertext"] .= "</strong><br /><br />";
} elseif ($_GET["reference"] == "client" && $_GET["reference_variable"]) {
	$reportdata["headertext"] .= "<strong>";
	
	$result = mysql_query("SELECT `id`, `date`, `lastreply`, `status`, `admin` FROM `tbltickets` WHERE `userid`=".(int)$_GET["reference_variable"]." ORDER BY `id` ASC");
	if ($_ADMINLANG["responseTime"]["clientFilter"]) $reportdata["headertext"] .=  sprintf($_ADMINLANG["responseTime"]["clientFilter"], $_GET["reference_variable"]);
	else $reportdata["headertext"] .= "Filtered by Client ID ".$_GET["reference_variable"];
	
	$reportdata["headertext"] .= "</strong><br /><br />";
} else {
	$result = mysql_query("SELECT `id`, `date`, `lastreply`, `status`, `admin` FROM `tbltickets` ORDER BY `id` ASC");
}

while ($row = mysql_fetch_array($result)) {
	$currentTimeReference = 0;
	$average = 0;
	$averageCount = 0;
	$result2 = mysql_query("SELECT `admin`, `date`, `message` FROM `tblticketreplies` WHERE `tid`='".(int)$row["id"]."' ORDER BY `id` ASC");
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
			
			$median[] = $seconds;
			
			$minutes = floor($seconds/60);
			$hours = floor($minutes/60);
			$days = floor($hours/24);
			
			$hours -= ($days*24);
			$minutes -= (($hours*60)+($days*24*60));
			$seconds -= (($days*24*60*60)+($hours*60*60)+($minutes*60));
			$inputPage++;
			
			$dt = strtotime($row["date"]);
			$lr =  strtotime($row["lastreply"]);
			if ($row["status"] == $_ADMINLANG["responseTime"]["ticketstatus"] && $lr > $dt) {
				$res = $lr - $dt;
				
				$resMedian[] = $res;
				$resAverageTotal += $res;
				$resAverageTotalCount++;
				
				$minutes2 = floor($res/60);
				$hours2 = floor($minutes2/60);
				$days2 = floor($hours2/24);
				
				$hours2 -= ($days2*24);
				$minutes2 -= (($hours2*60)+($days2*24*60));
				$res -= (($days2*24*60*60)+($hours2*60*60)+($minutes2*60));
				
				$res = $days2." Days ".$hours2." Hours ".$minutes2." Minutes ".$res." Seconds";
			} else {
				$res = "Not resolved yet.";	
			}
		
		
			if ($pageStart < $inputPage && $inputPage <= ($pageStart+30)) {
				if (!$_ADMINLANG["responseTime"]["ticketstatus"]) $_ADMINLANG["responseTime"]["ticketstatus"] = "Closed";
				$reportdata["tablevalues"][] = array("<a href=\"supporttickets.php?action=viewticket&id=".$row["id"]."\">".$row["id"]."</a>",$days." Days ".$hours." Hours ".$minutes." Minutes ".$seconds." Seconds", $res);
			}
			
		} else {
			$unanswered++;
			if ($row["status"] == $_ADMINLANG["responseTime"]["ticketstatus"]) $unansweredClosed++;
		}

	}
}

$seconds = floor($averageTotal/$averageTotalCount);
$resSeconds = floor($resAverageTotal/$resAverageTotalCount);

if ($_GET["median"] == "no") {
	$_SESSION["reports"]["median"] = false;
}

if ($_GET["median"] == "yes" || $_SESSION["reports"]["median"]) {
	sort($median, SORT_NUMERIC);
	sort($resMedian, SORT_NUMERIC);
	$middleMan = floor(count($median)/2);
	//if ($middleMan == count($median)) $middleMan--;
	$seconds = $median[$middleMan];
	$resSeconds = $resMedian[floor(count($resMedian)/2)];
	$_SESSION["reports"]["median"] = true;
}

if ($seconds) {
	$minutes = floor($seconds/60);
	$hours = floor($minutes/60);
	$days = floor($hours/24);
	
	$hours -= ($days*24);
	$minutes -= (($hours*60)+($days*24*60));
	$seconds -= (($days*24*60*60)+($hours*60*60)+($minutes*60));
	
	if (!$timeFrom) {
		if (!$_ADMINLANG["responseTime"]["avgRTime"]) $_ADMINLANG["responseTime"]["avgRTime"] = sprintf("Your average response time is: %s Day(s) %s Hour(s) %s Minute(s) and %s Second(s)", $days, $hours, $minutes, $seconds);
		else $_ADMINLANG["responseTime"]["avgRTime"] = sprintf($_ADMINLANG["responseTime"]["avgRTime"], $days, $hours, $minutes, $seconds);
		$reportdata["headertext"] .= $_ADMINLANG["responseTime"]["avgRTime"]."<br />";	
	} else {
		if (!$_ADMINLANG["responseTime"]["avgRTimewFrom"]) $_ADMINLANG["responseTime"]["avgRTimewFrom"] = sprintf("Your average response time from \"%s\" is: %s Day(s) %s Hour(s) %s Minute(s) and %s Second(s)", $from, $days, $hours, $minutes, $seconds);
		else $_ADMINLANG["responseTime"]["avgRTimewFrom"] = sprintf($_ADMINLANG["responseTime"]["avgRTimewFrom"], $from, $days, $hours, $minutes, $seconds);
		$reportdata["headertext"] .= $_ADMINLANG["responseTime"]["avgRTimewFrom"]."<br />";	
	}
	
	// Calculate Resolution Time
	$seconds = $resSeconds;
	$minutes = floor($resSeconds/60);
	$hours = floor($minutes/60);
	$days = floor($hours/24);
	
	$hours -= ($days*24);
	$minutes -= (($hours*60)+($days*24*60));
	$seconds -= (($days*24*60*60)+($hours*60*60)+($minutes*60));
	
	if (!$timeFrom) {
		if (!$_ADMINLANG["responseTime"]["avgResTime"]) $_ADMINLANG["responseTime"]["avgResTime"] = sprintf("Your average resolution time is: <i>%s Day(s) %s Hour(s) %s Minute(s) and %s Second(s)</i>", $days, $hours, $minutes, $seconds);
		else $_ADMINLANG["responseTime"]["avgResTime"] = sprintf($_ADMINLANG["responseTime"]["avgResTime"], $days, $hours, $minutes, $seconds);
		$reportdata["headertext"] .= $_ADMINLANG["responseTime"]["avgResTime"]."<br />";	
	} else {
		if (!$_ADMINLANG["responseTime"]["avgResTimewFrom"]) $_ADMINLANG["responseTime"]["avgResTimewFrom"] = sprintf("Your average resolution time from \"%s\" is: <i>%s Day(s) %s Hour(s) %s Minute(s) and %s Second(s)</i>", $_GET["from"], $days, $hours, $minutes, $seconds);
		else $_ADMINLANG["responseTime"]["avgResTimewFrom"] = sprintf($_ADMINLANG["responseTime"]["avgResTimewFrom"], $days, $hours, $minutes, $seconds);
		$reportdata["headertext"] .= $_ADMINLANG["responseTime"]["avgResTimewFrom"]."<br />";	
	}
}


if (!$_ADMINLANG["responseTime"]["ticket"]) $_ADMINLANG["responseTime"]["ticket"] = sprintf("You currently have %s unanswered tickets, with %s of them closed.", $unanswered, $unansweredClosed);
else $_ADMINLANG["responseTime"]["ticket"] = sprintf($_ADMINLANG["responseTime"]["ticket"], $unanswered, $unansweredClosed);
		
$reportdata["headertext"] .= $_ADMINLANG["responseTime"]["ticket"]."<br />";

if (!$_ADMINLANG["responseTime"]["ticketIgnore"]) $reportdata["headertext"] .= "<span style=\"font-size: 10px;\">This report ignores unanswered tickets.</span><br />";
else $reportdata["headertext"] .= "<span style=\"font-size: 10px;\">".$_ADMINLANG["responseTime"]["ticketIgnore"]."</span><br />";

# Header text - this gets displayed above the report table of data
if (!$_ADMINLANG["responseTime"]["tableheader"]) 
	$_ADMINLANG["responseTime"]["tableheader"] = "Average Response Time By Ticket";
	
# Report Table of Data Column Headings - should be an array of values
	if ($_ADMINLANG["responseTime"]["tableheadings"])
		$reportdata["tableheadings"] = $_ADMINLANG["responseTime"]["tableheadings"];
	else
		$reportdata["tableheadings"] = array("Ticket ID","Average Response Time","Resolution Time");

}

if (!$_ADMINLANG["responseTime"]["enterDate"]) 
	$reportdata["headertext"] .= "<br /><form method=\"get\" action=\"reports.php\">Enter a start date: <input type=\"hidden\" name=\"report\" value=\"response_time_pro\" /><input type=\"text\" name=\"from\" id=\"from\" value=\"".$from."\" /><input type=\"hidden\" name=\"tab\" value=\"".$_GET["tab"]."\" /><input type=\"hidden\" name=\"reference_variable\" value=\"".$_GET["reference_variable"]."\" /><input type=\"hidden\" name=\"reference\" value=\"".$_GET["reference"]."\" /><input type=\"hidden\" name=\"client\" value=\"".$_GET["client"]."\" /><input type=\"submit\" value=\"Generate Report\" /></form><br />";
else
	$reportdata["headertext"] .= "<br /><form method=\"get\" action=\"reports.php\">".$_ADMINLANG["responseTime"]["enterDate"]." <input type=\"hidden\" name=\"report\" value=\"response_time_pro\" /><input type=\"text\" id=\"from\" name=\"from\" value=\"".$from."\" /><input type=\"hidden\" name=\"tab\" value=\"".$_GET["tab"]."\" /><input type=\"hidden\" name=\"reference_variable\" value=\"".$_GET["reference_variable"]."\" /><input type=\"hidden\" name=\"reference\" value=\"".$_GET["reference"]."\" /><input type=\"hidden\" name=\"client\" value=\"".$_GET["client"]."\" /><input type=\"submit\" value=\"".$_ADMINLANG["responseTime"]["generateReport"]."\" /></form><br />
";

if (!$_ADMINLANG["responseTime"]["averageType"]) $_ADMINLANG["responseTime"]["averageType"] = "Average Type";
if (!$_ADMINLANG["responseTime"]["averageTypeMedian"]) $_ADMINLANG["responseTime"]["averageTypeMedian"] = "Use Median";
if (!$_ADMINLANG["responseTime"]["averageTypeMean"]) $_ADMINLANG["responseTime"]["averageTypeMean"] = "Use Mean";


$reportdata["headertext"] .= $_ADMINLANG["responseTime"]["averageType"].": ";
if ($_GET["median"] != "yes" || !$_SESSION["reports"]["median"])
	$reportdata["headertext"] .= "<form style=\"display:inline;\" method=\"get\" action=\"reports.php\"><input type=\"hidden\" name=\"report\" value=\"response_time_pro\" /><input type=\"hidden\" name=\"median\" value=\"yes\" /><input type=\"hidden\" name=\"from\" value=\"".$from."\" /><input type=\"hidden\" name=\"reference_variable\" value=\"".$_GET["reference_variable"]."\" /><input type=\"hidden\" name=\"reference\" value=\"".$_GET["reference"]."\" /><input type=\"hidden\" name=\"client\" value=\"".$_GET["client"]."\" /><input type=\"hidden\" name=\"tab\" value=\"".$_GET["tab"]."\" /><input type=\"submit\" value=\"".$_ADMINLANG["responseTime"]["averageTypeMedian"]."\" /></form><br />";
else
	$reportdata["headertext"] .= "<form style=\"display:inline;\" method=\"get\" action=\"reports.php\"><input type=\"hidden\" name=\"report\" value=\"response_time_pro\" /><input type=\"hidden\" name=\"median\" value=\"no\" /><input type=\"hidden\" name=\"from\" value=\"".$from."\" /><input type=\"hidden\" name=\"reference_variable\" value=\"".$_GET["reference_variable"]."\" /><input type=\"hidden\" name=\"reference\" value=\"".$_GET["reference"]."\" /><input type=\"hidden\" name=\"client\" value=\"".$_GET["client"]."\" /><input type=\"hidden\" name=\"tab\" value=\"".$_GET["tab"]."\" /><input type=\"submit\" value=\"".$_ADMINLANG["responseTime"]["averageTypeMean"]."\" /></form><br />";


$reportdata["headertext"] .= "<script type=\"text/javascript\">
	jQuery( \"#from\" ).datepicker().datepicker( \"option\", \"dateFormat\", \"mm/dd/yy\" );
	</script>";

if (!$_ADMINLANG["responseTime"]["next"]) 
	$nextText = "Next Page >";
else
	$nextText = $_ADMINLANG["responseTime"]["next"];
	
if (!$_ADMINLANG["responseTime"]["previous"]) 
	$prevText = "< Previous";
else
	$prevText = $_ADMINLANG["responseTime"]["previous"];


if (!$timeFrom) {
	$data["footertext"] .= "<div style=\"text-align: center;\">";   
	if ($_GET["page"] != 0) $data["footertext"] .= "<a href=\"reports.php?report=response_time_pro&tab=".$_GET["tab"]."&page=".($_GET["page"]-1)."\">".$prevText."</a> | ";  
	
	for ($i = 0; $i < $inputPage; $i+=30) {
		if ($_GET["page"] != ($i/30))
			$data["footertext"] .= " <a href=\"reports.php?report=response_time_pro&tab=".$_GET["tab"]."&page=".($i/30)."\">".(($i/30)+1)."</a> ";	
		else
			$data["footertext"] .= " <strong>".(($i/30)+1)."</strong> ";
	}
	
	if ($inputPage > ($pageStart+30)) $data["footertext"] .= " | <a href=\"reports.php?report=response_time_pro&tab=".$_GET["tab"]."&page=".($_GET["page"]+1)."\">".$nextText."</a>"; 
	$data["footertext"] .= "</div>";   
} else {
	$data["footertext"] .= "<div style=\"text-align: center;\">";   
	if ($_GET["page"] != 0) $data["footertext"] .= "<a href=\"reports.php?report=response_time_pro&tab=".$_GET["tab"]."&page=".($_GET["page"]-1)."&from=".$_GET["from"]."\">".$prevText."</a> | ";  
	
	for ($i = 0; $i < $inputPage; $i+=30) {
		if ($_GET["page"] != ($i/30))
			$data["footertext"] .= " <a href=\"reports.php?report=response_time_pro&tab=".$_GET["tab"]."&page=".($i/30)."\">".(($i/30)+1)."</a> ";	
		else
			$data["footertext"] .= " <strong>".(($i/30)+1)."</strong> ";	
	}
	 
	if ($inputPage > ($pageStart+30)) $data["footertext"] .= " | <a href=\"reports.php?report=response_time_pro&tab=".$_GET["tab"]."&page=".($_GET["page"]+1)."&from=".$_GET["from"]."\">".$nextText."</a>"; 
	$data["footertext"] .= "</div>";  
	
	$_ADMINLANG["responseTime"]["tableheader"] .= " from \"".$from."\"";
}
	
$reportdata["headertext"] .= "<br /><strong>".$_ADMINLANG["responseTime"]["tableheader"]."</strong>";
# Report Footer Text - this gets displayed below the report table of data
$data["footertext"] .= "Generated by: <a href=\"http://whmcsaddon.com\" target=\"_blank\">WHMCS Addon</a>";

?>