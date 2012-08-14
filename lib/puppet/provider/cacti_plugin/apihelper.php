#!/usr/bin/php
<?php

# Author: Stefan Schulte
#
# This is a helper script used by the provider "api" of the custom type
# "cacti_plugin". It can also be used as a stand-alone program to query the
# state of cacti plugins or to enable/disable plugins
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
# 1) When puppet wants to query cacti_plugins it does so by calling the
#    apihelper.php script and the apihelper.php script will return information
#    about cacti plugins in json format that can be parsed by the api provider
#    that is written in ruby
# 2) When puppet wants to uninstall/disable/enable a cacti_plugin it does also
#    call the apihelper.php script feeding it with a pluginname and the
#    desired state
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
# You should see all your cacti plugins and their attributes as JSON output on
# STDOUT now. If cacti logs a lot of warnings on STDOUT (older cacti versions
# can cause a lot of deprecations warnings if used together with a recent php
# version) you can also ask the apihelper script to write the JSON
# output to a file:
#
#     % ./apihelper.php instances /tmp/cacti_test.txt
#     % json_pp < /tmp/cacti_test.txt (requires perl-core/JSON-PP)
#
#
# You may have to edit these to match your cacti installation
require('/var/www/localhost/htdocs/cacti/include/global.php');
require_once($config["base_path"]."/lib/plugins.php");

$PLUGIN_STATE=array(
  '0' => 'uninstalled',
  '1' => 'enabled',
  '4' => 'disabled'
);

/* instances - query all cacti plugins and return plugin state as a
     hash of the form hash[$pluginname] = $state
   @returns - cacti plugins as a hash */
function instances() {
  global $PLUGIN_STATE;

  $result = array();

  $resultset = db_fetch_assoc('select directory, status from plugin_config order by directory');
  foreach($resultset as $record) {
    # there are states like needs update and needs configuration we do not handle
    $state = 'unknown';
    if(isset($PLUGIN_STATE[$record['status']])) {
      $state = $PLUGIN_STATE[$record['status']];
    }
    $result[$record['directory']] = $state;
  }
  if (empty($result)) $result = (object) null;
  return $result;
}

/* state - return the state of a single plugin
   @arg $plugin - The name of the plugin
   @return - the status as a string. Can be one of uninstalled, disabled,
     enabled or unknown */
function state($plugin) {
  global $PLUGIN_STATE;

  $state = db_fetch_cell("SELECT status FROM plugin_config WHERE directory = '$plugin'", false);
  if($state) {
    if(isset($PLUGIN_STATE[$state])) {
      return $PLUGIN_STATE[$state];
    }
    else {
      return "unknown";
    }
  }
  else {
    return "uninstalled";
  }
}

/* api_extend_plugin_is_installed - check if a plugin is installed.
     Unfortunately the plugin.php does not offer a way to check if a plugin
     is installed so we extend the api here.
   @arg $plugin - the name of the plugin
   @returns - TRUE if the plugin is installed. FALSE otherwise
*/
function api_extend_plugin_is_installed($plugin) {
  $exists = db_fetch_assoc("SELECT id FROM plugin_config WHERE directory = '$plugin'", false);
  if(sizeof($exists)) {
    return true;
  }
  else {
    return false;
  }
}

/* install - installs a plugin
   @arg $plugin - the name of the plugin
   @returns - TRUE if the plugin is present afterwards, FALSE otherwise */
function install($plugin) {
  global $config;

  $plugindir = $config['base_path'].'/plugins/'.$plugin;

  # The api_plugin_install freaks out if the there is no plugin of
  # such name
  if(file_exists($plugindir)) {
    echo "About to install $plugin\n";
    api_plugin_install($plugin);
    return api_extend_plugin_is_installed($plugin);
  }
  else {
    echo "Installation of plugin $plugin aborted because $plugindir does not exists.\n";
    return false;
  }
}

/* uninstall - uninstalls a plugin
   @arg $plugin - the name of the plugin
   @returns - TRUE if the plugin is absent afterwards, FALSE otherwise */
function uninstall($plugin) {
  echo "About to uninstall $plugin\n";
  api_plugin_uninstall($plugin);
  return (!api_extend_plugin_is_installed($plugin));
}

