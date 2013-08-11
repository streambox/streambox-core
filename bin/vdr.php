<?php
include ('./svdrp.php');

function vdrsendcommand($cmd)
{
	global $svdrpip, $svdrpport;

	addlog("Sending SVDRP command: " .$cmd);

	$svdrp = new SVDRP($svdrpip, $svdrpport);
	$svdrp->Connect();
	$ret = $svdrp->Command($cmd);
	$svdrp->Disconnect();

	addlog("SVDRP command result received");

	return $ret;
}

function vdrgetchannum($chan)
{
	addlog("VDR: vdrgetchannum(chan=" .$chan .")");

	if ($_SESSION['channels'] == "")
		$_SESSION['channels'] = vdrsendcommand("LSTC");

	// Get channel number
	$chans = preg_grep(quotemeta('" '.$chan.';| '.$chan.':"'), $_SESSION['channels']);

	$chans = explode(" ", $chans[key($chans)]);
	$channum = $chans[0];

	return $channum;
}

function vdrgetchanname($channum)
{
	addlog("VDR: vdrgetchanname(channum=" .$channum .")");

	$channel = vdrsendcommand("LSTC " .$channum);

	// Get channel name
	$chanarray = explode(":", $channel);
	$chanarray = explode(";", $chanarray[0]);
	$channame = $chanarray[0];
	$channame = substr($channame, strlen($channum)+1);

	if(!is_utf8($channame))
		$channame = utf8_encode($channame);

	return $channame;
}

function vdrgetchaninfo($channame)
{
	addlog("VDR: vdrgetchaninfo(channame=" .$channame .")");

	$info = array();

	$info['name'] = $channame;
	$info['number'] = vdrgetchannum($channame);
	list($date, $info['now_time'], $info['now_title'], $info['now_desc']) = vdrgetepgat($info['number'], "now");
	list($date, $info['next_time'], $info['next_title'], $info['next_desc']) = vdrgetepgat($info['number'], "next");

	return $info;
}

function vdrgetepgat($channum, $at)
{
	addlog("VDR: vdrgetepgat(channum=" .$channum .", at=" .$at .")");

	$cmd = "LSTE " .$channum ." " .$at;

	$epg = vdrsendcommand($cmd);

	$time="";
	$title="";
	$desc="";

	// For all epg
	$count = count($epg);
	for ($i = 0; $i < $count; $i++)
	{
		if(ereg("^T ", $epg[$i]))
			$title = substr($epg[$i], 2);
		else if(ereg("^D ", $epg[$i]))
			$desc = preg_replace("/\|/", "<br>", substr($epg[$i], 2));
		else if(ereg("^E ", $epg[$i]))
		{
			$time = substr($epg[$i], 2);
			$timearray = explode(" ", $time);

			$starttime = $timearray[1];
			$endtime = $timearray[1]+$timearray[2];

			$time = date('H\hi', $starttime) ."-" .date('H\hi', $endtime);
			$date = date('Y\/m\/d', $starttime);

			$currenttime = date("U");
			if (($currenttime >= $starttime) && ($currenttime < $endtime))
				$running = "yes";
			else
				$running = "no";
		}
	}

	// Convert if needed
	if (!is_utf8($title))
		$title = utf8_encode($title);
	if (!is_utf8($desc))
		$desc = utf8_encode($desc);

	return array($date, $time, $title, $desc, $endtime, $running);
}

