<?php

include_once('config.inc.php');
include_once('misc.inc.php');

session_start();

function is_logged_in() {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == "true") {
        return TRUE;
    } else {
        return FALSE;
    }
}

function set_logged_in($login_user) {
    $_SESSION['logged_in'] = 'true';
    $_SESSION['username']  = $login_user;
}

function set_is_adminuser() {
    $_SESSION['is_adminuser'] = 'true';
}

function is_adminuser() {
    if (isset($_SESSION['is_adminuser']) && $_SESSION['is_adminuser'] == 'true') {
        return TRUE;
    } else {
        return FALSE;
    }
}

function get_sess_user() {
    return $_SESSION['username'];
}

function logout() {
    session_destroy();
}

function try_login() {
    if (isset($_POST['username']) and isset($_POST['password'])) {
        if (valid_user($_POST['username']) === FALSE) {
            return FALSE;
        }
        $do_local_auth = 1;

        if (isset($wefactapiurl) && isset($wefactapikey)) {
            $wefact = do_wefact_auth($_POST['username'], $_POST['password']);
            if ($wefact === FALSE) {
                return FALSE;
            }
            if ($wefact != -1) {
                $do_local_auth = 0;
            }
        }

        if ($do_local_auth == 1) {
            if (do_db_auth($_POST['username'], $_POST['password']) === FALSE) {
                return FALSE;
            }
        }

        $userinfo = get_user_info($_POST['username']);

        set_logged_in($_POST['username']);
        if (isset($userinfo['isadmin']) && $userinfo['isadmin'] == 1) {
            set_is_adminuser();
        }
        return TRUE;
    }

    return FALSE;
}

?>
