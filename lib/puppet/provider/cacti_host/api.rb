require 'tempfile'

Puppet::Type.type(:cacti_host).provide(:api) do
  desc "Use the wrapperscript apihelper.php which directly talks
    to the cacti API that is also written in PHP.

    NOTE: You have to edit the `apihelper.php` script first and
    adjust the `include` statements to match the location of your
    cacti installation"

  self::APIHELPER = File.join(File.dirname(__FILE__), 'apihelper.php')

  confine :exists => '/usr/bin/php'
  commands :apihelper => self::APIHELPER

  mk_resource_methods

  def self.instances
    resources = []
    hosts = {}

    output = Tempfile.new('puppet_cactiapihelper')
    begin
      apihelper :instances, output.path
      hosts = PSON.parse(output.read)
    ensure
      output.close!
    end

    hosts.each_pair do |description, hash|
      resource = {:name => description, :ensure => :present}
      hash.each_pair do |key, value|
        resource[key.intern] = value
      end
      resources << new(resource)
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

  def exists?
    get(:ensure) != :absent
  end

  def create
    raise Puppet::Error, "Cannot create a cacti_host with no hostname beeing set" unless @resource[:hostname]

    # Take all the property values from the resource and
    # store them in property_hash so the flush method can
    # see them
    @resource.class.validproperties.each do |property|
      if value = @resource.should(property)
        @property_hash[property] = value
      end
    end
  end

  def destroy
    @property_hash[:ensure] = :absent
  end

  def flush
    if @property_hash[:ensure] == :absent
      apihelper :destroy, @resource[:name]
    else
      output = Tempfile.new('puppet_cactiapihelper')
      begin
        output.write PSON.generate(@property_hash)
        output.close
        apihelper :save, @resource[:name], output.path
      ensure
        output.close!
      end
    end
  end

end
