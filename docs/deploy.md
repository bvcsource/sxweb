# DEPLOY INSTRUCTIONS

## WEB INSTALLER
SXWeb comes with a web installer that you can use to install or upgrade the software. If the necessary configuration files aren't present, the web installer is run automatically. 

When upgrading, all you need to do is to unpack the distribution files and point your browser to the `install.php` script into your webroot.
 
 The web installer will help you to generate the main configuration file, that you can find here: `application/configs/skylable.ini`. 
 
## DATABASE
To run SXWeb you need a MySQL database. The web installer will create the DB schema for you.

### Manual installation/upgrade
The database schemas are into the `sql/` directory. To create the database you must use the file `sxweb.sql`. 

To upgrade manually, head to the directory `sql/upgrade`, find the right subdirectory (usually named `from_XX_to_YY` where XX and YY are version numbers) and apply `update_NN.sql` files you'll find into the directory in the order that the NN number indicates.

## DIRECTORIES

All data used by SXWeb usually resides into the `data/` directory, that must be writable by the web server and the PHP module. 

By default the `data/` directory will hold logs, session data, user data and temporary file uploads: remember to have sufficient disk space.

The directory ``application/configs/` holds all the application configuration files and should be writable by the web server, but this is not mandatory: you can simply create the needed files here, but they must be readable by the web server and the PHP module.

## APPLICATION

The main configuration file is `application/configs/skylable.ini`. Usually you generate this file using the web installer.
 
You can find a sample configuration file here: `application/configs/skylable.ini.sample`.

*Important*: these .ini files follow the guidelines you can find here: [Zend_Config_Ini Documentation](http://framework.zend.com/manual/1.12/en/zend.config.adapters.ini.html).

You can have multiple sections and use PHP constants (as you can see into the sample file), so spend 5 minutes reading the basics into the linked page.

By default the application use the config keys into the `production` section.

1) MANDATORY - Edit the file `application/configs/skylable.ini` to suits your needs.
Remember that all the settings into the `production` profile will be used into the production environment.

First thing to do is to set up your SX cluster address, the DB connection parameters and the SMTP. Then fine tune the application to suits your needs.

2) OPTIONAL - Edit the file `application/configs/application.ini`

Editing this file is not mandatory, usually you can leave it as is. For debugging purpose you can change the logging level and some application parameters.

By default it forces PHP to store sessions data locally: __don't change this__!

3) OPTIONAL - Create the file public/config.inc.php

This file is read before any other file, so you can use it to set some constants or special PHP settings that you need.

As an example, you can set this constant to use the site in "development mode":

    <?php
    define('APPLICATION_ENV','development');
    ?>

>
> You need the `development` section into your `skylable.ini` and `application.ini` files for this to work.
>

## WEB SERVER

Your web server webroot must point to the `public/` directory.

## CAVEATS

If you get 403 Forbidden, make sure that SELinux is not interfering with the application. 
Check `/var/log/audit.log` and if required run:

    # chcon -Rt httpd_sys_content_t /path/to/sxweb

## TROUBLESHOOTING
SXWeb will always try to tell you what is going wrong writing it to a log file. The first place to search for problems is `data/logs/sxweb.log`. Then you can look at the web server error log (on Ubuntu located in `/var/log/apache2/error.log`).
 
 Usually the logging is not too much verbose, but switching to the `development` application profile will make logging very verbose and will show you lots of messages on screen. Don't use it in production.
 
 You can change the logging level without changing the application profile editing the file `application/configs/application.ini` and changing the line:
 
     resources.log.stream.filterParams.priority = 4
 
 To:
 
     resources.log.stream.filterParams.priority = 7
  