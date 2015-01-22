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
        $uploader = null;

        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK)
            throw new Exception('File was not successfully uploaded from your computer.');

        if ($_FILES['file']['name'] === "")
            throw new Exception('File name not supplied by the browser.');

        // Upload
        $uploader = new DropboxUploader($_POST['email'], $_POST['password']);
        $uploader->upload($_FILES['file']['tmp_name'], $_POST['destination'], $_FILES['file']['name']);

        echo '<span style="color: green">File successfully uploaded to your Dropbox!</span>';
    } catch (Exception $e) {
        // Handle Upload Exceptions
        $label = ($uploader && $e->getCode() & $uploader::FLAG_DROPBOX_GENERIC) ? 'DropboxUploader' : 'Exception';
        $error = sprintf("[%s] #%d %s", $label, $e->getCode(), $e->getMessage());

        echo '<span style="color: red">Error: ' . htmlspecialchars($error) . '</span>';
    }
}
?>
<form method="POST" enctype="multipart/form-data">
    <dl>
        <dt><label for="email">Dropbox e-mail</label></dt>
        <dd><input type="text" id="email" name="email"></dd>
        <dt><label for="password">Dropbox password</label></dt>
        <dd><input type="password" id="password" name="password"></dd>
        <dt><label for="destination">Destination directory (optional)</label></dt>
        <dd><input type="text" id="destination" name="destination"> e.g. "dir/subdirectory", will be created if it
            doesn't exist
        </dd>
        <dt><label for="file"></label>File</dt>
        <dd><input type="file" id="file" name="file"></dd>
        <dd><input type="submit" value="Upload the file to my Dropbox!"></dd>
    </dl>
</form>
</body>
</html>
