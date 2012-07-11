#!/usr/bin/php
<?php

# Author: Stefan Schulte
#
# The apihelper intends to be a little helper script used by the api cact_host
# provider to create/destroy/modify cacti hosts.
#
# Instead of modifying the cacti database directly by running some fancy SQL
# statements the api provider intends to use the API offered by cacti itself
# to be independent from any database structur. The API however is only
# available for PHP while the provider is written in ruby (like puppet itself)
#
# The apihelper acts as a command line tool, passing commandline arguments to
# the appropiate function calls to the cacti api. Please note that the
# apihelper doesn't implement any value validation whatsoever and leaves that
# for the API.
#
# The script does query the API for current cacti_hosts so the result can be
# used to implement prefetching in the puppet provider. To pass the results of
# a query back to the caller (the puppet provider in this case), the script
# will print the results on stdout in JSON format.
#
# NOTE:
# You *have* to modify a few paths here depending on how you installed php and
# cacti. The first thing you should check is the shebang line (the first line)
# if it matches your php executable. The second one are the include paths you
# need to adjust depending on where the api resides on the filesystem.
#
# The best way to check whether the script can find the cacti api is by
# running
#
#     % ./apihelper.php instances /tmp/apitest.txt
#     % json_pp < /tmp/apitest
#
# json_pp is used to pretty-print the file and is part of perl-core/JSON-PP.
# You can also use cat. The file /tmp/apitest.txt should show a list of all
# hosts with the corresponding puppet properties.

# You may have to edit these to match your cacti installation
require('/var/www/localhost/htdocs/cacti/include/global.php');
require_once($config["base_path"]."/lib/api_automation_tools.php");
require_once($config["base_path"]."/lib/api_device.php");

# The API method getHosts() will return an array of hashes for all hosts. The
# HOST_FIELDS array describes the fields we are interested in. Most fields can
# be directly mapped to puppet properties but not all. If we need a mapping
# between API names and puppet properties, a mapping has to be described in
# the MAP_API2PUPPET hash.
$HOST_FIELDS=array(
  'hostname',
  'snmp_version',
  'snmp_community',
  'snmp_username',
  'snmp_password',
  'snmp_auth_protocol',
  'snmp_priv_passphrase',
  'snmp_priv_protocol',
  'snmp_context',
  'snmp_port',
  'snmp_timeout',
  'max_oids',
  'disabled',
  'notes',
  'availability_method',
  'ping_method',
  'ping_port',
  'ping_timeout',
  'ping_retries'
);

# The API sometimes has slightly different fieldnames than the name of the
# corresponding puppet property. Because this helper script intends to print
# the names that are used in the puppet type the MAP_API2PUPPET works as a
# translation table.
$MAP_API2PUPPET=array(
  'snmp_password' => 'snmp_auth_password',
  'snmp_priv_passphrase' => 'snmp_priv_password',
  'max_oids' => 'snmp_max_oids',
);

function instances(){
  global $HOST_FIELDS, $MAP_API2PUPPET;

  $result = array();

  # The getHosts() call does return a list of hosts and is basically just a
  # wrapper around `select * from host`
  $hostTemplates = getHostTemplates();

  foreach (getHosts() as $host) {
    $name = $host['description'];
    $result[$name] = array();
    $result[$name]['host_template'] = $hostTemplates[$host['host_template_id']];

    foreach($HOST_FIELDS as $key) {
      if (isset($MAP_API2PUPPET[$key])) {
        $result[$name][$MAP_API2PUPPET[$key]] = $host[$key];
      }
      else {
        $result[$name][$key] = $host[$key];
      }
    }
  }
  return $result;
}

