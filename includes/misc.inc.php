<?php

include('config.inc.php');

$blocklogin = FALSE;

if ((!isset($apipass) or empty($apipass)) or (!isset($apiip) or empty($apiip)) or (!isset($apiport) or empty($apiport))) {
    $errormsg = 'You need to configure your settings for the PowerDNS API. See <a href="doc/apiconf.txt">doc/apiconf.txt</a>';
    $blocklogin = TRUE;
}

if (!isset($apiproto) or !preg_match('/^http(s)?$/', $apiproto)) {
    $errormsg = "The value for \$apiproto is incorrect in your config. Did you configure it?";
    $blocklogin = TRUE;
}

if (!isset($apisslverify)) {
    $errormsg = "The value for \$apisslverify is incorrect in your config. Did you configure it?";
    $blocklogin = TRUE;
} else {
    $apisslverify = ( bool ) $apisslverify;
}

if (isset($defaults['primaryns'])) {
    $errormsg = "You should reconfigure your \$defaults['primaryns'] settings to use <code>\$defaults['ns'][0]</code>. We converted if for you now.";
    $defaults['ns'][] = $defaults['primaryns'];
    if (isset($defaults['secondaryns'])) {
        $defaults['ns'][] = $defaults['secondaryns'];
    }
}

if (!isset($logo) or empty($logo)) {
    $logo = 'http://www.tuxis.nl/uploads/images/nsedit.png';
}


/* No need to change stuf below */

if (function_exists('curl_init') === FALSE) {
    $errormsg = "You need PHP Curl to run nsedit";
    $blocklogin = TRUE;
}

if (class_exists('SQLite3') === FALSE) {
    $errormsg = "You need PHP SQLite3 to run nsedit";
    $blocklogin = TRUE;
}
 
if (function_exists('openssl_random_pseudo_bytes') === FALSE) {
    $errormsg = "You need PHP compiled with openssl to run nsedit";
    $blocklogin = TRUE;
}


$defaults['defaulttype'] = ucfirst(strtolower($defaults['defaulttype']));

function string_starts_with($string, $prefix)
{
    $length = strlen($prefix);
    return (substr($string, 0, $length) === $prefix);
}

function string_ends_with($string, $suffix)
{
    $length = strlen($suffix);
    if ($length == 0) {
        return true;
    }

    return (substr($string, -$length) === $suffix);
}

function writelog( $string1, $string2 = '' ) {
    l( $string1 . ' ' . $string2 );
}

function jtable_respond($records, $method = 'multiple', $msg = 'Undefined errormessage') {
    $jTableResult = array();
    if ($method == 'error') {
        $jTableResult['Result'] = "ERROR";
        $jTableResult['Message'] = $msg;
    } elseif ($method == 'single') {
        $jTableResult['Result'] = "OK";
        $jTableResult['Record'] = $records;
    } elseif ($method == 'delete') {
        $jTableResult['Result'] = "OK";
    } elseif ($method == 'options') {
        $jTableResult['Result'] = "OK";
        $jTableResult['Options'] = $records;
    } else {
        if (isset($_GET['jtPageSize'])) {
            $jTableResult['TotalRecordCount'] = count($records);
            $records = array_slice($records, $_GET['jtStartIndex'], $_GET['jtPageSize']);
        }
        $jTableResult['Result'] = "OK";
        $jTableResult['Records'] = $records;
        $jTableResult['RecordCount'] = count($records);
    }

    header('Content-Type: application/json');
    print json_encode($jTableResult);
    exit(0);
}

function user_template_list() {
    global $templates;

    $templatelist = array();
    foreach ($templates as $template) {
        if (is_adminuser()
            or (isset($template['owner'])
                and ($template['owner'] == get_sess_user() or $template['owner'] == 'public'))) {
            array_push($templatelist, $template);
        }
    }
    return $templatelist;
}

function user_template_names() {
    $templatenames = array('None' => 'None');
    foreach (user_template_list() as $template) {
        $templatenames[$template['name']] = $template['name'];
    }
    return $templatenames;
}

/* This function was taken from https://gist.github.com/rsky/5104756 to make
it available on older php versions. Thanks! */

if (!function_exists('hash_pbkdf2')) {
    function hash_pbkdf2($algo, $password, $salt, $iterations, $length = 0, $rawOutput = false) {
        // check for hashing algorithm
        if (!in_array(strtolower($algo), hash_algos())) {
            trigger_error(sprintf(
                '%s(): Unknown hashing algorithm: %s',
                __FUNCTION__, $algo
            ), E_USER_WARNING);
            return false;
        }

        // check for type of iterations and length
        foreach (array(4 => $iterations, 5 => $length) as $index => $value) {
            if (!is_numeric($value)) {
                trigger_error(sprintf(
                    '%s() expects parameter %d to be long, %s given',
                    __FUNCTION__, $index, gettype($value)
                ), E_USER_WARNING);
                return null;
            }
        }

        // check iterations
        $iterations = (int)$iterations;
        if ($iterations <= 0) {
            trigger_error(sprintf(
                '%s(): Iterations must be a positive integer: %d',
                __FUNCTION__, $iterations
            ), E_USER_WARNING);
            return false;
        }

        // check length
        $length = (int)$length;
        if ($length < 0) {
            trigger_error(sprintf(
                '%s(): Iterations must be greater than or equal to 0: %d',
                __FUNCTION__, $length
            ), E_USER_WARNING);
            return false;
        }

        // check salt
        if (strlen($salt) > PHP_INT_MAX - 4) {
            trigger_error(sprintf(
                '%s(): Supplied salt is too long, max of INT_MAX - 4 bytes: %d supplied',
                __FUNCTION__, strlen($salt)
            ), E_USER_WARNING);
            return false;
        }

        // initialize
        $derivedKey = '';
        $loops = 1;
        if ($length > 0) {
            $loops = (int)ceil($length / strlen(hash($algo, '', $rawOutput)));
        }

        // hash for each blocks
        for ($i = 1; $i <= $loops; $i++) {
            $digest = hash_hmac($algo, $salt . pack('N', $i), $password, true);
            $block = $digest;
            for ($j = 1; $j < $iterations; $j++) {
                $digest = hash_hmac($algo, $digest, $password, true);
                $block ^= $digest;
            }
            $derivedKey .= $block;
        }

        if (!$rawOutput) {
            $derivedKey = bin2hex($derivedKey);
        }

        if ($length > 0) {
            return substr($derivedKey, 0, $length);
        }

        return $derivedKey;
    }
}

?>
