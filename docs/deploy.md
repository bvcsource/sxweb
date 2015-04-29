# DEPLOY INSTRUCTIONS

## GETTING STARTED

Copy all SXWeb files to a new directory, e.g. /var/www/sxweb and create a new virtual host in your web server configuration. 
Make sure the document root points to the public/ sub-directory, i.e. /var/www/sxweb/public.

If you are using Apache, adapt this example:

    <VirtualHost *:443>
        ServerName sxweb.foo.com
        ServerAdmin webmaster@foo.com
        DocumentRoot /var/www/sxweb/public/
        <Directory /var/www/sxweb/public>
            <IfModule mod_rewrite.c>
                RewriteEngine On
                RewriteCond %{REQUEST_FILENAME} -s [OR]
                RewriteCond %{REQUEST_FILENAME} -l [OR]
                RewriteCond %{REQUEST_FILENAME} -d
                RewriteRule ^.*$ - [NC,L]
                RewriteRule ^.*$ index.php [NC,L]
            </IfModule>
        SSLEngine on
        SSLCertificateFile    /etc/ssl/certs/wildcard.skylable.com.pem
        SSLCertificateKeyFile /etc/ssl/private/wildcard.skylable.com.key
    </VirtualHost>

Make sure that mod_rewrite is enabled: 

    # a2enmod rewrite

If you are using nginx and php-fpm, create a new server { } section following this example:

    server {
        listen 443;
        ssl on;
        ssl_certificate        /etc/ssl/sxweb.foo.com.crt;
        ssl_certificate_key    /etc/ssl/sxweb.foo.com.key;

        server_name sxweb.foo.com;
        server_tokens off;
        root /var/www/sxweb/public;
        index       index.html index.htm index.php;
        client_max_body_size    128M;
        location / {
            try_files $uri $uri/ /index.php$is_args$args;

            location ~ \.php$ {
                fastcgi_pass   127.0.0.1:9000;
                fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
                include        fastcgi_params;
                fastcgi_read_timeout 600;
                fastcgi_hide_header X-Powered-By;
            }
        }
    }

Make sure that php-fpm is running and listening on 127.0.0.1:9000.

If you are using a different web server, refer to the documentation of the web server or contact us for assistance.


Reload the configuration of your web server and then open SXWeb in your browser to start the installer: https://sxweb.foo.com

## SETUP AND UPGRADES

SXWeb comes with a web installer that takes care of the initial setup of the application and subsequent upgrades. If the necessary configuration files aren't present, the web installer is run automatically. 

When upgrading, all you need to do is to unpack the distribution files and point your browser to the `install.php` script into your webroot.
 
The web installer will help you to generate the main configuration file, which will be stored under: `application/configs/skylable.ini`. 
 
## DATABASE

To run SXWeb you need a MySQL database and a MySQL account. The web installer will create the DB schema for you.

If you want to perform this operation manually, you can find the database schemas into the `sql/` directory. This directory also contains the files to update the database schema when upgrading to a newer version of SXWeb. Normally you don't need to use these files, they will be automatically applied for you.

## DIRECTORIES AND PERMISSIONS

All data used by SXWeb usually resides into the `data/` directory. This directory must be writable by the web server and the PHP module. 

By default the `data/` directory will hold logs, session data, user data and temporary file uploads: make sure you have sufficient disk space available.

The directory ``application/configs/` holds all the application configuration files and should be writable by the web server, but this is not mandatory: you can simply create the needed files here, but they must be readable by the web server and the PHP module.

Assuming that you installed SXWeb under /var/www/sxweb, you can use these commands to fix the permissions, after replacing $WEBSERVER_USER with the user under which PHP executes the scripts (check phpinfo() output when in doubt):

    # find /var/www/sxweb -type f -exec chmod 640 {} \;
    # chown -R root:$WEBSERVER_USER /var/www/sxweb
    # find /var/www/sxweb/data -type d -exec chmod 700 {} \;
    # chown -R $WEBSERVER_USER /var/www/sxweb/data
    # touch /var/www/sxweb/application/configs/skylable.ini
    # chmod 640 /var/www/sxweb/application/configs/skylable.ini
    # chown $WEBSERVER_USER /var/www/sxweb/application/configs/skylable.ini

## APPLICATION

The main configuration file is `application/configs/skylable.ini`. Usually you don't need to edit this file manually, it is generated automatically by the web installer.
 
You can find a sample configuration file here: `application/configs/skylable.ini.sample`.

*Important*: these .ini files follow the guidelines you can find here: [Zend_Config_Ini Documentation](http://framework.zend.com/manual/1.12/en/zend.config.adapters.ini.html).

You can have multiple sections and use PHP constants (as you can see into the sample file), so spend 5 minutes reading the basics into the linked page.

By default the application use the config keys into the `production` section.

There is a second configuration file with some advanced settings under `application/configs/application.ini`.

Normally you don't need to edit this file. From this file you can change the logging level and some application parameters.

By default it forces PHP to store sessions data locally: __don't change this__!

## CAVEATS

If you get 403 Forbidden, make sure that SELinux is not interfering with the application. 
Check `/var/log/audit.log` and if required run:

    # chcon -Rt httpd_sys_content_t /path/to/sxweb

SXWeb needs to write to the data/ subdirectory. Make sure it is writable by the user under which the webserver 
is running and also verify that SELinux is not blocking write access to it:

    # chcon -R -h -t httpd_sys_script_rw_t /path/to/sxweb/data

## TROUBLESHOOTING

SXWeb will always try to tell you what is going wrong writing it to a log file. The first place to search for problems is `data/logs/sxweb.log`. Then you can look at the web server error log (on Ubuntu located in `/var/log/apache2/error.log`).
 
 Usually the logging is not too much verbose, but switching to the `development` application profile will make logging very verbose and will show you lots of messages on screen. Don't use it in production.
 
 You can change the logging level without changing the application profile editing the file `application/configs/application.ini` and changing the line:
 
     resources.log.stream.filterParams.priority = 4
 
 To:
 
     resources.log.stream.filterParams.priority = 7
  
