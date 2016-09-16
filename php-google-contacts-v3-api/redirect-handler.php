<?php

// This page handles the redirect from the authorisation page. It will authenticate your app and
// retrieve the refresh token which is used for long term access to Google Contacts. You should
// add this refresh token to the 'config.json' file.



if (!isset($_GET['code'])) {
    die('No code URL paramete present.');
}

require '../config.php';
$fk_user = $_SESSION['GCS_fk_user'];
$code = $_GET['code'];

dol_include_once('/googlecontactsync/class/gcs.class.php');

$PDOdb=new TPDOdb;
$token = new TGCSToken;

$token->loadByObject($PDOdb, $fk_user, 'user');
$token->token = $code;
$token->type_object='user';
$token->fk_object = $fk_user;
$token->save($PDOdb);
