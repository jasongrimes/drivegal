<?php
/*
 * Template for a local configuration file with data that should not be committed to version control.
 *
 * Copy this to local.php and customize it for the local environment.
 */

// Credentials for Google Drive API.
$app['drivegal.client_id'] = '';
$app['drivegal.client_secret'] = '';
$app['drivegal.redirect_uri'] = '';

// Credentials for Google OAuth2 login.
$app['oauth.services'] = array(
    'google' => array(
        'key' => $app['drivegal.client_id'],
        'secret' => $app['drivegal.client_secret'],
        'scope' => array(
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile'
        ),
        'user_endpoint' => 'https://www.googleapis.com/oauth2/v1/userinfo'
    ),
);

$app['db.options'] = array(
    'driver'   => 'pdo_mysql',
    'dbname' => '',
    'host' => '',
    'user' => '',
    'password' => '',
);
