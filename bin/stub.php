<?php


function stubgetchaninfo($channame)
{
	global $remoteapp;

	if ($remoteapp == "vdr")
		return vdrgetchaninfo($channame);
	else if ($remoteapp == "tvheadend")
        {
		$info['name'] = $channame;
		$info['number'] = 0;
		list($info['now_time'], $info['now_title'], $info['now_desc']) = array('', '', '');
		list($info['next_time'], $info['next_title'], $info['next_desc']) = array('', '', '');

		return $info;
	}
}

function stubgetchannum($chan)
{
	global $remoteapp;

	if ($remoteapp == "vdr")
		return vdrgetchannum($channame);
	else if ($remoteapp == "tvheadend")
		return 0;
}

function stubgetepgat($channum, $at)
{
	global $remoteapp;

	if ($remoteapp == "vdr")
		return vdrgetepgat($channum, $at);
	else if ($remoteapp == "tvheadend")
		return 0;
}

function stubgetrecinfo($rec)
{
	global $remoteapp;

	if ($remoteapp == "vdr")
		return vdrgetrecinfo($rec);
	else if ($remoteapp == "tvheadend")
		return array();
}

function stublisttimers()
{
        global $remoteapp;

        if ($remoteapp == "vdr")
                return vdrlisttimers();
        else if ($remoteapp == "tvheadend")
                return array();
}

function stubdeltimer($timer)
{
        global $remoteapp;

        if ($remoteapp == "vdr")
                return vdrdelimer($timer);
        else if ($remoteapp == "tvheadend")
	{
		$ret['status'] = "Error";
                $ret['message'] = "Not implemented";

                return $ret;
	}
}

function stubsettimer($prevtimer, $channum, $date, $stime, $etime, $desc, $active)
{
        global $remoteapp;

        if ($remoteapp == "vdr")
                return vdrsettimer($prevtimer, $channum, $date, $stime, $etime, $desc, $active);
        else if ($remoteapp == "tvheadend")
        {
                $ret['status'] = "Error";
                $ret['message'] = "Not implemented";

                return $ret;
        }
}

function stubgetepg($channel, $time, $day, $programs, $extended)
{
        global $remoteapp;

        if ($remoteapp == "vdr")
                return vdrgetepg($channel, $time, $day, $programs, $extended);
        else if ($remoteapp == "tvheadend")
                return array();
}

?>

