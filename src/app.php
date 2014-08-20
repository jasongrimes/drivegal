<?php

use Cocur\Slugify\Bridge\Silex\SlugifyServiceProvider;
use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Drivegal\Authenticator;
use Drivegal\GalleryInfoMapper;
use Drivegal\GalleryService;


$app = new Application();
$app->register(new UrlGeneratorServiceProvider());
$app->register(new ValidatorServiceProvider());
$app->register(new ServiceControllerServiceProvider());
$app->register(new TwigServiceProvider());
$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    // add custom globals, filters, tags, ...

    return $twig;
}));

$app->register(new SlugifyServiceProvider());
$app->register(new SessionServiceProvider());

$app['authenticator'] = $app->share(function($app) {
    return new Authenticator(
        $app['gallery.info.mapper'],
        $app['drivegal.client_id'],
        $app['drivegal.client_secret'],
        $app['drivegal.redirect_uri'],
        $app['drivegal.scopes']
    );
});

$app['gallery.info.mapper'] = $app->share(function($app) {
    return new GalleryInfoMapper($app['slugify']);
});
$app['gallery'] = $app->share(function($app) {
   return new GalleryService($app['authenticator'], $app['gallery.info.mapper'], $app['slugify']);
});

return $app;
