<?php
/*
mpower
*/

$no_http_headers = true;
$port = 7635;

# Replace these values with the login for your mPower device
$username = "admin";
$password = "ubnt";

$hostname       = $_SERVER["argv"][1];  # hostname or ip address
$action         = $_SERVER["argv"][2];  # one of: index, query, get
if (count($_SERVER["argv"]) > 3) {
	$field      = $_SERVER["argv"][3];  # one of: port, label, output, power, energy, enabled, current, voltage, powerfactor, relay, lock
}
if (count($_SERVER["argv"]) > 4) {
	$index      = $_SERVER["argv"][4];  
}

function getsensordata($hostname, $username, $password) {
  $cache_file = '/tmp/cacti-mpower-' . $hostname . '.cache';
  
  if(file_exists($cache_file) && (filemtime($cache_file) > (time() - 30))) {
	# cache file exists and is recent. use that instead of querying via ssh
	# (this helps to lower server load in case cacti does repeated queries in a short period of time)
	$response = file_get_contents($cache_file);
  } else {
	# retrieve sensor status from the remote server via SSH
	$connection = ssh2_connect($hostname, 22);
	ssh2_auth_password($connection, $username, $password);
	$stream = ssh2_exec($connection, '/sbin/cgi /usr/www/mfi/sensors.cgi');
	stream_set_blocking($stream, true);
	$response = stream_get_contents($stream);
	@fclose($stream);
  
	# strip header from the response
	$response = str_replace('Content-type:  application/json', '', $response);
  
	# save to cache file
	file_put_contents($cache_file, $response, LOCK_EX);
  }
  
  # parse JSON
  $json = json_decode($response, true);
  
  # extract just the piece we need
  $sensors = $json["sensors"];
  
  return $sensors;
}

function printindex($sensors){
  foreach ($sensors as $i => $val) {
    print($sensors[$i]["port"])."\n";
  }
}

function query($sensors,$field){
  foreach ($sensors as $i => $val) {
    print($sensors[$i]["port"]).":".$sensors[$i][$field]."\n";
  }
}

function get($sensors,$field,$index){
  foreach ($sensors as $i => $val) {
    if ($sensors[$i]["port"] == $index) {
      print($sensors[$i][$field]."\n");
    }
  }
}

# 

switch ($action){
  case "index" : printindex(getsensordata($hostname, $username, $password)); break;
  case "query" : query(getsensordata($hostname, $username, $password), $field); break;
  case "get"   : get(getsensordata($hostname, $username, $password), $field, $index); break;
}
?>
