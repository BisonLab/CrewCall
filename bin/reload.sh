#!/bin/sh

rm -rf var/cache/*
php ./bin/console --force  doctrine:schema:drop
php ./bin/console --force  --em=sakonnin doctrine:schema:drop
php ./bin/console doctrine:schema:create
php ./bin/console --em=sakonnin doctrine:schema:create
php ./bin/console cache:clear --no-warmup
php ./bin/console once:create-base-data
if [ "$1" = "with-user" ]
 then
    [ -n "$2" ] && user=$2
    [ -n "$3" ] && email=$3
    php ./bin/console crewcall:user:create --role=ADMIN $user $email
    echo Created user $user. Sending password email to $email 
    php ./bin/console crewcall:user:send-passwordmail $user
fi

#rm -rf var/cache/*
