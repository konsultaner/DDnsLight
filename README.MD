# DDnsLight #
This is a little class that allows you to use your webserver to be a dynamic dns server.

## Installation ##
Create a index.php in a folder you want to use for the update process like '/dns'. Copy the class to the folder and edit the index.php like

    <?php
    include_once("DDnsLight.php");

    $dns = new DDnsLight();
    $dns->run();

The first time you run the script, it will create a folder "storage" including a "dns.ini.php" and a ".htaccess" file. Open the ini.php file and set a password. The password will be used to authorize the update-ip process.

The second time you call the script, it will do what it was written for.

## Call the dynamic ip ##
For simple website applications it will use the location header to redirect to a dynamic ip. To do so you need to call the site like

    http://my-static-site.com/?/
    http://my-static-site.com/?/a/deep/link

to simply receive the current dynamic ip, simply call

    http://my-static-site.com/

this can be used for other scripts that redirect other services

## Config to update dyndns as well ##

The class has some constants. You should only change the following to make the script also update your dyndns account

    const DYN_DNS_ACCOUNT_UPDATE = FALSE;
    const DYN_DNS_ACCOUNT_USER = "";
    const DYN_DNS_ACCOUNT_PASSWORD = "";
    const DYN_DNS_ACCOUNT_HOST = "";

## Licence ##

Use the script as you like on your own risk.

The script originally came from [here](http://www.axelteichmann.de/DynamicDNS/index-DynDNS-mit-Fritzbox.php).