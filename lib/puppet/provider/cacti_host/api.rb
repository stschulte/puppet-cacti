require 'json'

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

    hosts = PSON.parse(apihelper('instances'))
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

end