function vdrgetfullepgat($channel, $at, $programs, $requestedday)
{
	addlog("VDR: vdrgetfullepgat(channel=" .$channel .", at=" .$at .", programs=" .$programs .", requestedday=" .$requestedday .")");

	$epgout = array();

	// Generate the epgout
	$categories = getcategories();
	for ($i=0; $i<count($categories); $i++)
	{
		$catentry = array();
		$catentry['name'] = $categories[$i]['name'];
		$catentry['channel'] = array();

		$channels = getchannels($categories[$i]['name']);

		for ($j=0; $j<count($channels); $j++)
		{
			$programscounter=0;

			// Create a new chan entry
			$chanentry = array();
			$chanentry['name'] = $channels[$j]['name'];
			$chanentry['number'] = vdrgetchannum($channels[$j]['name']);
			$chanentry['epg'] = array();

			if ( ($channel != "all") && ( $chanentry['number'] != $channel) )
				continue;

			if ($_SESSION['epg' .$channels[$j]['name']] == "")
				$_SESSION['epg' .$channels[$j]['name']] = vdrsendcommand("LSTE " .$channels[$j]['number']);

			$validepg = 0;
			$chanepg = array();

			// For all epg
			for ($k=0; $k<count($_SESSION['epg' .$channels[$j]['name']]); $k++)
			{

				// Find a new EPG entry
				if(ereg("^E", $_SESSION['epg' .$channels[$j]['name']][$k]))
				{
					$time = substr($_SESSION['epg' .$channels[$j]['name']][$k], 2);
					$timearray = explode(" ", $time);

					$starttime = $timearray[1];
					$endtime = $timearray[1]+$timearray[2];

					switch ($programs)
					{
						case "all":
							$validepg = 1;
							break;
						case "day":
							$dayendtime = $requestedday + (3600*24);
							if (($endtime > $at) && ($starttime < $dayendtime))
								$validepg = 1;
							else
								$validepg = 0;
							break;
						default:
							if (($endtime > $at) && ($programscounter < $programs))
								$validepg = 1;
							else
								$validepg = 0;
							break;
					}

					if (!$validepg)
						continue;

					// New valid epg found
					$chanepg['title'] = "";
					$chanepg['time'] = date('H\hi', $timearray[1]) ."-" .date('H\hi', $timearray[1]+$timearray[2]);

					continue;
				}

				if(ereg("^T", $_SESSION['epg' .$channels[$j]['name']][$k]) && $validepg)
				{
					$chanepg['title'] = substr($_SESSION['epg' .$channels[$j]['name']][$k], 2);
					if (!is_utf8($chanepg['title']))
						$chanepg['title'] = utf8_encode($chanepg['title']);

					continue;
				}

				// Add a new epg
				if(ereg("^e", $_SESSION['epg' .$channels[$j]['name']][$k]))
				{
					if ($validepg)
					{
						$chanentry['epg'][] = $chanepg;
						$programscounter++;

						$validepg = 0;
					}
					continue;
				}
			}

			$catentry['channel'][] = $chanentry;
		}

		$epgout[] = $catentry;
	}

	return $epgout;
}

function vdrgetepg($channel, $time, $day, $programs, $extended)
{
	addlog("VDR: vdrgetepg(channel=" .$channel .", time=" .$time .", programs=" .$programs .", extended=" .$extented .")");

	// Get local time (Not UTC)
	$currentdate = date("U");

	// Remove current day seconds
	$requestedday = $currentdate - date("Z") - ($currentdate % (3600*24)) + ($day * (3600*24));

	switch ($programs)
	{
		case "all":
			// Get all entries
			break;

		case "day":
			// Get all day
			$requesteddate = $requestedday;
			if ($time)
			{
				$requestedtime = (substr($time, 0, 2) * 3600) + (substr($time, 2) * 60);
				$requesteddate += $requestedtime;
			}
			break;

		default:
			// Get exact time
			switch ($time)
			{
				case "now":
					$requesteddate = $currentdate;
					break;
				default:
					$requestedtime = (substr($time, 0, 2) * 3600) + (substr($time, 2) * 60);
					$requesteddate = $requestedday + $requestedtime;
					break;
			}
	}

	if ($extended)
	{
		list ($chanentry['date'], $chanentry['time'], $chanentry['title'], $chanentry['desc'], $endtime, $chanentry['running'])
			= vdrgetepgat($channel, "at " .$requesteddate);
		$chanentry['name'] = vdrgetchanname($channel);
		return $chanentry;
	}
	else
		return vdrgetfullepgat($channel, $requesteddate, $programs, $requestedday);
}

