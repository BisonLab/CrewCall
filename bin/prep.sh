#!/bin/sh

files="app/AppKernelCustomTrait.php app/config/states.yml app/config/contexts.yml app/config/types.yml app/config/config_custom.yml"

for f in $files
 do
  echo "cp ${f}.dist $f"
 done

cd src
git clone git@github.com:BisonLab/CrewCallBundle.git
