<?php

// After filling in the clientID, clientSecret and redirectUri (within 'config.json'), you should visit this page
// to get the authorisation URL.

// Note that the redirectUri value should point towards a hosted version of 'redirect_handler.php'.

require '../config.php';
dol_include_once('/googlecontactsync/class/gcs.class.php');

$fk_user_gcs = (int)GETPOST('fk_user');

require_once 'vendor/autoload.php';

use rapidweb\googlecontacts\helpers\GoogleHelper;

$client = GoogleHelper::getClient();



$authUrl = GoogleHelper::getAuthUrl($client);
//var_dump($authUrl,$client);

$_SESSION['GCS_fk_user'] = $fk_user_gcs;

header('location:'.$authUrl);
