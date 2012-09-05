<?php

global $username;

session_start();

$username=$_SERVER['PHP_AUTH_USER'];
$password=$_SERVER['PHP_AUTH_PW'];
$remote_ip = $_SERVER['REMOTE_ADDR'];
$remote_host= gethostbyaddr($remote_ip);

if ($_SESSION['authorized'] == false)
{
  // checkup login and password
  if (isset($username) && isset($password))
  {
    if($password == sqlgetuserinfo("password",$username))
    {
      $_SESSION['authorized'] = true;
      $log = "user [" .$username ."] successfully identified! IP/HOST=[" .$remote_ip ."/" .$remote_host ."]";
      addlog($log);
      addmonitoringlog($log);

      sqlsetuserstat("last_connection", $username, date("Y/m/d H:i:s"));
      $num=sqlgetuserstat("num_connections", $username);
      $num++;
      sqlsetuserstat("num_connections", $username, $num);
    }
    else
    {
      $log = "Identification failed: " .$username ."/" .$password ."IP/HOST=[" .$remote_ip ."/" .$remote_host ."]";
      addlog($log);
      addmonitoringlog($log);
      header('WWW-Authenticate: Basic Realm="Login please"');
      header('HTTP/1.0 401 Unauthorized');
      echo "Incorrect user/password";
      exit;
    }
  }
  else
  {
    header('WWW-Authenticate: Basic Realm="Login please"');
    header('HTTP/1.0 401 Unauthorized');
  }
}

?>
