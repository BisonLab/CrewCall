# INSTALL

 - Clone this.
 - Create the database. I've been using postgresql for development.
 - Run ./bin/prep.sh  This give you a copy of all the config files you would want to edit for your needs. It will also clone the CrewCallBundle into src/.
 - composer update (Yes, you need composer.) https://getcomposer.org/
 - Run composer update again and again and again while installing whatever the packages needs for installing themselves.
 - ./bin/console assetic:dump

Optionally, run ./bin/reload.sh for preparing the database, insert some fixtures and create the crewcall user with the too simple "cc" password.

# Customization.

This is a work in progress. The goal is to give you the option to just use this "base" and the CrewCallBundle straight from github or fork it for your own customization while keeping up to date with the main application which is the CrewCallBundle.

(And for me (The main author, Thomas Lundquist) it is to use this base as my base so I don't have to sync it with my development which would end up being way too irregular.)

This means you can hack your own base design, reports, menu options and so on by extending or editing the content in app/Resources and src/CustomBundle. You can also add new bundles in app/AppKernelCustomTrait.php without conflicts.

Your own configuration can be put in the app/config/\*custom.yml files and they will not be messed up by a merge with upstream. 

But don't do this unless you really want to, since if you want to have this in a git repo it has to be a fork from "my" tree and you will have to sync between your new fork and upstream from time to time like with Symfony-Standard, which this is an extension of.

I will try to make it possible for you to just merge from upstream when you feel like it. But each time you do you should check the .dist files for new stuff. You can bet composer.json has been updated at least.

The reason CustomBundle is in the appResources/prepskeleton/ while the dist-files are not is questionable. I decided to do like this for now, but may change my opinion on it. Should they be easier to diff or not in the way for the daily work? Feel free to answer me what you think.
