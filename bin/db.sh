#!/bin/sh

user=$(grep "^DATABASE_URL=" .env.local | perl -p -e 's/.*\/\/([\w-_]+):.*/$1/')

pass=$(grep "^DATABASE_URL=" .env.local | perl -p -e 's/.*\/\/([\w-_]+):(.*)@.*/$2/')

name=$(grep "^DATABASE_URL=" .env.local | perl -p -e 's/.*\w\/([\w-_]+)\?ser.*/$1/')

PGPASSWORD=$pass psql -h localhost -U $user $name
