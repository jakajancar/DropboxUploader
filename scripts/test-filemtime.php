<?php
/**
 * test that filemtime() returns Unix timestamp (UTC) on current system
 *
 * @link http://3v4l.org/jGmdV
 */

$nowHour   = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time();
$hostname  = php_uname('n');
$formatRfc = 'D, d M Y H:i:s O';

$timestampStartOfUnixEpoch = 0; // start of the Unix epoch.
$timestampRequest          = 3600 * (int)($nowHour / 3600);

$testFile = sprintf('%s/%s', php_sys_get_temp_dir(), 'test-filemtime');

if ($hostname !== 'php_shell') {
    printf("PHP version is %s and OS is %s\n", PHP_VERSION, PHP_OS);
}
printf("Testfile is '%s'.\n", $testFile);

touch($testFile, $timestampStartOfUnixEpoch);
php_clearstatcache(false, $testFile);
$mtime = filemtime($testFile);
printf("Testfile '%s' mtime should be from touch %d, is %d.\n", basename($testFile), $timestampStartOfUnixEpoch, $mtime);
printf("The mtime represents %s\n", date($formatRfc, $mtime));

touch($testFile, $timestampRequest);
php_clearstatcache(false, $testFile);
$mtime = filemtime($testFile);
printf("Testfile '%s' mtime should be from touch %d, is %d.\n", basename($testFile), $timestampRequest, $mtime);
printf("The mtime represents %s\n", date($formatRfc, $mtime));

unlink($testFile);

/**
 * NOTE: This function is incomplete, the fallback is to '/tmp' which targets Unix-like.
 *
 * @return string
 */
function php_sys_get_temp_dir() {
    // (PHP 5 >= 5.2.1)
    if (function_exists('sys_get_temp_dir')) {
        return sys_get_temp_dir();
    }

    // (PHP 4 >= 4.3.0, PHP 5)
    if (function_exists('stream_get_meta_data')) {
        $handle = tmpfile(); // (PHP 4, PHP 5)
        $meta   = stream_get_meta_data($handle);
        // (PHP 5 >= 5.1.0)
        if (isset($meta['uri'])) {
            return dirname($meta['uri']);
        }
    }

    // emulate  PHP 4 <= 4.0.6 tempnam() behavior, fragile
    foreach(array('TMPDIR', 'TMP') as $key) {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
    }

    // fallback for Unix-like (php_shell specifically)
    return '/tmp';
}

/**
 * @param $clear_realpath_cache
 * @param $filename
 * @link http://php.net/manual/en/function.version-compare.php
 */
function php_clearstatcache($clear_realpath_cache, $filename) {
    if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
        clearstatcache($clear_realpath_cache, $filename);
    } else {
        clearstatcache();
    }
}
