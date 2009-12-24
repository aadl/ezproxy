Requirements
------------

This module requires Drupal 5 or higher and EZproxy installed


Installation
------------

1. Place the ezproxy module into your modules directory (sites/all/modules)

2. Enable the module in admin >> site configuration >> modules

3. Set up access permissions in admin >> user >> permissions for both 'administer ezproxy'
   and 'access exproxy content'.



Configuration
-------------
There are three ways this module can be used:

1. External script authentication
   In this mode, EZproxy will present the login screen, but the usernames and passwords will be checked
   by Drupal in the Drupal database.

   http://www.oclc.org/support/documentation/ezproxy/usr/external.htm

2. CGI Authentication (recommended)
   In this mode, EZproxy will get Drupal to the authentication itself.  The standard Drupal login form
   will be used if needed.  If a user is already logged into Drupal when they request an EZproxy database
   they will be taken there directly.  Use this mode for transparent login between Drupal and EZproxy.

   http://www.oclc.org/support/documentation/ezproxy/usr/cgi.htm

3. Ticket Authentication
   In this mode, links are created which contain ticket authentication information which is used to authenticate
   the user.  This method, by nature, is more of an API than anything else.

External script authentication
------------------------------

1. Configure EZproxy to use Drupal as the external script for authentication.
   Copy the following line into the ezproxy user.txt file (/usr/local/ezproxy/user.txt),
   replace example.com with the domain name for your Drupal site.
  
   ::external=http://example.com/ezproxyauth,post=ezuser=^u&ezpass=^p,valid=+OK

2. Go to the configuration page for ezproxy (admin >> site configuration >> ezproxy) and enter the hostname
   for your ezproxy server.  If you have this installed in the same machine as Drupal this is probably the
   same domain name as your Drupal site.


CGI Authentication (recommended)
--------------------------------

1. Configure ezproxy to use Drupal for authentication.
   Copy the following line into the ezproxy user.txt file (/usr/local/ezproxy/user.txt),
   replace example.com with the domain name for your Drupal site.
  
   ::CGI=http://example.com/ezproxylogin?url=^U

2. Go to the configuration page for ezproxy (admin >> site configuration >> ezproxy) and enter the hostname,
   port, and ticket secret for your ezproxy server.  If you have this installed in the same machine as Drupal
   this is probably the same domain name as your Drupal site.


Ticket Authentication
----------------------
1. Configure EZProzy to accept authentication through tickets, then set the ticket secret in the EZProxy
   settings page

Usage
-----
1. External script authentication
   When you click on a link to go to an EZproxy database, EZproxy will ask you to login.  The username and 
   password entered will be sent to Drupal and Drupal will check if the user exists in the system and has 
   the 'access ezproxy content' permission.  If the user does have permission, Drupal will report back to
   EZproxy with a successful logon attempt.

2. CGI authentication
   When you click on a link to go to an EZproxy database, EZproxy will first ask Drupal if you are authenticated.
   If the user is currently logged in as has the 'access ezproxy content' permission then Drupal will report back
   to EZproxy with the successful logon attempt and the user will be taken to the database they requested.
   If the user is not logged in, Drupal will prompt them to login.  When successful Drupal will report back
   to EZproxy with the successful logon attempt and the user will be taken to the database they requested.

3. Ticket Authentication
   Create links using the ezproxy_l() function this module provides.  This function acts just like the l()
   function in Drupal core except that the "path" contains the URL of the database you wish to connect to.
   The function returns a fully decorated URL.

Notes
-----
1. The list of databases set up in EZprozy can be viewed at admin >> settings >> ezproxy >> list
2. A logout from EZproxy can be forced by going to http://example.com/ezproxylogout



Author/Maintainer
-----------------

Aaron Fulton aaron@webtolife.org

