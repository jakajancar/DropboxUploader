Dropbox Uploader
================

Dropbox Uploader is a PHP class which can be used to upload files to [Dropbox](http://www.getdropbox.com/), an online file synchronization and backup service.

You can use it to create a file upload form for your webpage, which uploads files to your dropbox. The `exampe.php` is a good start; just remove the email/password/destination fields and hardcode the respective values.

Another possibility is to create an email-to-dropbox gateway using procmail or something similar.

If you have too much time on your hands, you can even create a service to offer the above to non-technical persons.

Usage
-----

    require 'DropboxUploader.php';

    $uploader = new DropboxUploader('email@address.com', 'password');
    $uploader->upload('path/to/a/file.txt');

For a more complete usage example, see `example.php`.

License
-------

Dropbox Uploader is licensed under the [MIT License](http://en.wikipedia.org/wiki/MIT_License).

Trobleshooting
--------------

**I'm getting the following error: `Error: Cannot execute request: SSL certificate problem, verify that the CA cert is OK. Details: error:14090086:SSL routines:SSL3_GET_SERVER_CERTIFICATE:certificate verify failed`**

This means that the certificate of the Certification Authority (CA) that Dropbox uses for their SSL certificates is not installed on your system or PHP/cURL is not configured correctly to find it.

**If you ARE the system administrator**, try installing the CA certificates bundle to a system-wide location. If you use a package management system, this will ensure that they are kept up to date automatically. On Debian Linux for example, you can install the package ca-certificates.

**If you are NOT the system administrator**, you can download just [the needed certificate][cert] and point DropboxUploader to it (before calling the *upload()* method):

    $uploader->setCaCertificateFile("path/to/the/certificate.cer");

[cert]: http://curl.haxx.se/ca/cacert.pem