function save($host, $attributes) {
  # We first need the id of our host if the host is already present.
  # Unfortunately the API does not have a nice function for that already
  # so we cheat a little bit here
  $id = db_fetch_cell("select id from host where description = '$host' LIMIT 1");
  if (empty($id)) {
    echo "Host $host is currently absent\n";
  }
  else {
    echo "Host $host is currently present and will be updated (id=$id)\n";
  }

  $hostTemplateId;
  if(isset($attributes['host_template'])) {
    foreach(getHostTemplates() as  $templateid => $name) {
      if ($name == $attributes['host_template']) {
        $hostTemplateId = $templateid;
      }
    }
    if(isset($hostTemplateId)) {
      echo "Hosttemplate ".$attributes['host_template']." found with id $hostTemplateId\n";
    }
    else{
      echo "Hosttemplate ".$attributes['host_template']." was not found. The API may reset the hosttemplate for host $host\n";
    }
  }

  $default_community = NULL;
  if (empty($attributes['version']) or $attributes['version'] == '1' or $attributes['version'] == '2') {
    $default_community = 'public';
  }

  $host_id = api_device_save(
    empty($id) ? NULL : $id,
    empty($hostTemplateId) ? 0 : $hostTemplateId,
    $host,
    empty($attributes['hostname'])            ? NULL     : $attributes['hostname'],
    empty($attributes['snmp_community'])      ? $default_community : $attributes['snmp_community'],
    empty($attributes['snmp_version'])        ? '1'      : $attributes['snmp_version'],
    empty($attributes['snmp_username'])       ? NULL     : $attributes['snmp_username'],
    empty($attributes['snmp_auth_password'])  ? NULL     : $attributes['snmp_auth_password'],
    empty($attributes['snmp_port'])           ? '161'    : $attributes['snmp_port'],
    empty($attributes['snmp_timeout'])        ? '500'    : $attributes['snmp_timeout'],
    empty($attributes['disabled'])            ? NULL     : $attributes['disabled'],
    empty($attributes['availability_method']) ? '2'      : $attributes['availability_method'],
    empty($attributes['ping_method'])         ? '2'      : $attributes['ping_method'],
    empty($attributes['ping_port'])           ? NULL     : $attributes['ping_port'],
    empty($attributes['ping_timeout'])        ? '400'    : $attributes['ping_timeout'],
    empty($attributes['ping_retries'])        ? NULL     : $attributes['ping_retries'],
    empty($attributes['notes'])               ? NULL     : $attributes['notes'],
    empty($attributes['snmp_auth_protocol'])  ? NULL     : $attributes['snmp_auth_protocol'],
    empty($attributes['snmp_priv_password'])  ? NULL     : $attributes['snmp_priv_password'],
    empty($attributes['snmp_priv_protocol'])  ? NULL     : $attributes['snmp_priv_protocol'],
    empty($attributes['snmp_context'])        ? NULL     : $attributes['snmp_context'],
    empty($attributes['snmp_max_oids'])       ? '10'     : $attributes['snmp_max_oids'],
    NULL
  );
  if($host_id == 0) {
    # try to find out what went wrong
    if (isset($_SESSION['sess_error_fields'])) {
      foreach($_SESSION['sess_error_fields'] as $key => $field) {
        if(isset($_SESSION['sess_field_values'][$key])) {
          $value = $_SESSION['sess_field_values'][$key];
          echo "api_device_save failed: Passed invalid value as $field: $value\n";
        }
        echo "api_device_save failed: Passed invalid value as $field\n";
      }
    }
  }
  return $host_id;
}

$parms = $_SERVER["argv"];
array_shift($parms);

if (sizeof($parms)) {
  switch($parms[0]) {
  case 'instances':
    if(isset($parms[1])) {
      $filename = $parms[1];
      $fh = fopen($filename, 'w') or die("Can't open file $filename");
      fwrite($fh, json_encode(instances())) or die("Can't write to file $filename");
      fclose($fh);
      echo "Instances written to $filename\n";
    }
    else {
      echo "You have to pass a filename as a second parameter\n";
      echo "Usage: ".__FILE__." instances <filename>\n";
      exit(1);
    }
    break;
  case 'save':
    if(!isset($parms[1]) or !isset($parms[2])) {
      echo "You must pass the cacti host and a filename with the hostattributes in json format\n";
      echo "Usage: ".__FILE__." save <hostname> <json_filename>\n";
      exit(1);
    }

    $cacti_host = $parms[1];
    $json = file_get_contents($parms[2]);
    if($json == FALSE) {
      echo "Unable to read file ".$parms[2]."\n";
      exit(1);
    }
    $attributes = json_decode($json, true);
    if (!isset($attributes)) {
      echo "Unable to decode json\n";
      exit(1);
    }

    $hostid = save($cacti_host, $attributes);
    if ($hostid != 0) {
      exit(0);
    }
    else {
      echo "Unable to save host $cacti_host\n";
      exit(1);
    }
    break;
  case 'destroy':
    echo "Destroy not yet implemented\n";
    exit(1);
    break;
  default:
    echo "ERROR: Ivalid argument ".$parms[0]."\n";
    exit(1);
  }
}
else {
  echo "Usage: apihelper.php instances\n";
}

?>
