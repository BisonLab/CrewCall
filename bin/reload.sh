#!/bin/sh

rm -rf var/cache/*
./bin/console --force  doctrine:schema:drop
./bin/console --force  --em=sakonnin doctrine:schema:drop
./bin/console doctrine:schema:create
./bin/console --em=sakonnin doctrine:schema:create
./bin/console fos:user:create --super-admin crewcall crewcall@localhost cc
./bin/console cache:clear --no-warmup
./bin/console sakonnin:insert-basedata
./bin/console once:create-base-data
./bin/console once:create-base-function
rm -rf var/cache/*
