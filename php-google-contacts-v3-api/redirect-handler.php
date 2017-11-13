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

$conf->modules_parts['triggers']=array();

dol_include_once('/googlecontactsync/class/gcs.class.php');

define('GCS_NO_TOKEN',true);

require __DIR__.'/vendor/autoload.php';
use rapidweb\googlecontacts\helpers\GoogleHelper;
$client = GoogleHelper::getClient();
GoogleHelper::authenticate($client, $code);
$accessToken = GoogleHelper::getAccessToken($client);

$PDOdb=new \TPDOdb;
$token = new \TGCSToken;

$token->loadByObject($PDOdb, $fk_user, 'user');
$token->token = $accessToken->access_token;
$token->refresh_token = $accessToken->refresh_token;
$token->type_object='user';
$token->fk_object = $fk_user;
$PDOdb->debug = true;
$token->save($PDOdb);
//exit;

$user_card_url = (DOL_VERSION < 3.6) ? '/user/fiche.php' : '/user/card.php';
header('location:'.dol_buildpath($user_card_url,1).'?id='.$fk_user);
