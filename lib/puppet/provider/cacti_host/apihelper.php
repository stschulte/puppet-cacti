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
#     ./apihelper.php instances
#
# on the commandline. You should see a dump of your cacti hosts as JSON.

# You may have to edit these to match your cacti installation
include('/var/www/localhost/htdocs/cacti/include/global.php');
include_once($config["base_path"]."/lib/api_automation_tools.php");

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

$parms = $_SERVER["argv"];
array_shift($parms);

if (sizeof($parms)) {
  switch($parms[0]) {
  case 'instances':
    echo json_encode(instances());
#    echo yaml_emit(instances());
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
