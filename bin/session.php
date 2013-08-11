<?php
function sessioncreate($type, $url, $mode)
{
	global $httppath, $ffmpegpath, $maxencodingprocesses, $ffmpegdebug, $ffmpegdebugfile, $encodingscript;
	global $username, $streamingurl, $recpath;
	global $qualities;

	$log = "user [" .$username ."]"." Creating a new session for \"" .$url ."\" (" .$type .", " .$mode .")";
	addlog($log);
	addmonitoringlog($log);

	// Check url
	if (!isurlvalid($url, $type))
		return "";

        // Extract $channame if possible
        switch ($type)
        {
                case 'tv':
                        $channame = $url;
                        $channum = vdrgetchannum($channame);
                        break;
                case 'rec':
                        list($channame, $title, $desc, $recorded) = vdrgetrecinfo($url);
                        break;
                default:
                        $channame = "";
                        break;
        }

	// Trying to reuse an existing session
	$dir_handle = @opendir('../ram/sessions/');
	if ($dir_handle)
	{
		while ($session = readdir($dir_handle))
		{
                        if($session == "." || $session == ".." || $session == 'lost+found')
                                continue;

                        if (!is_dir('../ram/sessions/' .$session))
                                continue;

                        // Get info
                        list($rtype, $rmode, $rurl, $rchanname) = readinfostream($session);
			if (($type == $rtype) && ($mode == $rmode) && ($channame == $rchanname))
			{
				addlog("Reusing existing session: " .$session);
				goto create_link;
			}
		}
	}


	// Check that the max number of session is not reached yet
	$nbencprocess = exec("find ../ram/sessions/ -name ffmpeg.pid | wc | awk '{ print $1 }'");
	if ($nbencprocess >= $maxencodingprocesses)
	{
	  $log= "Error: Cannot create sesssion, too much sessions already encoding";
		addlog($log);
		addmonitoringlog($log);
		return $log;
	}

	// Get a free session
	$i=0;
	for ($i=0; $i<1000; $i++)
	{
		$session = "session" .$i;
		if (!file_exists('../ram/sessions/' .$session))
			break;
	}

	if ($i == 1000)
	{
	  $log = "Error: Cannot find a new session name";
		addlog($log);
		addmonitoringlog($log);
		return $log;
	}

	// Create session
	addlog("Creating new session dir ram/sessions/" .$session);
	exec('mkdir ../ram/sessions/' .$session);

	// Create logo
        if ($type == 'vid')
                generatelogo($type, $url, '../ram/sessions/' .$session .'/thumb.png');
        else
                generatelogo($type, $channame, '../ram/sessions/' .$session .'/thumb.png');

	// FFMPEG debug
	if ($ffmpegdebug)
		$ffdbg = $ffmpegdebugfile;
	else
		$ffdbg = "";

	// Start encoding
	$url = str_replace("\\'", "'", $url);
	$encodingscript="./istream_adaptive.sh";
	switch ($type)
	{
		case 'tv':
			$scripturl = $streamingurl .$url;
			$scriptnbsegments = 5;
			break;
		case 'rec':
			$scripturl = $recpath .$url;
			$scriptnbsegments = 1260;
			break;
		case 'vid':
			$scripturl = $url;
			$scriptnbsegments = 1260;
			break;
	}
	// Generate command

	// Script  name
	$cmd  = $encodingscript ." ";
	// URL to play
	$cmd .= "\"" .$scripturl ."\" ";
	// Nb of segments
	$cmd .= $scriptnbsegments ." ";
	// FFmpeg path
	$cmd .= $ffmpegpath ." ";
	// Session name
	$cmd .= $session ." ";
	// Debug
	$cmd .= $ffdbg ." ";
	// Qualities
	$cmd .= count($qualities) ." ";
	foreach ($qualities as $qname => $qparams)
		$cmd .= "\" " .$qparams ."\" ";
	// Fork
	$cmd .= ">/dev/null 2>&1 &";

	addlog("Sending encoding command: " .$cmd);

	$cmd = str_replace('%', '%%', $cmd);
	exec ($cmd);

	// Give the time to the scrip to create pids
	exec ("sleep 2");

	// Write streaminfo
	writeinfostream($session, $type, $mode, $url, $channame);

create_link:
	// Create link
	exec ('mkdir ../ram/' .$username .'; ln -fs ../sessions/' .$session .' ../ram/' .$username .'/');

	sqlsetuserstat("last_channel", $username, $channame);

	return $session;
}

