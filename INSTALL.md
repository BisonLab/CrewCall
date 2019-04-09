CrewCall - Installation.
======================

This is a howto for installing and setting up CrewCall on a Debian/Ubuntu server. Installing on other Linux/Unix distributions is no problem, just find the right packages.

The application runs nicely on php7 which is also recommended. Debian 9 Jessie  and Ubuntu above 16.04 has it.


OS Packages
-----------

 * git zip unzip
 * postgresql (Other DBMSes should work since we do use Doctrine, but they are not tested)

 * php-cli php-apcu php-gearman php-pgsql php-json php-curl php-dev pkg-config php-gmp php-intl php-symfony-polyfill-intl-icu php-zip php-bz2

I'm using apache, but nginx should work aswell. Feel free to try. Please send a report on how it went.

 * apache2 libapache2-mod-php apache2-utils

And I'm sorry, but I did end up with Bootstrap and stuff that need LESS. So, you've gotta install node.js and friends.

 * node-less

CrewCall base
-----------

The base is basically a customized version of Symfony Standard and should be handled the same way. But, for now it does not have a composer or symfony install routine. Alas, we're doing it the good old way.

 - Fork this. That way you can have your own git repo with your changes and config files while being able to compare with upstream when you feel like.

 - git clone the fork to wherever you want it.

 - Run ./bin/prep.sh  This give you a copy of all the config files you would want to edit for your needs. It will also clone the CrewCall main application Bundle into src/.

You may want to follow the upstream CrewCall Base, if so, do this:

$ git remote add upstream git@git.github.com:bisonlab/CrewCall.git 
$ git fetch upstream

Then, to pull from master, which should be safe since it should only be messing with distfiles and the skeleton unless there are bugs in other places.

$ git pull upstream master


Parameters
----------

During the composer update (See below) you will be asked for these amongst others:

 * secret (Just hit random keys. 16'ish of'em.)
 * database_host (default localhost)
 * database_port  (default 5432)
 * database_name
 * database_user
 * database_password

The database do not have to be ready, but here you have to plan or know what to use.
How to create the database and user comes after the next section.


Symfony and friends
-------------------

The composer binary is not a part of the base, but you need it. It is suggested that you add this to /usr/local/bin but I tend to put it in the root directory of the project.

https://getcomposer.org/download/

$ <wherever>/composer.phar update

Run composer update again and again and again while installing whatever the packages needs for installing themselves.

$ ./bin/console assetic:dump


Database
--------

You gotta create the database user and databases.

The database can be created by Doctrine later, but it still needs a user:

(You'd probably have to do this as the postgres user or root. It will create the user with database creation granted.))

$ createuser --createdb --login --pwprompt <DBUSER>

Back to the user with the project:

The database.
$ ./bin/console doctrine:database:create

And the ones below can be run separately or with  ..

$ ./bin/reload.sh

.. for preparing the database, insert some fixtures and create the crewcall user with the too simple "cc" password.

$ ./bin/console doctrine:schema:create

Sakonnin (the message/log/file handling system)
$ ./bin/console doctrine:schema:create --em=sakonnin

Add Sakonnin message types and prep a little.

$ ./bin/console sakonnin:insert-basedata

Add some CC specific base data for an easier start

$ ./bin/console once:create-base-data

$ ./bin/console once:create-base-function

Add the crewcall user, and yes, replace the password at the end.

$ ./bin/console fos:user:create crewcall crewcall@localhost <PASSWORD>

Better be an admin.

$ ./bin/console fos:user:promote crewcall ROLE_ADMIN


And yes, we have to set up the web server.
-----------------------------------------

This you probably know already and if not, go here:

http://symfony.com/doc/current/setup/web_server_configuration.html

And also decide how to solve file permissions issues:

http://symfony.com/doc/current/setup/file_permissions.html

The ACL method is the preferrable:

$ sudo apt-get install acl

$ HTTPDUSER=www-data
$ APPOWNER=<your username>
$ sudo setfacl -dR -m u:"$HTTPDUSER":rwX -m u:"$APPOWNER":rwX var
$ sudo setfacl -R -m u:"$HTTPDUSER":rwX -m u:"$APPOWNER":rwX var


Config files
-------------

In app/config you will find alot of yaml - files. Some of these are meant to be edited, some you should leave as they are. parameters.yml is for every instance you run, like dev and prod and config_custom.yml is meant for stuff that are alike on both of tese.

In config_custom.yml you will have a config option "allow_registration" which should decide if registration is alowed or not. It's not the only place unfortunately. You have to comment out the "fos_user_register" section in routing_custom.yml since it cannot be set based on the first parameter.

The other files should usually not need to be edited unless you go the "I need customization" path. Which it's about in the next section.


Customization
-------------

This is a work in progress. The goal is to give you the option to just use this "base" and the CrewCallBundle straight from github or fork it for your own customization while keeping up to date with the main application which is the CrewCallBundle.

(And for me (The main author, Thomas Lundquist) it is to use this base as my base so I don't have to sync it with my development which would end up being way too irregular.)

This means you can hack your own base design, reports, menu options and so on by extending or editing the content in app/Resources and src/CustomBundle. You can also add new bundles in app/AppKernelCustomTrait.php without conflicts.

Your own configuration can be put in the app/config/\*custom.yml files and they will not be messed up by a merge with upstream. 

But don't do this unless you really want to, since if you want to have this in a git repo it has to be a fork from "my" tree and you will have to sync between your new fork and upstream from time to time like with Symfony-Standard, which this is an extension of.

I will try to make it possible for you to just merge from upstream when you feel like it. But each time you do you should check the .dist files for new stuff. You can bet composer.json has been updated at least.

The reason CustomBundle is in the appResources/prepskeleton/ while the dist-files are not is questionable. I decided to do like this for now, but may change my opinion on it. Should they be easier to diff or not in the way for the daily work? Feel free to answer me what you think.


Just a note about migrations.
-----------------------------

Using doctrine:migrations:diff is easy and very useful after you are into production or don't want to reload all the schemas and data.

But it will look bad, and be even worse if you do not care about the entity managers and by that also table prefixes.

Which is totally possible like this:

$ ./bin/console doctrine:migrations:diff  --em=crewcall --filter-expression='/crewcall_/'
$ ./bin/console doctrine:migrations:diff  --em=sakonnin --filter-expression='/sakonnin_/'
