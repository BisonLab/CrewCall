CrewCall - Installation.
========================

This is a howto for installing and setting up CrewCall on a Debian/Ubuntu server. Installing on other Linux/Unix distributions is no problem, just find the right packages.

(This howto is not entirely up to the newest app version.)

OS Packages
-----------

 * git zip unzip
 * postgresql (Other DBMSes should work since we do use Doctrine, but they are not tested)

 * php-cli php-apcu php-gearman php-pgsql php-json php-curl php-dev pkg-config php-gmp php-intl php-symfony-polyfill-intl-icu php-zip php-bz2 php-xml php-bcmath php-mbstring php-gd

I'm using apache, but nginx should work aswell. Feel free to try. Please send a report on how it went.

 * apache2 libapache2-mod-php apache2-utils

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
How to create the database and user comes later.


Config files
-------------

Two files is used for configuration, .env.local and config/packages/custom.yaml

For now it's a bit random which config option is where.

$ cp .env.local.dist .env.local

$ cp config/packages/custom.yaml.dist config/packages/custom.yaml

Here are some of the options you should edit before creating the database and inserting the base data.

 * locale - Locale you are in. Leaving as is will mostly work.

 * fullcalendar_locale - Uses a different naming convention and does not have
   any available locale which is why it's separate. "all" is a possibility here.

 * internal_organization - Here you set the name of the organization you want as the base for the system. Want a different role, change that aswell. These will be created automatically later in tis process

 * allow_external_crew  - If you want a tad more complexity. This is if you want to put crew members in a variety of organizations and not just the default one with the default role. Usually, try without it.

 * "allow_registration" which should decide if registration is alowed or not. It's not the only place unfortunately.

 * addressing - Addresses are so much. Alas, I made it configureable. Here you define which address elements you want to use and the order they are used. It's for both input and output.

The other files should usually not need to be edited unless you go the "I need customization" path.


Symfony and friends
-------------------

The composer binary is not a part of the base, but you need it.

https://getcomposer.org/download/

$ composer.phar update

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

.. for preparing the database, insert some fixtures.

$ ./bin/reload.sh with-user <username> <email>

does the same as above but also creates a user with the second argument as username and third as email address. No second or third will create a user with $USER as username and email.

It will attempt to send a password reset email, which will fail if smtp servicer is not configured. .env.local is the right place for that part.

The password email will most probably have "http://localhost".. as URL. change it to whatever your (vurtial) host is when pasting it into your browser. Reason for this is that there is no hard coding of URL's and the application will only know how it was accessed if it's through the web server.

$ ./bin/console doctrine:schema:create

Sakonnin (the message/log/file handling system)
$ ./bin/console doctrine:schema:create --em=sakonnin

Add Sakonnin message types and prep a little.

$ ./bin/console sakonnin:insert-basedata

Add some CC specific base data for an easier start, it will create the first role and internal organization.

$ ./bin/console once:create-base-data

You can choose not to run this one or edit it before you do.

$ ./bin/console once:create-base-function

Add the crewcall user, and yes, replace the password at the end.

$ ./bin/console crewcall:user:create crewcall crewcall@localhost <PASSWORD>

Better be an admin.

$ ./bin/console crewcall:user:promote crewcall ROLE_ADMIN


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

$ setfacl -dR -m u:"$HTTPDUSER":rwX -m u:"$APPOWNER":rwX var

$ setfacl -R -m u:"$HTTPDUSER":rwX -m u:"$APPOWNER":rwX var

Customization
-------------

If the config files mentione above is not enough, you can use a Custom Bundle wihere you can do almost everything you want.

git clone or fork the skeleton.

https://github.com/BisonLab/CrewCallCustomBundle.git

Have fun adding controllers, commands, migrations and so on here.

Just a note about migrations.
-----------------------------

Using doctrine:migrations:diff is easy and very useful after you are into production or don't want to reload all the schemas and data.

But it will look bad, and be even worse if you do not care about the entity managers and by that also table prefixes.

Which is totally possible like this:

$ ./bin/console doctrine:migrations:diff  --em=crewcall --filter-expression='/crewcall_/'

$ ./bin/console doctrine:migrations:diff  --em=sakonnin --filter-expression='/sakonnin_/'
