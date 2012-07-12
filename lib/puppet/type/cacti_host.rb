Puppet::Type.newtype(:cacti_host) do
  @doc = "Manage a host to be monitored by Cacti."

  ensurable

  newparam(:name) do
    desc "The description of the host."

    isnamevar
  end

  newproperty(:hostname) do
    desc "Fully qualified hostname or IP address for this host."
  end

  newproperty(:snmp_version) do
    desc "Supported SNMP version for this host."

    newvalues 1,2,3
  end

  newproperty(:snmp_community) do
    desc "SNMP community string for version 1 or 2."

    validate do |value|
      unless /^[a-zA-Z0-9\-_]{1,32}$/.match(value)
        raise ArgumentError, "%s is not a valid SNMP community string" % value
      end
    end
  end

  newproperty(:snmp_username) do
    desc "SNMP username for version 3."
  end

  newproperty(:snmp_auth_password) do
    desc "SNMP authorization password for version 3."
  end

  newproperty(:snmp_auth_protocol) do
    desc "SNMP authorization protocol for version 3."

    newvalues(:md5, :sha)

    munge do |value|
      value.to_s.upcase
    end
  end

  newproperty(:snmp_priv_password) do
    desc "SNMP privacy password for version 3."
  end

  newproperty(:snmp_priv_protocol) do
    desc "SNMP privacy protocol for version 3."

    newvalues(:des, :aes, :aes128, :'[None]')

    munge do |value|
      unless value.to_s == '[None]'
        value.to_s.upcase
      else
        value.to_s
      end
    end

  end

  newproperty(:snmp_context) do
    desc "SNMP context for version 3."
  end

  newproperty(:snmp_port) do
    desc "UDP port number to use for SNMP."

    validate do |value|
      unless (1..65535).include?(value.to_i)
        raise ArgumentError, "%s is not a valid port" % value
      end
    end

    munge do |value|
      value.to_i
    end
  end

  newproperty(:snmp_timeout) do
    desc "Maximum number of milliseconds to wait for an SNMP response."

    validate do |value|
      unless value =~ /^\d+$/
        raise ArgumentError, "%s is not a valid timeout" % value
      end
    end

    munge do |value|
      value.to_i
    end
  end

  newproperty(:snmp_max_oids) do
    desc "Maximum number of OID's that can be obtained in a single SNMP GET request."

    validate do |value|
      unless value =~ /^\d+$/
        raise ArgumentError, "%s is not a valid value for maximum OID's" % value
      end
    end

    munge do |value|
      value.to_i
    end
  end

  newproperty(:host_template) do
    desc "Host template to use for this host."
  end

  newproperty(:disabled)

  newparam(:enable) do
    desc "Whether a host should be actively monitored or not."

    newvalues(:true, :false)

    munge do |value|
      @resource[:disabled] = case value.to_s
        when "false" then "on"
        else ""
      end
    end

    defaultto :true
  end

  newproperty(:notes) do
    desc "Notes for this host."
  end

  newproperty(:availability_method) do
    desc "The method Cacti will use to determine if a host is available for polling."

    validate do |value|
      raise Puppet::Error, "availability_method has to be numeric, not #{value}" unless value.to_s =~ /^[0-9]+$/
    end
  end

  newproperty(:ping_method) do
    desc "The type of ping packet to send."

    validate do |value|
      raise Puppet::Error, "ping_method has to be numeric, not #{value}" unless value.to_s =~ /^[0-9]+$/
    end
  end

  newproperty(:ping_port) do
    desc "TCP or UDP port to attempt connection."

    validate do |value|
      raise Puppet::Error, "ping_port has to be numeric, not #{value}" unless value.to_s =~ /^[0-9]+$/
    end

  end

  # Hardcoded to 400 milliseconds timeout
  newproperty(:ping_timeout) do
    desc "The timeout value to use for host ICMP and UDP ping."

    validate do |value|
      raise Puppet::Error, "ping_timeout has to be numeric, not #{value}" unless value.to_s =~ /^[0-9]+$/
    end

  end

  # Hardcoded to 1 retry attempt
  newproperty(:ping_retries) do
    desc "After an initial failure, the number of ping retries Cacti will attempt."

    validate do |value|
      raise Puppet::Error, "ping_retries has to be numeric, not #{value}" unless value.to_s =~ /^[0-9]+$/
    end
  end

  validate do
    case self[:snmp_version]
    when :'3' then
      raise Puppet::Error, "snmp_username is required for SNMPv3" unless self[:snmp_username]
      raise Puppet::Error, "snmp_auth_protocol protocol is required for SNMPv3" unless self[:snmp_auth_protocol]
      raise Puppet::Error, "snmp_auth_password is required for SNMPv3" unless self[:snmp_auth_password]
      raise Puppet::Error, "snmp_priv_protocol is required for SNMPv3" unless self[:snmp_priv_protocol]
      if self[:snmp_priv_protocol] != "[None]" then
        raise Puppet::Error, "snmp_priv_password is required when using SNMPv3" unless self[:snmp_priv_password]
      end
    when :'2'
      raise Puppet::Error, "snmp_community is required for SNMPv2" unless self[:snmp_community]
    when :'1'
      raise Puppet::Error, "snmp_community is required for SNMPv1" unless self[:snmp_community]
    end
  end
end
