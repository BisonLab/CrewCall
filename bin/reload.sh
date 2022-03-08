#!/bin/sh

rm -rf var/cache/*
./bin/console --force  doctrine:schema:drop
./bin/console --force  --em=sakonnin doctrine:schema:drop
./bin/console doctrine:schema:create
./bin/console --em=sakonnin doctrine:schema:create
./bin/console cache:clear --no-warmup
./bin/console once:create-base-data
if [ "$1" = "with-user" ]
 then
    [ -n "$2" ] && user=$2
    [ -n "$3" ] && email=$3
    ./bin/console crewcall:user:create --role=ADMIN $user $email
    echo Created user $user. Sending passwoerd email to $email 
    ./bin/console crewcall:user:send-passwordmail $user
fi

#rm -rf var/cache/*
