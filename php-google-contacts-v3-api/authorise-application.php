<?php

// After filling in the clientID, clientSecret and redirectUri (within 'config.json'), you should visit this page
// to get the authorisation URL.

// Note that the redirectUri value should point towards a hosted version of 'redirect_handler.php'.

require '../config.php';
dol_include_once('/googlecontactsync/class/gcs.class.php');

require_once 'vendor/autoload.php';

use rapidweb\googlecontacts\helpers\GoogleHelper;

define('GCS_NO_TOKEN',true);

$client = GoogleHelper::getClient();
$authUrl = GoogleHelper::getAuthUrl($client);
//var_dump($authUrl,$client);exit;

$_SESSION['GCS_fk_user'] = (int)GETPOST('fk_user');

header('location:'.$authUrl);
