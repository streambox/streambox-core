<?php

function getGlobals()
{
	global $mediasource, $videosource, $audiosource;
	global $enablemediavideo, $enablemediaaudio, $epgmaxdays;

	$ret = array();
	if ($enablemediavideo)
		$ret['video_path'] = $videosource;
	else
		$ret['video_path'] = "";
	if ($enablemediaaudio)
		$ret['audio_path'] = $audiosource;
	else
		$ret['audio_path'] = "";
	$ret['epg_maxdays'] = $epgmaxdays;

	return json_encode($ret);
}

function getTvCat()
{
	$ret = array();
	$ret['categories'] = getcategories();

	return json_encode($ret);
}

function getFullChanList()
{
	$catlist = array();

	// Get all categories
	$categories = getcategories();

	// For all categories
	$count = count($categories);
	for ($i = 0; $i < $count; $i++)
	{
		$tmpcat = array();

		$tmpcat['name'] = $categories[$i]['name'];
		$tmpcat['channel'] = getchannels($tmpcat['name']);

		$catlist[] = $tmpcat;
	}

	$ret = array();
	$ret['category'] = $catlist;

	return json_encode($ret);
}

function getTvChan($cat)
{
	$ret = array();
	$ret['channel'] = getchannels($cat);

	return json_encode($ret);
}

function getChanInfo($channame)
{
	global $remoteapp;

	$ret = array();

	if ($remoteapp == "vdr")
		$ret['program'] = vdrgetchaninfo($channame);
	else
	{
		$info['name'] = $channame;
		$info['number'] = 0;
		list($info['now_time'], $info['now_title'], $info['now_desc']) = array('', '', '');
		list($info['next_time'], $info['next_title'], $info['next_desc']) = array('', '', '');

		$ret['program'] = $info;
	}

	return json_encode($ret);
}

function getRecInfo($rec)
{
	global $remoteapp;

	$ret = array();

	$info = array();

	if ($remoteapp == "vdr")
		list($info['channel'], $info['name'], $info['desc'], $info['recorded']) = vdrgetrecinfo($rec);

	$ret['program'] = $info;

	return json_encode($ret);
}

function getVidInfo($file)
{
	$ret = array();

	// Generate logo
	generatelogo('vid', $file, '../ram/temp-logo.png');
	
	$ret['program'] = mediagetinfostream($file);

	return json_encode($ret);
}

function startBroadcast($type, $url, $mode)
{
	$ret = array();

	$ret['session'] = substr(sessioncreate($type, $url, $mode), strlen("session"));

	return json_encode($ret); 
}

function stopBroadcast($session)
{
	$ret = array();

	if ($session == "all")
		$ret = sessiondelete($session);	
	else
		$ret = sessiondelete("session" .$session);

        return json_encode($ret);
}

function getStreamInfo($session)
{
	$ret = array();

	$info = sessiongetinfo("session" .$session);
	$info['session'] = substr($info['session'], strlen("session"));
	$ret['stream'] = $info;

	return json_encode($ret);
}

function getStreamStatus($session, $prevmsg)
{
	$ret = sessiongetstatus("session" .$session, $prevmsg);

	return json_encode($ret);
}

function getTimers()
{
	global $remoteapp;

	$ret = array();

	if ($remoteapp == "vdr")
		$ret['timer'] = vdrlisttimers();

	return json_encode($ret);
}

function delTimer($id)
{
	global $remoteapp;

	if ($remoteapp == "vdr")
		$ret = vdrdeltimer($id);

        return json_encode($ret);
}

function editTimer($id, $name, $active, $channumber, $date, $starttime, $endtime)
{
	global $remoteapp;

	if ($remoteapp == "vdr")
		$ret = vdrsettimer($id, $channumber, $date, $starttime, $endtime, $name, $active);

	return json_encode($ret);
}

function getRunningSessions()
{
	$ret = array();

	$ret['broadcast'] = sessiongetlist();

        return json_encode($ret);

}

function browseFolder($path, $type)
{
	$ret = array();

	$ret['list'] = filesgetlisting($path, $type);

	return json_encode($ret);
}

function streamAudio($path, $file)
{
	$ret = array();

	$ret['track'] = streammusic($path, $file);

	return json_encode($ret);

}

function getEpg($channel, $time, $day, $programs)
{
	global $remoteapp;

	$ret = array();

	if ($remoteapp == "vdr")
		$ret['category'] = vdrgetepg($channel, $time, $day, $programs, 0);

	return json_encode($ret);
}

function getEpgInfo($channel, $time, $day)
{
	global $remoteapp;

	$ret = array();

	if ($remoteapp == "vdr")
		$ret['program'] = vdrgetepg($channel, $time, $day, 1, 1);

	return json_encode($ret);
}

?>