function vdrgetrecinfo($rec)
{
	global $recpath;

	addlog("VDR: vdrgetrecinfo(rec=" .$rec .")");

	$infofile = $recpath .$rec ."/info";
	if (file_exists($infofile))
		$info= file_get_contents($infofile);
	else
	{
		$infofile = $recpath .$rec ."/info.vdr";
		if (file_exists($infofile))
			$info= file_get_contents($infofile);
		else
			$info="";
	}

	$allepg = explode("\n", $info);

	$epgtitle="";
	$epgdesc="";

	// For all epg
	$count = count($allepg);
	for ($i = 0; $i < $count; $i++)
	{
		// Now find T or C
		if(ereg("^C", $allepg[$i]))
		{
			$channame = substr($allepg[$i], 2);
			$channames = explode(" ", $channame);
			$channame = substr($channame, strlen($channames[0])+1);
		}
		else if(ereg("^T", $allepg[$i]))
			$epgtitle = substr($allepg[$i], 2);
		else if(ereg("^D", $allepg[$i]))
			$epgdesc = preg_replace("/\|/", "<br>", substr($allepg[$i], 2));
		else if(ereg("^E ", $allepg[$i]))
		{
			$time = substr($allepg[$i], 2);
			$timearray = explode(" ", $time);

			$recorded = date('Y\/m\/d \a\t H\hi', $timearray[1]);
		}

	}

	// Convert if needed
	if (!is_utf8($epgtitle))
		$epgtitle = utf8_encode($epgtitle);
	if (!is_utf8($epgdesc))
		$epgdesc = utf8_encode($epgdesc);

	return array($channame, $epgtitle, $epgdesc, $recorded);
}

function vdrlisttimers()
{
	global $username;

	addlog("VDR: vdrlisttimers()");

	$timerslist = array();

	$timers = vdrsendcommand("LSTT");

	if (gettype($timers) == "string")
	{
		if (!is_numeric(substr($timers,0,1)))
			return $timerslist;
		else
			$timersarray[] = $timers;
	}
	else
		$timersarray = $timers;

	foreach($timersarray as $timer)
	{
		$newtimer = array();

		// Extract timer info
		$timerarray = explode(" ", $timer);

		$newtimer['id'] = $timerarray[0];

		$timerarray = explode(":", $timer);

		$typearray = explode(" ", $timerarray[0]);
		$newtimer['name'] = $timerarray[7];
		$newtimer['active'] = ($typearray[1] & 0x1)?"1":0;
		$newtimer['channumber'] = $timerarray[1];
		$newtimer['channame'] = vdrgetchanname($timerarray[1]);
		$newtimer['date'] = preg_replace("$-$", "/", $timerarray[2]);
		$newtimer['starttime'] = $timerarray[3];
		$newtimer['endtime'] = $timerarray[4];
		$newtimer['running'] = ($typearray[1] & 0x8)?1:0;

		if ( (substr($newtimer['name'], 0, strlen($username)+1)) != "$username" ."_" )
			continue;
		else
			$newtimer['name'] = substr($newtimer['name'], strlen($username)+1);

		$timerslist[] = $newtimer;
	}

	return $timerslist;
}

function vdrdeltimer($timer)
{
	addlog("VDR: vdrdeltimer(timer=" .$timer .")");

	$ret = array();

	$message = vdrsendcommand("DELT " .$timer);

	if (preg_match("/deleted/", $message))
	{
		$ret['status'] = "Ok";
		$ret['message'] = "Timer successfully deleted";
	}
	else
	{
		$ret['status'] = "Error";
		$ret['message'] = $message;
	}

	return $ret;
}

function vdrsettimer($prevtimer, $channum, $date, $stime, $etime, $desc, $active)
{
	global $username;

	addlog("VDR: vdrsettimer(prevtimer=" .$prevtimer .", channum=" .$channum .", date=" .$date .", stime=" .$stime .", etime=" .$etime .", desc=" .$desc .", active=" .$active .")");

	$ret = array();

	// Convert date to VDR format
	$date = preg_replace("$/$", "-", $date);

	// Add user name
	$desc = $username ."_" .$desc;

	if ($prevtimer == "")
		$command = "NEWT " .$active .":" .$channum .":" .$date .":" .$stime .":" .$etime .":99:99:" .$desc;
	else
		$command = "MODT " .$prevtimer ." " .$active .":" .$channum .":" .$date .":" .$stime .":" .$etime .":99:99:" .$desc;

	$message = vdrsendcommand($command);

	if (is_numeric(substr($message, 0, 1)))
	{
		$ret['status'] = "Ok";
		if ($prevtimer == "")
			$ret['message'] = "Timer created successfully";
		else
			$ret['message'] = "Timer edited successfully";
	}
	else
	{
		$ret['status'] = "Error";
		$ret['message'] = $message;
	}

	return $ret;
}

?>
