#INSTALL

 - Clone this.
 - Run ./bin/prep.sh  This give you a copy of all the config files you would want to edit for your needs.
 - cd src
 - git clone git@github.com:BisonLab/CrewCallBundle.git
 - cd ..
 - composer update (Yes, you need composer.) https://getcomposer.org/

# Customization.

The reason I've split the applicaiton into a base and a bundle is that you know have the option to customize the base (almost) as much as you want while staying up to date with the main application.

This means your own base design, reports, menu options and so on.

But don't do this unless you really want to, since you will have to sync between your new fork and upstream from time to time like with Symfony-Standard, which this is an extension of.