function sessiondelete($session)
{
	global $username;

	$ret = array();

	if ($session == 'all')
	{
		$dir_handle = @opendir('../ram/' .$username);
		if ($dir_handle)
		{
			while ($session = readdir($dir_handle))
			{
				if($session == "." || $session == ".." || $session == 'lost+found')
					continue;

				if (!is_dir('../ram/sessions/' .$session))
					continue;

				// Get info
				list($type, $mode, $url, $channame) = readinfostream($session);

				if ($type != "none")
					sessiondeletesingle($session);
			}
		}
	}
	else
		sessiondeletesingle($session);

	$ret['status'] = "ok";
	$ret['message'] = "Successfully stopped broadcast";

	return $ret;

}

function sessiongetinfo($session)
{
	$info = array();

	addlog("Getting info for session " .$session);

	// Get some info
	list($type, $mode, $url, $channame) = readinfostream($session);
	
	// Fill common info
	$info['session'] = $session;
	$info['type'] = $type;
	$info['mode'] = $mode;

	// Get info
	$getid3 = new getID3;
	$fileinfo = $getid3->analyze('../ram/sessions/' .$session .'/thumb.png');
	$info['thumbwidth'] = $fileinfo['video']['resolution_x'];
	$info['thumbheight'] = $fileinfo['video']['resolution_y']; 

	// Type info
	switch ($type)
	{
		case 'tv':
			$info['name'] = $channame;
			$channum = vdrgetchannum($channame);
			list($date, $info['now_time'], $info['now_title'], $info['now_desc']) = vdrgetepgat($channum, "now");
			list($date, $info['next_time'], $info['next_title'], $info['next_desc']) = vdrgetepgat($channum, "next");
			break;
		case 'rec':
			$info['channel'] = $channame;
			list($channame, $info['name'], $info['desc'], $info['recorded']) = vdrgetrecinfo($url);
			break;
		case 'vid':
			$infovid = mediagetinfostream($url);
			$info['name'] = basename($url);
			$info['desc'] = $infovid['desc'];
			$info['duration'] = $infovid['duration'];
			$info['format'] = $infovid['format'];
			$info['video'] = $infovid['video'];
			$info['audio'] = $infovid['audio'];
			$info['resolution'] = $infovid['resolution'];
			break;
	}

	return $info;
}


function sessiondeletesingle($session)
{
	global $username;
  $log = "user [" .$username ."]"." Deleting session " .$session;
	addlog($log);
	addmonitoringlog($log);

	// Remove link
	exec("rm ../ram/" .$username ."/" .$session);

	// Check if the session is still used
	exec('find ../ram/ -name "' .$session .'" | grep -v sessions', $output);
        if(count($output) > 0)
        {
                addlog("Session " .$session ." in use by another user");
                return;
        }

	$ram = "../ram/sessions/" .$session ."/";
	$cmd = "";

	// First kill ffmpeg
	$ffmpegpid=is_pid_running($ram ."ffmpeg.pid");
	if ( $ffmpegpid != 0 )
		$cmd .= " kill -9 " .$ffmpegpid ."; rm " .$ram ."ffmpeg.pid; ";
	addlog("Sending session kill command: " .$cmd);

	$cmd .= "rm -rf " .$ram;
	exec ($cmd);
}

function getstreamingstatus($session)
{
	global $maxencodingprocesses, $httppath, $qualities;

	$status = array();

	$path = '../ram/sessions/' .$session;

	// Check that session exists
	if (substr($session, 7, 1) == ":")
	{
		$status['status'] = "error";
		$status['message'] = "<b>Error:" .substr($session,5) ."</b>";
	}
	else
	{
		// Get stream info
		list($type, $mode, $url, $channame) = readinfostream($session);

    // it is better to check for playlist availability as they are created after first complete ts fragment is available
    // in case of adaptive streaming, 1 playlist is created automatically
		if (count(glob($path . '/*.m3u8')) <= count($qualities))
		{
			if (!is_pid_running($path .'/ffmpeg.pid'))
			{
				$status['status'] = "error";
				$status['message'] = "<b>Error: streaming could not start correctly</b>";
			}
			else
			{
				$status['status'] = "wait";
				switch ($type)
				{
					case 'tv':
						$status['message'] = "<b>Live: requesting " .$channame ."</b>";
						break;
					case 'rec':
						$status['message'] = "<b>Rec: requesting " .$channame ."</b>";
						break;
					case 'vid':
						$status['message'] = "<b>Vid: requesting " .$url ."</b>";
						break;
				}
			}

			$status['message'] .= "<br>";

			$status['message'] .= "<br>  Starting encoding, please wait... (F:";
			if (is_pid_running($path .'/ffmpeg.pid'))
				$status['message'] .= "Y";
			else
				$status['message'] .= "N";
			$status['message'] .= ")";
		}
		else
		{
			$status['status'] = "ready";

			$status['message'] = "<b>Broadcast ready</b><br>";

			$status['message'] .= "<br>  * Quality: <i>" .$mode ."</i>";
			$status['message'] .= "<br>  * Status: ";
			if (is_pid_running($path .'/ffmpeg.pid'))
				$status['message'] .= "<i>live streaming</i>";
			else
				$status['message'] .= "<i>fully encoded</i>";

			$status['url'] = $httppath ."ram/sessions/" .$session ."/stream.m3u8";

		}
	}

	return $status;
}

