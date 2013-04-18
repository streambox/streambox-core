<?php
	// Timezone
	date_default_timezone_set("Europe/Paris");

	// Enable/disable features
	$enablemediavideo=1;		// Video files streaming
	$enablemediaaudio=1;		// Audio files streaming

	// Debug mode
	$debug=0;			// Debug all action 
	$debugfile="/tmp/istreamdev.log"; // Debug file
	$ffmpegdebug=0;			// Debug ffmpeg
	$ffmpegdebugfile="/tmp/istreamdev-ffmpeg.log"; // FFmpeg debug file
	$monitoring=0;
	$monitoringfile="/tmp/istreamdev-monitoring.log"; // Monitor all streaming requests

	// Http configuration
	$httppath='/istreamdev/';	// Absolute path to the index.php file. Don't put http://yourdomain !!

	// SQL database
	$sqlserver="127.0.0.1";		// SQL server IP
	$sqluser="user";		// SQL user
	$sqlpassword="password";	// SQL password
	$sqldatabase="streambox";	// SQL database name

	// VDR configuration
	$vdrchannels='/etc/vdr/channels.conf';			// VDR channel list
	$svdrpport=2001;					// SVDRP port
	$svdrpip='127.0.0.1';					// SVDRP ip
	$vdrstreamdev='http://127.0.0.1:3000/TS/';		// VDR streamdev URL
	$vdrrecpath='/video/';					// VDR recording directory
	$vdrepgmaxdays=10;					// Number of days to get from EPG

	// Media configuration
	$videotypes='avi mkv ts mov mp4 wmv flv mpg mpeg mpeg2 mpv ';	// Supported video extensions (must finish with a space)
	$audiotypes='mp3 aac wav ';					// Supported audio extensions
	$videosource='/mnt/media/movies/';				// Video files directory
	$audiosource='/mnt/media/music/';				// Audio files directory

        /////////////////////////////////////////////////////////////////
        // Encoding: define apaptive streaming engcoding
        //                      Name            Video   Audio   Resolution      Framerate
        $qualities=array(       '1'     =>      '110k   48k     416x234         15',
                                '2'     =>      '200k   48k     416x234         25',
                                '3'     =>      '400k   48k     416x234         25',
                                '4'     =>      '600k   48k     640x360         25',
                                '5'     =>      '900k   48k     720x408         25'
                        );
	//////////////////////////////////////////////////////////////////

	$maxencodingprocesses=10;				// Max simultaneous encoding processes
	$debugadaptive=0;					// Adaptive streaming monitoring directly on the picture

	// Misc
	$ffmpegpath='/usr/bin/ffmpeg';		//path to ffmpeg binary
	$segmenterpath='/usr/bin/segmenter';	//path to segmenter binary
?>
