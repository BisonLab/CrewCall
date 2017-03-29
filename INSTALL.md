# INSTALL

 - Clone this.
 - Create the database. I've been using postgresql for development.
 - Run ./bin/prep.sh  This give you a copy of all the config files you would want to edit for your needs. It will also clone the CrewCallBundle into src/.
 - composer update (Yes, you need composer.) https://getcomposer.org/
 - ./bin/console assetic:dump

Optionally, run ./bin/reload.sh for preparing the database, insert some fixtures and create the cerewcall user with the too easy "cc" password.

# Customization.

The reason I've split the applicaiton into a base and a bundle is that you know have the option to customize the base (almost) as much as you want while staying up to date with the main application.

This means your own base design, reports, menu options and so on.

But don't do this unless you really want to, since you will have to sync between your new fork and upstream from time to time like with Symfony-Standard, which this is an extension of.
