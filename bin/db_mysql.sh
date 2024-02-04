#!/bin/sh

user=$(grep "^DATABASE_URL=" .env.local | perl -p -e 's/.*\/\/([\w-_]+):.*/$1/')
pass=$(grep "^DATABASE_URL=" .env.local | perl -p -e 's/.*\/\/([\w-_]+):(.*)@.*/$2/')

host=$(grep "^DATABASE_URL=" .env.local | perl -p -e 's/.*@([\w\.]+):\d+\/.*/$1/')
port=$(grep "^DATABASE_URL=" .env.local | perl -p -e 's/.*@[\w\.]+:(\d+)\/.*/$1/')

name=$(grep "^DATABASE_URL=" .env.local | perl -p -e 's/.*\/(\w+)\?.*"/$1/')

# echo mysql -u $user --password="$pass" -p -h $host $name
mysql -u $user --password="$pass" -h $host $name
