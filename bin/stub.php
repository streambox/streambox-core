<?php


function stubgetchaninfo($channame)
{
	global $remoteapp;

	$ret = array();

	if ($remoteapp == "vdr")
		$ret = vdrgetchaninfo($channame);
	else if ($remoteapp == "tvheadend")
        {
		$info['name'] = $channame;
		$info['number'] = 0;
		list($info['now_time'], $info['now_title'], $info['now_desc']) = array('', '', '');
		list($info['next_time'], $info['next_title'], $info['next_desc']) = array('', '', '');

		$ret = $info;
	}

	return $ret;

}

?>
