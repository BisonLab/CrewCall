#!/bin/sh

rm -rf var/cache/*
./bin/console --force  doctrine:schema:drop
./bin/console --force  --em=sakonnin doctrine:schema:drop
./bin/console doctrine:schema:create
./bin/console --em=sakonnin doctrine:schema:create
./bin/console cache:clear --no-warmup
./bin/console once:create-base-data
if [ "$1" == "with-user" ]
 then
    ./bin/console crewcall:user:create --role=ADMIN crewcall crewcall@localhost crewcall
fi

rm -rf var/cache/*
