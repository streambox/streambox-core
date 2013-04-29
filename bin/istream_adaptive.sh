#!/bin/bash

##########################
# Code, don't modify
##########################

STREAM=`echo "$1" | sed 's/ /%20/g'`
SEGWIN=$2		# Amount of Segments to produce
FFPATH=$3
SESSION=$4
FFMPEGLOG=$5
NBQUALITIES=$6
CMDLINEARGS=("$@")

SEGDUR=10               # Length of Segments produced (between 10 and 30)


function get_quality
{
	qualityid=$1
	qparamindex=$((qualityid+5))
	qualityname=$2

	qualities="${CMDLINEARGS[$qparamindex]}"

	case "$qualityname" in
	"VRATE")
		echo $qualities | awk '{ print $1}'
		;;
	"ARATE")
		echo $qualities | awk '{ print $2}'
		;;
	"BW")
		vrate=`echo $qualities | awk '{ print $1}'`
		vrate=${vrate:0:-1}
		arate=`echo $qualities | awk '{ print $2}'`
		arate=${arate:0:-1}
		echo $(((arate+vrate)*961 + 35900))
		;;
	"XY")
		echo $qualities | awk '{ print $3}'
		;;
	"FRAMERATE")
		echo $qualities | awk '{ print $4}'		
		;;
	"GOP_LENGTH")
		framerate=`echo $qualities | awk '{ print $4}'`
		echo $((framerate*$SEGDUR))
		;;
        *)
		echo "0"
		;;
	esac
}

function get_stream_name
{
	streamid="$1"

	echo "stream_`get_quality $streamid VRATE`_`get_quality $streamid ARATE`_`get_quality $streamid XY`_%03d.ts"
}

function get_playlist_name
{
	streamid="$1"

	echo "stream_`get_quality $streamid VRATE`_`get_quality $streamid ARATE`_`get_quality $streamid XY`.m3u8"
}

CURDIR=`pwd`

if [ $# -eq 0 ]
then
echo "Format is : ./istream_adaptive.sh source_url nb_segments ffmpeg_path session_name log_file_name nb_qualities quality_params"
exit 1
fi

# Log
if [ -z "$FFMPEGLOG" ]
then
	FFMPEGLOG="/dev/null"
fi

# Check that the session dir exists
if [ ! -e ../ram/sessions/$SESSION ]
then
	exit;
fi

# Go into session
cd ../ram/sessions/$SESSION

#create master playlist
echo "#EXTM3U" > stream.m3u8
for streamid in `seq 1 $NBQUALITIES`
do
	echo "#EXT-X-STREAM-INF:PROGRAM-ID=1,BANDWIDTH=`get_quality $streamid BW`,RESOLUTION=`get_quality $streamid XY`" >> stream.m3u8
	echo "`get_playlist_name $streamid`" >> stream.m3u8
done

COMMON_OPTION="-map 0:v:0 -map 0:a:0 -filter:v yadif -f mpegts -async 2 -threads 0 "
AUDIO_OPTION="-acodec libaacplus -ac 2 -b:a "
VIDEO_OPTION="-vcodec libx264 -flags +loop+mv4 -cmp 256 -partitions +parti4x4+parti8x8+partp4x4+partp8x8+partb8x8 -me_method hex -subq 7 -trellis 1 -refs 5 -coder 0 -me_range 16 -i_qfactor 0.71 -rc_eq 'blurCplx^(1-qComp)' -qcomp 0.6 -qmin 10 -qmax 51 -qdiff 4 -level 30 -sc_threshold 0 "

# Start ffmpeg
echo start > $FFMPEGLOG
FFMPEG_QUALITIES=""
for ffid in `seq 1 $NBQUALITIES`
do
	FFMPEG_QUALITIES="${FFMPEG_QUALITIES} $COMMON_OPTION  $AUDIO_OPTION `get_quality $ffid ARATE` -s `get_quality $ffid XY` $VIDEO_OPTION \
				-keyint_min `get_quality $ffid FRAMERATE` -r `get_quality $ffid FRAMERATE` -g `get_quality $ffid GOP_LENGTH` -b:v `get_quality $ffid VRATE` -bt `get_quality $ffid VRATE` -maxrate `get_quality $ffid VRATE` \
				-bufsize `get_quality $ffid VRATE` -f ssegment -segment_list `get_playlist_name $ffid` -segment_time $SEGDUR `get_stream_name $ffid`"

# add specific option for live playlist
if [ "${STREAM:0:4}" == "http" ]
then
   FFMPEG_QUALITIES="${FFMPEG_QUALITIES} -segment_list_flags +live -segment_list_size $SEGWIN"
fi
done

echo $FFMPEG_QUALITIES 2>$FFMPEGLOG

if [ "${STREAM:0:4}" == "http" ]
then
	$FFPATH -i "$STREAM" -y $FFMPEG_QUALITIES 2>$FFMPEGLOG &
else
	$CURDIR/cat_recording.sh "$STREAM" | $FFPATH -i - -y $FFMPEG_QUALITIES 2>$FFMPEGLOG &
fi

sleep 1

# Store ffmpeg pid
FFPID=$!
if [ ! -z "$FFPID" ]
then
	SPID=`\ps ax --format "%p %c %P" | grep "$FFPID ffmpeg" | awk {'print $1'}`;
	if [ ! -z "$SPID" ]
	then
		echo $SPID > ./ffmpeg.pid
	fi
fi
