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
        // Rename uploaded file to reflect original name
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK)
            throw new Exception('File was not successfully uploaded from your computer.');
    
        $tmpDir = uniqid('/tmp/DropboxUploader-');
        if (!mkdir($tmpDir))
            throw new Exception('Cannot create temporary directory!');
    
        if ($_FILES['file']['name'] === "")
            throw new Exception('File name not supplied by the browser.');
        
        $tmpFile = $tmpDir.'/'.str_replace("/\0", '_', $_FILES['file']['name']);
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $tmpFile))
            throw new Exception('Cannot rename uploaded file!');
    
        // Upload
        $uploader = new DropboxUploader($_POST['email'], $_POST['password']);
        $uploader->upload($tmpFile, $_POST['dest']);
    
        echo '<span style="color: green">File successfully uploaded to your Dropbox!</span>';
    } catch(Exception $e) {
        echo '<span style="color: red">Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
    }

    // Clean up
    if (isset($tmpFile) && file_exists($tmpFile))
        unlink($tmpFile);
    
    if (isset($tmpDir) && file_exists($tmpDir))
        rmdir($tmpDir);
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
