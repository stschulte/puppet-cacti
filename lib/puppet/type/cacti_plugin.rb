Puppet::Type.newtype(:cacti_plugin) do

  @doc = "Manages cacti plugins. You can use it to install, enable or
    disable a cacti plugin."

  newparam(:name) do
    desc "The name of the plugin."
  end

  newproperty(:ensure) do
    desc "The desired state of the plugin"

    newvalue :uninstalled
    newvalue :disabled
    newvalue :enabled
  end

end
