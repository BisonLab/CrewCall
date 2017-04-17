# INSTALL

 - Clone this.
 - Create the database. I've been using postgresql for development.
 - Run ./bin/prep.sh  This give you a copy of all the config files you would want to edit for your needs. It will also clone the CrewCallBundle into src/.
 - composer update (Yes, you need composer.) https://getcomposer.org/
 - Run composer update again and again and again while installing whatever the packages needs for installing themselves.
 - ./bin/console assetic:dump

Optionally, run ./bin/reload.sh for preparing the database, insert some fixtures and create the crewcall user with the too simple "cc" password.

# Customization.

The reason I've split the application into a base and a bundle is that you now have the option to customize the base (almost) as much as you want while staying up to date with the main application.

This means your own base design, reports, menu options and so on by extending or editing the content in app/Resources and src/CustomBundle. You can also add new bundles in app/AppKernelCustomTrait.php without conflicts.

But don't do this unless you really want to, since you will have to sync between your new fork and upstream from time to time like with Symfony-Standard, which this is an extension of.
