require 'tempfile'

Puppet::Type.type(:cacti_setting).provide(:api) do
  desc "Use the wrapperscript apihelper.php which directly talks
    to the cacti API that is also written in PHP.

    NOTE: You have to edit the `apihelper.php` script first and
    adjust the `include` statements to match the location of your
    cacti installation"

  self::APIHELPER = File.join(File.dirname(__FILE__), 'apihelper.php')

  commands :apihelper => self::APIHELPER

  def self.instances
    resources = []
    settings = {}

    output = Tempfile.new('puppet_cactiapihelper')
    begin
      apihelper :instances, output.path
      settings = PSON.parse(output.read)
    rescue PSON::ParserError => detail
      raise Puppet::Error, "Unable to get cacti settings in json format. Please check that cacti and mysqld are running and that you have modified #{self::APIHELPER} to match your installation: #{detail}"
    ensure
      output.close!
    end

    settings.each_pair do |name, value|
      resources << new(:name => name, :value => value, :provider => :api)
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

  def value
    get(:value)
  end

  def value=(new_value)
    apihelper :set, resource[:name], new_value
    @property_hash[:value] = new_value
  end

end
