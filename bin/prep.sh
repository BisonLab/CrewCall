#!/bin/sh

[ -d app ] || { echo "Probably in the wrong directory. Needs to be run from the approot." ; exit 1; }

files="composer.json app/AppKernelCustomTrait.php app/config/states.yml app/config/contexts.yml app/config/types.yml app/config/config_custom.yml app/config/services.yml app/config/routing_custom.yml"

for f in $files
 do
  cp ${f}.dist $f
 done

cp -a app/Resources/prepskeleton/* .
cd src
git clone git@github.com:BisonLab/CrewCallBundle.git
