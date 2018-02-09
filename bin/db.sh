#!/bin/sh
pass=$(grep database_password  app/config/parameters.yml | awk '{ print $2; }')
user=$(grep database_user  app/config/parameters.yml | awk '{ print $2; }')
name=$(grep database_name  app/config/parameters.yml | awk '{ print $2; }')
echo $pass

psql -h localhost -U $user $name