/* enable - enables a plugin
   @arg $plugin - the name of the plugin
   @returns - TRUE if the plugin is enabled afterwards, FALSE otherwise */
function enable($plugin) {
  echo "About to enable $plugin\n";
  api_plugin_enable($plugin);
  return api_plugin_is_enabled($plugin);
}

/* disable - disables a plugin
   @arg $plugin - the name of the plugin
   @returns - TRUE if the plugin is disabled afterwards, FALSE otherwise */
function disable($plugin) {
  echo "About to disable $plugin\n";
  api_plugin_disable($plugin);
  return (!api_plugin_is_enabled($plugin));
}

/* ensure_enabled - enables a plugin. If the plugin is currently not
     installed, it will be installed first. If the plugin is already enabled
     this is a noop.
   @arg $plugin - The name of the plugin
   @returns TRUE if the plugin is enabled afterwards, FALSE otherwise */
function ensure_enabled($plugin) {
  if (state($plugin) == 'enabled') return true;

  if (!api_extend_plugin_is_installed($plugin)) {
    if (!install($plugin)) {
      return false;
    }
  }

  return(enable($plugin));
}

/* ensure_uninstalled - uninstalls a plugin. If the plugin is currently
     enabled it will be disabled first. If the plugin is already uninstalled
     this is a noop
   @arg $plugin - the name of the plugin
   @return - TRUE if the plugin is absent afterwards, FALSE otherwise */
function ensure_uninstalled($plugin) {

  if (state($plugin) == 'uninstalled') return true;

  # if the plugin is enabled we need to disable it first
  if(api_plugin_is_enabled($plugin)) {
    if (!disable($plugin)) {
      return false;
    }
  }
  return uninstall($plugin);
}

/* ensure_disabled - disables a plugin. If the plugin is currently
     absent it will be installed first. If it is currently enabled it
     will be disabled. If the plugin is already disabled this is a noop.
   @arg $plugin - the name of the plugin
   @return - TRUE if the plugin is disabled afterwards, FALSE otherwise */
function ensure_disabled($plugin) {
  if (state($plugin) == 'disabled') return true;

  if(api_plugin_is_enabled($plugin)) {
    return (disable($plugin));
  }
  if (!api_extend_plugin_is_installed($plugin)) {
    return (install($plugin));
  }

  # should not be reached
  return false;
}

/* ensure_installed - installs a plugin. If the plugin is currently
     disabled or enabled this function does nothing"
   @arg $plugin - The name of the plugin
   @returns - TRUE if the plugin is enabled or disabled afterwards, FALSE
     otherwise */
function ensure_installed($plugin) {
  $state = state($plugin);
  if ($state == 'disabled' or $state == 'enabled') return true;
  if (api_extend_plugin_is_installed($plugin)) {
    return true;
  }
  return install($plugin);
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
  case 'install':
  case 'uninstall':
  case 'disable':
  case 'enable':
    $action = $parms[0];
    if(empty($parms[1])) {
      echo "You have to specify the plugin you want to $action\n";
      echo "Usage: ".__FILE__." $action <pluginnname>\n";
      exit(1);
    }
    $plugin = $parms[1];

    if(state($plugin) == 'unknown') {
      echo "Current state of plugin $plugin is unknown. This can happen if ";
      echo "the plugin needs configuration or is waiting for an update which ";
      echo "requires manual intervention. ";
      echo "The action $action is aborted.\n";
      exit(1);
    }

    $success = false;
    switch($action) {
    case 'install': $success = ensure_installed($plugin); break;
    case 'uninstall': $success = ensure_uninstalled($plugin); break;
    case 'disable': $success = ensure_disabled($plugin); break;
    case 'enable': $success = ensure_enabled($plugin); break;
    }
    if ($success) {
      echo "Action $action for plugin $plugin completed successfully\n";
      exit(0);
    }
    else {
      echo "Unable to $action $plugin\n";
      exit(1);
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
  echo "Usage: apihelper.php install <pluginname>\n";
  echo "Usage: apihelper.php uninstall <pluginname>\n";
  echo "Usage: apihelper.php disable <pluginname>\n";
  echo "Usage: apihelper.php enable <pluginname>\n";
}
?>
