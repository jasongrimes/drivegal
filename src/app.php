<?php

use Cocur\Slugify\Bridge\Silex\SlugifyServiceProvider;
use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\DoctrineServiceProvider;
use Drivegal\Authenticator;
use Drivegal\GalleryInfoMapper;
use Drivegal\GalleryService;
use Drivegal\OAuthSimpleUserProvider;
use CHH\Silex\CacheServiceProvider;
use Kilte\Silex\Pagination\PaginationServiceProvider;

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
        $app['drivegal.scopes'],
        $app['oauth_user_provider'],
        $app['security']
    );
});
$app['oauth_user_provider'] = $app->share(function($app) { return new OAuthSimpleUserProvider($app['user.manager']); });

$app['gallery.info.mapper'] = $app->share(function($app) {
    return new GalleryInfoMapper($app['db'], $app['slugify']);
});
$app['gallery'] = $app->share(function($app) {
   return new GalleryService($app['authenticator'], $app['gallery.info.mapper'], $app['slugify'], $app['cache']);
});

$app->register(new DoctrineServiceProvider());
$app->register(new Gigablah\Silex\OAuth\OAuthServiceProvider());
$app->register(new Silex\Provider\FormServiceProvider()); // Provides CSRF token generation for the OAuth service provider.
$app->register(new Silex\Provider\SecurityServiceProvider(), array(
    'security.firewalls' => array(
        'default' => array(
            'pattern' => '^/',
            'anonymous' => true,
            'remember_me' => array(
                'always_remember_me' => true,
            ),
            'oauth' => array(
                //'login_path' => '/auth/{service}',
                //'callback_path' => '/auth/{service}/callback',
                //'check_path' => '/auth/{service}/check',
                'failure_path' => '/login',
                'with_csrf' => true,
                'always_use_default_target_path' => true,
                'default_target_path' => '/my-gallery',
            ),
            'logout' => array(
                'logout_path' => '/logout',
                // 'with_csrf' => true,
                'target' => '/login', // @todo This doesn't seem to be working...
            ),
            // 'users' => new Gigablah\Silex\OAuth\Security\User\Provider\OAuthInMemoryUserProvider(),
            'users' => $app->share(function($app) { return $app['oauth_user_provider']; }),
        )
    ),
    'security.access_rules' => array(
        array('^/auth', 'ROLE_USER')
    )
));
$app->register(new \Silex\Provider\RememberMeServiceProvider());
$app->register(new SimpleUser\UserServiceProvider());

$app->register(new CacheServiceProvider, array(
    'cache.options' => array("default" => array(
        "driver" => "apc"
    ))
));

return $app;
