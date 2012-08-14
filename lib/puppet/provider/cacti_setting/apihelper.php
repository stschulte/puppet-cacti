#!/usr/bin/php
<?php

# Author: Stefan Schulte
#
# This is a helper script used by the provider "api" of the custom type
# "cacti_setting". It can also be used as a stand-alone program to query
# cacti values.
#
# The api provider tries to use the cacti API whenever possible so the
# provider does not have to care about a specific database backend nor does it
# have to know the username and password of the database backend as all
# connections are handled through cacti functions.
#
# There is one problem: Cacti and its API is written in PHP while the puppet
# plugin is written in ruby (like the rest of puppet). That's why the
# apihelper.php script is needed:
#
# 1) When puppet wants to query a cacti value it does so by calling the
#    apihelper.php script and the apihelper.php script will return information
#    about cacti values in json format that can be parsed by the api provider
#    that is written in ruby
# 2) When puppet wants to set a cacti_setting it does also call the
#    apihelper.php script feeding it with the option name and the desired
#    value
#
# NOTE:
#
# You *have* to modify a few paths here depending on how you installed php and
# cacti. The first thing you should check is the shebang line (the first line)
# if it matches your php executable.
# The second thing you need to modify are the include paths below that have to
# match your cacti installation path.
#
# The best way to check whether the script can find the cacti api is by
# running
#
#     % ./apihelper.php instances
#
# You should see all your cacti settings as JSON output on STDOUT now. If
# cacti logs a lot of warnings on STDOUT (older cacti versions can cause a
# lot of deprecations warnings if used together with a recent php version)
# you can also ask the apihelper script to write the JSON output to a file:
#
#     % ./apihelper.php instances /tmp/cacti_test.txt
#     % json_pp < /tmp/cacti_test.txt (requires perl-core/JSON-PP)
#
#
# You may have to edit these to match your cacti installation
require('/var/www/localhost/htdocs/cacti/include/global.php');
require_once($config["base_path"]."/lib/functions.php");

/* instances - query all cacti settings that are present in the database
     and return the values as a hash
     @returns - cacti settings as a hash of the form $hash[$name]=$value */
function instances() {
  $result = array();

  $resultset = db_fetch_assoc('select name, value from settings order by name');
  foreach($resultset as $record) {
    $result[$record['name']] = $record['value'];
  }
  if (empty($result)) $result = (object) null;
  return $result;
}


/* json_pp - Pretty prints json data (useful when writing to stdout)
  @arg $input - the unformated json string as returned by json_encode
  @returns - the json string with proper indention */
function json_pp($input) {
  $output = "";
  $inside_string = false;
  $indent = 0;
  $next_is_escaped = false;


  for($i = 0; $i < strlen($input); $i++) {
    $char = $input[$i];

    if(!$next_is_escaped and $char == "\\") {
      $next_is_escaped = true;
      $output .= $char;
    }
    else {
      if($next_is_escaped) {
        $output .= $char;
        $next_is_escaped = false;
      }
      elseif($inside_string and $char != '"') {
        $output .= $char;
      }
      else {
        switch($char) {
        case '{':
        case '[':
          $indent += 4;
          $output .= $char."\n".(str_repeat(" ",$indent));
          break;
        case '}':
        case ']':
          $indent -= 4;
          $output .= "\n".(str_repeat(" ",$indent)).$char;
          break;
        case ':':
          $output .= $char." ";
          break;
        case ",":
          $output .= $char."\n".(str_repeat(" ",$indent));
          break;
        case '"':
          $inside_string = !$inside_string;
          $output .= $char;
          break;
        case " ":
        case "\t":
        case "\n":
          break;
        default:
          $output .= $char;
        }
      }
    }
  }
  return $output;
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
      echo json_pp(json_encode(instances()));
    }
    break;
  case 'get':
    if(isset($parms[1])) {
      $name = $parms[1];
      echo read_config_option($name)."\n";
    }
    else {
      echo "You have to specify the setting you want to query\n";
      echo "Usage: ".__FILE__." get <cacti_setting>\n";
      exit(1);
    }
    break;
  case 'set':
    if(isset($parms[1]) and isset($parms[2])) {
      $name = $parms[1];
      $desired_value = $parms[2];
      set_config_option($name, $desired_value);

      # The returnvalue of set_config_option is void, so we double check here
      $current_value = read_config_option($name);
      if($current_value == $desired_value) {
        echo "Setting $name has been set to $desired_value\n";
        exit(0);
      }
      else {
        echo "Unable to set $name to $desired_value. Current value is $current_value\n";
        exit(1);
      }
    }
    else {
      echo "You have to specify the setting and the desired value\n";
      echo "Usage: ".__FILE__." set <cacti_setting> <new_value>\n";
    }
    break;
  default:
    echo "ERROR: Ivalid argument ".$parms[0]."\n";
    exit(1);
    break;
  }
}
else {
  echo "Usage: apihelper.php instances\n";
  echo "Usage: apihelper.php get <cacti_setting>\n";
  echo "Usage: apihelper.php set <cacti_setting> <value>\n";
}
?>