function sessiongetstatus($session, $prevmsg)
{
	$time = time();

	// Check if we need to timeout on the sesssion creation
	$checkstart = preg_match("/requesting/", $prevmsg);
	
	while((time() - $time) < 29)
	{

		// Get current status
		$status = getstreamingstatus($session);
	
		// Alway return ready
		if ($status['status'] == "ready")
		{
			addlog("Returning status: " .$status['message']);
			return $status;
		}

		// Status change
		if ($status['message'] != $prevmsg)
		{
			addlog("Returning status: " .$status['message']);
			return $status;
		}

		// Check session creation timeout
		if ($checkstart && ((time() - $time) >= 15))
		{
			$status['status'] = "error";
			$status['message'] = "Error: session could not start";

			$status['message'] .= "<br>";

			$status['message'] .= "<br>  * FFmpeg: ";

			if (is_pid_running('../ram/sessions/' .$session .'/ffmpeg.pid'))
				$status['message'] .= "<i>running</i>";
			else
				$status['message'] .= "<i>stopped</i>";

			addlog("Returning status: " .$status['message']);
			return $status;
		}

		usleep(10000);
	}

	/* Time out */
	$status['status'] = "wait";
	$status['message'] = $prevmsg;

	addlog("Returning status: " .$status['message']);
	return $status;
}

function sessiongetlist()
{
	global $username;

	$sessions = array();

	addlog("Listing sessions for " .$username);

	$dir_handle = @opendir('../ram/' .$username .'/');
	if ($dir_handle)
	{
		while ($session = readdir($dir_handle))
		{
			if($session == "." || $session == ".." || $session == 'lost+found')
				continue;

			if (!is_dir('../ram/' .$username .'/' .$session))
				continue;

			// Get info
			list($type, $mode, $url, $channame) = readinfostream($session);
			if ($type == "none")
				continue;

			// Get status
			$status = getstreamingstatus($session);

			$newsession = array();
			$newsession['session'] = substr($session, strlen("session"));
			$newsession['type'] = $type;
			if ($type == "vid")
				$newsession['name'] = basename($url);
			else
				$newsession['name'] = $channame;

			if ($status['status'] == "error")
				$newsession['name'] = "Error: " .$newsession['name'];

			// Check if encoding
			if (is_pid_running('../ram/sessions/' .$session .'/ffmpeg.pid') && ($status['status'] != "error"))
				$newsession['encoding'] = 1;
			else
				$newsession['encoding'] = 0;

			$sessions[] = $newsession;

		}
	}

	return $sessions;
}

function streammusic($path, $file)
{
	global $httppath;

	addlog("Streaming music from path \"" .$path ."\"");

	if (!isurlvalid($path, "media"))
		return array();

	$files = array();

	// Create all symlinks
	exec('mkdir ../playlist');
        exec('rm ../playlist/*');
        exec('ln -s ' .addcslashes(quotemeta($path), " &'") .'/* ../playlist');

	// Generate files

	// Get listing
	$filelisting = filesgetlisting($path);

	$addfiles = 0;

	foreach ($filelisting as $f)
	{
		if ($f['type'] != 'audio')
			continue;

		if ($f['name'] == $file)
			$addfiles = 1;

		if ($addfiles)
		{
			$newfile = array();
			$newfile['file'] = $httppath ."playlist/" . $f['name'];
			$files[] = $newfile;
		}
	}

	return $files;
}

?>
