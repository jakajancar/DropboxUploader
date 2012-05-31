<!DOCTYPE html>
<html>
    <head>
         <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>Dropbox Uploader Demo</title>
    </head>
    <body>
        <h1>Dropbox Uploader Demo</h1>
<?php
if ($_POST) {
    require 'DropboxUploader.php';

    try {
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK)
            throw new Exception('File was not successfully uploaded from your computer.');
    
        if ($_FILES['file']['name'] === "")
            throw new Exception('File name not supplied by the browser.');
        
        // Upload
        $uploader = new DropboxUploader($_POST['email'], $_POST['password']);
        $uploader->upload($_FILES['file']['tmp_name'], $_POST['dest'],  $_FILES['file']['name']);
    
        echo '<span style="color: green">File successfully uploaded to your Dropbox!</span>';
    } catch(Exception $e) {
        echo '<span style="color: red">Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
    }

}
?>
        <form method="POST" enctype="multipart/form-data">
        <dl>
            <dt>Dropbox e-mail</dt><dd><input type="text" name="email" /></dd>
            <dt>Dropbox password</dt><dd><input type="password" name="password" /></dd>
            <dt>Destination directory (optional)</dt><dd><input type="text" name="dest" /> e.g. "dir/subdir", will be created if it doesn't exist</dd>
            <dt>File</dt><dd><input type="file" name="file" /></dd>
            <dd><input type="submit" value="Upload the file to my Dropbox!" /></dd>
        </dl>
    </body>
</html>
