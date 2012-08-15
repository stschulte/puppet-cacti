# Cacti module for Puppet

This module manages Cacti on Linux. You can use it to handle cacti hosts, cacti settings and cacti plugins as puppet resources.

Because the commandline programs shipped with cacti do not offer the desired features, all types come with a `apihelper.php` script which includes the cacti libraries to read and update settings. Please note that you *have* to modifiy these scripts and update the path to your cacti installation as these vary from distribution to distribution. Just follow the instructions inside the `apihelper.php` scripts.

The types have been tested on cacti 0.8.7i and cacti 0.8.8a

## The cacti\_host type

### Description

The `cacti_host` type allows you to describe a host as a puppet resource. The type does only create and modify hosts and their attributes, deleting a host is currently not implemented. Note that while puppet can make sure your host belongs to a certain host template, puppet does not create any graphs for that host.

If you want to further automate the process of adding new hosts I recommend you install the cacti plugin `autom8` to automatically create graphs after creating a host. Because the puppet provider makes use of cacti API functions and does not modify the database directly all plugin hooks are getting executed, so the `autom8` plugin will still work.

### Sample Usage:

Make sure a `cacti_host` is present but don't care about most attributes.

    cacti_host { 'Localhost':
      ensure              => 'present',
      host_template       => 'Local Linux Machine',
      hostname            => '127.0.0.1',
    }

A more complex configuration using SNMPv3 to monitor a router could look like


    cacti_host { 'router':
      ensure              => 'present',
      availability_method => '2',
      host_template       => 'Cisco Router',
      hostname            => '192.168.0.1',
      notes               => 'managed by puppet',
      ping_method         => '2',
      ping_port           => '23',
      ping_retries        => '1',
      ping_timeout        => '400',
      snmp_auth_password  => 'bar',
      snmp_auth_protocol  => 'SHA',
      snmp_max_oids       => '10',
      snmp_port           => '161',
      snmp_priv_password  => 'baz',
      snmp_priv_protocol  => 'AES128',
      snmp_timeout        => '500',
      snmp_username       => 'foo',
      snmp_version        => '3',
    }

## The cacti\_plugin type

### Description

The `cacti_plugin` type allows you to specify the state of a plugin (`uninstalled`, `disabled`, `enabled`) as a puppet resource. The plugin you want to manage through puppet has to be already installed (e.g. extracted into `/usr/share/cacti/plugins`.

If a plugin is in the state `needs configuration` the provider will just fail and the issue needs to be resolved by hand.

### Sample Usage

Make sure the extracted plugin autom8 is enabled

    cacti_plugin { 'autom8':
      ensure => enabled,
    }

Make sure that the extracted plugin thold is uninstalled and the plugin settings is in state disabled.

    cacti_plugin { 'thold':
      ensure => uninstalled,
    }

    cacti_plugin { 'settings':
      ensure => disabled,
    }

## The cacti\_setting type

### Description

The Cacti application allows you to change settings (like `path_rrdtool`) inside the web GUI. The `cacti_setting` type now allows you to describe a setting as a puppet resource. Puppet will make sure the specified setting has the desired value.

To achieve that goal, the `api` provider will run a helperscript (`apihelper.php`) that will directly use cacti functions to read/write settings.

Please note that you have to modify the script `apihelper.php` in order to use this type. Just read the instructions inside the `apihelper.php` file.

### Sample Usage

Make sure the value of `path_rrdtool` is `/usr/bin/rrdtool`

    cacti_setting { 'path_rrdtool':
      value => '/usr/bin/rrdtool',
    }

Make sure the autom8 plugin will create trivial graphs automatically

    cacti_setting { 'autom8_graphs_enabled':
      value => 'on',
    }

