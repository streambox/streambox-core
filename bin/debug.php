<?php
function addlog($log)
{
	global $debug, $debugfile, $username;

	if (!$debug)
		return ;

	$newlog = date("[Y/m/d H:i:s]  ") ."<" .$username ."> " .$log ."\n";

	$debughandle = fopen($debugfile, 'a');
	if (!$debughandle)
		return;
	fwrite($debughandle, $newlog);

	fclose($debughandle);
}

function addmonitoringlog($log)
{
  global $monitoring, $monitoringfile,$monitoringemail;

  if (!$monitoring)
  return;

  if(isset($monitoringemail))
    mail($monitoringemail, $log, "", "From: ".$monitoringemail );

  $newlog = date("Y/m/d H:i:s -> ") .$log ."\n";

  $debughandle = fopen($monitoringfile, 'a');
  if (!$debughandle)
  return;
  fwrite($debughandle, $newlog);

  fclose($debughandle);
}


?>
