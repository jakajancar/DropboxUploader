Dropbox Uploader
================

Dropbox Uploader is a PHP class named `DropboxUploader` which can be used to upload files to [Dropbox], an online
file synchronization and backup service.

Its development was started before Dropbox released their API, and to work, it scrapes their website. So
you can and probably should use their API now as it is much more stable. It's the [Dropbox Core API in PHP][API].

You can use the Dropbox Uploader to create a file upload form for your website, which uploads files to your dropbox. The
[`example.php`](example.php) is a good start; just remove the email/password/destination fields and insert the
respective values.

[Dropbox]: http://www.getdropbox.com/
[API]: https://www.dropbox.com/developers/core/start/php

Usage
-----

    require 'DropboxUploader.php';

    $uploader = new DropboxUploader('email@address.com', 'password');
    $uploader->upload('path/to/a/file.txt');

For a more complete usage example, see [`example.php`](example.php).

License
-------

Dropbox Uploader is licensed under the [MIT License (`MIT`)](http://spdx.org/licenses/MIT).

Troubleshooting
---------------

**I'm getting the following error:**

    Error: Cannot execute request: SSL certificate problem, verify that the CA cert is OK.⤦
    ⤥ Details: error:14090086:SSL routines:SSL3_GET_SERVER_CERTIFICATE:certificate verify failed

This means that the certificate of the Certification Authority (CA) that Dropbox uses for their SSL certificates is not
installed on your system or PHP/cURL is not configured correctly to find it.

**If you ARE the system administrator**, try installing the CA certificates bundle to a system-wide location. If you
use a package management system, this will ensure that they are kept up to date automatically. On Debian Linux for
example, you can install the package ca-certificates.

**If you are NOT the system administrator**, you can download just [the needed certificate][cert] and point
DropboxUploader to it (before calling the *upload()* method):

    $uploader->setCaCertificateFile("/absolute/path/to/the/cacert.file");

It is also possible to do this setting in the PHP ini file for PHP 5.3.7 and above. See [`curl.cainfo`][ini] for the ini
configuration and look for the `CURLOPT_CAINFO` option on [`curl_setopt` (PHP manual)][phpcurlsetopt].

[cert]: http://curl.haxx.se/ca/cacert.pem
[phpcurlsetopt]: http://php.net/manual/en/function.curl-setopt.php

Developing
----------

To develop, it is most easy to checkout the [hakre/DropboxUploader][development] branch:

    git clone -b development git://github.com/hakre/DropboxUploader
    cd DropboxUploader

Them retrieve the dependencies using Composer:

    wget http://getcomposer.org/composer.phar
    php composer.phar install

### Testsuite

Dropbox Uploader comes with a Phpunit testsuite located in the `test` folder.

To get the testsuite configured, copy `phpunit.xml.dist` to `phpunit.xml` and modify the Dropbox email and password
credentials and the SSL certificate store configuration (might be required if not set in PHP ini [`curl.cainfo`][ini]).

[ini]: http://php.net/manual/en/curl.configuration.php

If you want to use any of these settings from the commandline, set an environment variable with
the same name. Environment variables have a higher priority than the XML configuration;

    export Dropbox_Credential_Password=your-password-goes-here

You can then invoke the testsuite from the projects root directory:

    vendor/bin/phpunit test

### Branching

Development is done against [hakre/DropboxUploader][development], the *development*
branch. Create yourself a new branch from it and name it for every non-trivial changes you want to introduce.

Changes are then taken from feature branches into *development* and then into *master*.

Expect the development branch to get some force-pushes, just to keep in mind when your development branch diverges -
better give your local branch a different name.

[development]: https://github.com/hakre/DropboxUploader
