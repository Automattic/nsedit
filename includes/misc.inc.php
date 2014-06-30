<?php

include('config.inc.php');

function get_db() {
    global $authdb;

    $db = new SQLite3($authdb, SQLITE3_OPEN_READWRITE);
    $db->exec('PRAGMA foreign_keys = 1');

    return $db;
}

function gen_pw() {
    $password = exec('/usr/bin/pwgen -s -B -c -n 10 -1');
    return $password;
}

function get_all_users() {
    $db = get_db();
    $r = $db->query('SELECT id, emailaddress, isadmin FROM users');
    $ret = array();
    while ($row = $r->fetchArray()) {
        $row['emailaddress'] = htmlspecialchars($row['emailaddress']);
        array_push($ret, $row);
    }

    return $ret;
}

function get_pw($username) {
    $db = get_db();
    $pw = $db->querySingle("SELECT password FROM users WHERE emailaddress = '".$username."'");
    $db->close();
    return $pw;
}

function add_user($username, $isadmin = '0', $password = FALSE) {
    if ($password === FALSE or $password == "") {
        $password = get_pw($username);
    } elseif (!preg_match('/\$6\$/', $password)) {
        $salt = bin2hex(openssl_random_pseudo_bytes(16));
        $password = crypt($password, '$6$'.$salt);
    }

    $db = get_db();
    $ret = $db->exec("INSERT OR REPLACE INTO users (emailaddress, password, isadmin) VALUES ('".$username."', '".$password."', $isadmin)");
    $db->close();

    return $ret;
}

function delete_user($id) {
    $db = get_db();
    $ret = $db->exec("DELETE FROM users WHERE id = $id");
    $db->close();

    return $ret;
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
    } else {
        if (isset($_GET['jtPageSize'])) {
            $jTableResult['TotalRecordCount'] = count($records);
            $records = array_slice($records, $_GET['jtStartIndex'], $_GET['jtPageSize']);
        }
        $jTableResult['Result'] = "OK";
        $jTableResult['Records'] = $records;
        $jTableResult['RecordCount'] = count($records);
    }

    print json_encode($jTableResult);
    exit(0);
}

function valid_user($name) {
    return ( bool ) preg_match( "/^[a-z0-9@_.-]+$/i" , $name );
}


?>
