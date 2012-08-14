Puppet::Type.newtype(:cacti_setting) do

  @doc = "Manages a cacti setting and ensures it has
    a certain value"

  newparam(:name) do
    desc "The name of the setting."
  end

  newproperty(:value) do
    desc "The desired value of the setting"
  end

end
