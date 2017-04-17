#!/bin/sh

files="composer.json app/AppKernelCustomTrait.php app/config/states.yml app/config/contexts.yml app/config/types.yml app/config/config_custom.yml app/config/services.yml routing_custom.yml"

for f in $files
 do
  cp ${f}.dist $f
 done

cd src
git clone git@github.com:BisonLab/CrewCallBundle.git
