require 'tempfile'

Puppet::Type.type(:cacti_plugin).provide(:api) do
  desc "Use the wrapperscript apihelper.php which directly talks
    to the cacti API that is also written in PHP.

    NOTE: You have to edit the `apihelper.php` script first and
    adjust the `include` statements to match the location of your
    cacti installation"

  self::APIHELPER = File.join(File.dirname(__FILE__), 'apihelper.php')

  commands :apihelper => self::APIHELPER

  def self.instances
    resources = []
    plugins = {}

    output = Tempfile.new('puppet_cactiapihelper')
    begin
      apihelper :instances, output.path
      plugins = PSON.parse(output.read)
    rescue PSON::ParserError => detail
      raise Puppet::Error, "Unable to get cacti plugins in json format. Please check that cacti and mysqld are running and that you have modified #{self::APIHELPER} to match your installation: #{detail}"
    ensure
      output.close!
    end

    plugins.each_pair do |name, status|
      resources << new(:name => name, :ensure => status, :provider => :api);
    end
    resources
  end

  def self.prefetch(resources)
    instances.each do |prov|
      if resource = resources[prov.name]
        resource.provider = prov
      end
    end
  end

  def ensure
    @property_hash[:ensure] || :uninstalled
  end

  def ensure=(desired_state)
    action = case desired_state
    when :enabled
      :enable
    when :disabled
      :disable
    when :uninstalled
      :uninstall
    end
    apihelper action, resource[:name]
    @property_hash[:ensure] = desired_state
  end

end
