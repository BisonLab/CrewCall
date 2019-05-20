#!/bin/bash -e

rm -rf var/cache/*
./bin/console --force  doctrine:schema:drop
./bin/console --force  --em=sakonnin doctrine:schema:drop
./bin/console doctrine:schema:create
./bin/console --em=sakonnin doctrine:schema:create
./bin/console cache:clear --no-warmup
./bin/console once:create-base-data
./bin/console once:create-base-function
if [ "$1" == "with-user" ]
 then
  ./bin/console fos:user:create  crewcall crewcall@localhost cc
  ./bin/console fos:user:promote crewcall ROLE_ADMIN
fi
rm -rf var/cache/*
