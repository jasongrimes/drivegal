<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Silex\Application;
use Drivegal\GalleryInfo;
use Drivegal\GalleryService;
use Drivegal\Authenticator;
use Drivegal\Exception\AlbumNotFoundException;
use Drivegal\Exception\ServiceAuthException;
use Drivegal\Exception\ServiceException;
use JasonGrimes\Paginator;

//Request::setTrustedProxies(array('127.0.0.1'));

/** @var Application $app */
global $app;

//
// Helper function to convert a route parameter into a gallery.
//
$galleryProvider = function($galleryInfo, Request $request) use ($app) {
    if ($slug = $request->attributes->get('gallery_slug')) {
        $galleryInfo = $app['gallery.info.mapper']->findBySlug($slug);
    } elseif ($id = $request->attributes->get('google_user_id')) {
        $galleryInfo = $app['gallery.info.mapper']->findByGoogleUserId($id);
    }
    if (!$galleryInfo instanceof GalleryInfo) {
        throw new NotFoundHttpException('Gallery "' . ($slug ?: $id) . '" not found.');
    }

    return $galleryInfo;
};

//
// Error handlers
//
$app->error(function(ServiceAuthException $e, $code) use ($app) {
    return new Response($app['twig']->render('errors/gallery-auth-failed.twig'));
});
$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    $message = '';
    switch (get_class($e)) {
        case 'Drivegal\Exception\ServiceException':
            $code = 503;
            $message = 'The Google Drive server returned an error.';
            break;
    }

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/'.$code.'.html',
        'errors/'.substr($code, 0, 2).'x.html',
        'errors/'.substr($code, 0, 1).'xx.html',
        'errors/default.html',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code, 'message' => $message)), $code);
});

//
// Controller: Home page.
//
$app->get('/', function () use ($app) {
    return $app['twig']->render('index.twig', array());
})
->bind('homepage')
;

//
// Controller: Connect to a Google Drive account (to set up a gallery)
//
$app->get('/setup', function() use ($app) {
    return $app['twig']->render('setup.twig', array('auth_url' => $app['authenticator']->getAuthUrl()));
})
->bind('setup')
;

//
// Controller: Handle OAuth redirects from Google to authorize a gallery.
//
$app->get('/oauth', function(Application $app, Request $request) {
    if ($error_code = $request->query->get('error')) {
        if ($error_code == 'access_denied') { // The user refused to grant access.
            $error = 'The connection attempt was canceled.';
        } else {
            $error = 'Connection failed with the following error: "' . $error_code . '"';
        }
        $app['session']->getFlashBag()->add('error', $error);

        return $app->redirect($app['url_generator']->generate('setup'));
    }

    $auth_result = $app['authenticator']->authorizeGallery($request->query->get('code'));
    if ($auth_result['success']) {
        $app['session']->getFlashBag()->add('just-connected', true);
        // $app['session']->getFlashBag()->add('success', 'Successfully connected to your Google Drive account.');

        return $app->redirect($app['url_generator']->generate('settings'));
    } else {
        $app['session']->getFlashBag()->add('error', $auth_result['error']);

        return $app->redirect($app['url_generator']->generate('setup'));
    }
});

//
// Controller: Deactivate gallery
//
$app->get('/deactivate', function(Application $app) {
    if (!$app['user']) {
        $app['session']->getFlashBag()->add('error', 'Please log in to find your gallery.');

        return $app->redirect($app['url_generator']->generate('login'));
    }

    $galleryInfo = $app['gallery.info.mapper']->findByGoogleUserId($app['user']->getCustomField('googleUserId'));
    if (!$galleryInfo) {
        $app['session']->getFlashBag()->add('error', 'You haven\'t set up a gallery for this Google account yet.');

        return $app->redirect($app['url_generator']->generate('setup'));
    }

    if ($app['gallery']->deactivateGallery($galleryInfo)) {
        $app['session']->getFlashBag()->add('success', 'Disconnected from your Google Drive account and deactivated your gallery.');

        return $app->redirect($app['url_generator']->generate('setup'));
    } else {
        $app['session']->getFlashBag()->add('error', 'Error disconnecting from your Google Drive account.');

        return $app->redirect($app['url_generator']->generate('settings'));
    }


})
->bind('deactivate');

//
// Controller: Manage settings for a gallery.
//
$app->match('/settings', function(Application $app, Request $request) {
    if (!$app['user']) {
        $app['session']->getFlashBag()->add('error', 'You must sign in to edit your settings.');
        return $app->redirect($app['url_generator']->generate('login'));
    }

    /** @var GalleryInfo $galleryInfo */
    $galleryInfo = $app['gallery.info.mapper']->findByGoogleUserId($app['user']->getCustomField('googleUserId'));
    if (!$galleryInfo) {
        $app['session']->getFlashBag()->add('error', 'You don\'t have a gallery set up yet. You can set one up below.');
        return $app->redirect($app['url_generator']->generate('setup'));
    }
    if (!$galleryInfo->getIsActive()) {
        $app['session']->getFlashBag()->add('error', 'Your gallery was deactivated. You can reactivate it by connecting to your Google Drive below.');
        return $app->redirect($app['url_generator']->generate('setup'));
    }

    // Process form submissions.
    if ($request->isMethod('POST')) {
        $galleryInfo->setSlug($request->request->get('slug'));
        $galleryInfo->setGalleryName($request->request->get('galleryName'));

        $errors = $app['gallery']->validateGalleryInfo($galleryInfo);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $app['session']->getFlashBag()->add('error', $error);
            }
        } else {
            // Save the gallery info
            $app['gallery.info.mapper']->save($galleryInfo);
            $app['session']->getFlashBag()->add('success', 'Saved gallery info.');

            return $app->redirect($app['url_generator']->generate('my-gallery'));
        }
    }

    return $app['twig']->render('edit.twig', array(
        'galleryInfo' => $galleryInfo,
        'galleryUrl' => 'http://drivegal.com/' . $galleryInfo->getSlug(),
    ));
})
->bind('settings')
;

//
// Controller: View the top-level album list.
//
$app->get('/{gallery_slug}/album/', function(Application $app, GalleryInfo $galleryInfo) {
    $albumContents = $app['gallery']->getAlbumContents($galleryInfo, '');
    return $app['twig']->render('album.twig', array(
        'galleryName' => $galleryInfo->getGalleryName(),
        'albumTitle' => 'Album list',
        'subAlbums' => $albumContents->getSubAlbums(),
        'files' => '',
        'breadcrumbs' => $albumContents->getBreadcrumbs(),
        'albumUrl' => $app['url_generator']->generate('album-list', array('gallery_slug' => $galleryInfo->getSlug())),
        'streamUrl' => $app['url_generator']->generate('gallery', array('gallery_slug' => $galleryInfo->getSlug())),
        'nextUrl' => '',
        'prevUrl' => '',
        'showLightboxPhoto' => '',
        'gallerySlug' => $galleryInfo->getSlug(),
    ));
})
->assert('gallery_slug', '^[^_][^/]+') // slug can't start with an underscore or contain a slash (we have to specify that manually since we override the default regex).
->convert('galleryInfo', $galleryProvider)
->bind('album-list')
;

//
// Controller: View an album in a gallery
//
$app->get('/{gallery_slug}/album/{album_path}/', function(Application $app, GalleryInfo $galleryInfo, $album_path) {
    try {
        /** @var \Drivegal\AlbumContents $albumContents */
        $albumContents = $app['gallery']->getAlbumContents($galleryInfo, $album_path);
    } catch (AlbumNotFoundException $e) {
        $app['session']->getFlashBag()->add('error', 'Album "' . $album_path . '" not found.');
        return $app->redirect($app['url_generator']->generate('gallery', array('gallery_slug' => $galleryInfo->getSlug())));
    }

    return $app['twig']->render('album.twig', array(
        'galleryName' => $galleryInfo->getGalleryName(),
        'albumTitle' => $albumContents->getTitle(),
        'files' => $albumContents->getFiles(),
        'subAlbums' => $albumContents->getSubAlbums(),
        'breadcrumbs' => $albumContents->getBreadcrumbs(),
        'albumUrl' => $app['url_generator']->generate('album-list', array('gallery_slug' => $galleryInfo->getSlug())),
        'streamUrl' => $app['url_generator']->generate('gallery', array('gallery_slug' => $galleryInfo->getSlug())),
        'showLightboxPhoto' => '',
        'gallerySlug' => $galleryInfo->getSlug(),
    ));
})
->assert('gallery_slug', '^[^_][^/]+') // slug can't start with an underscore or contain a slash (we have to specify that manually since we override the default regex).
->assert('album_path', '.*') // album path *can* contain slashes.
->convert('galleryInfo', $galleryProvider)
->value('album_path', '')
->bind('album')
;

//
// Middleware: Determine the current user.
//
$app->before(function (Symfony\Component\HttpFoundation\Request $request) use ($app) {
    $token = $app['security']->getToken();
    // echo '<pre>' . print_r($token, true);
    $app['user'] = null;

    if ($token && !$app['security.trust_resolver']->isAnonymous($token)) {
        $app['user'] = $token->getUser();
    }
});

//
// Controller: Login
//
$app->get('/login', function () use ($app) {
    $services = array_keys($app['oauth.services']);

    return $app['twig']->render('login.twig', array(
        'login_paths' => array_map(function ($service) use ($app) {
            return $app['url_generator']->generate('_auth_service', array(
                'service' => $service,
                '_csrf_token' => $app['form.csrf_provider']->generateCsrfToken('oauth')
            ));
        }, array_combine($services, $services)),
        'logout_path' => $app['url_generator']->generate('logout', array(
                '_csrf_token' => $app['form.csrf_provider']->generateCsrfToken('logout')
            ))
    ));
})
->bind('login');

//
// Controller: Logout
//
$app->match('/logout', function () {})->bind('logout');

//
// Controller: View my gallery
//
$app->get('/my-gallery', function(Application $app) {
    if (!$app['user']) {
        $app['session']->getFlashBag()->add('error', 'Please log in to find your gallery.');

        return $app->redirect($app['url_generator']->generate('login'));
    }

    $galleryInfo = $app['gallery.info.mapper']->findByGoogleUserId($app['user']->getCustomField('googleUserId'));
    if (!$galleryInfo) {
        $app['session']->getFlashBag()->add('error', 'You haven\'t set up a gallery for this Google account yet.');

        return $app->redirect($app['url_generator']->generate('setup'));
    }

    return $app->redirect($app['url_generator']->generate('gallery', array('gallery_slug' => $galleryInfo->getSlug())));
})
->bind('my-gallery');

//
// Controller: View a gallery's photo stream
//
$app->get('/{gallery_slug}/{pagestr}', function(Application $app, Request $request, GalleryInfo $galleryInfo, $pagestr) {
    $pg = substr($pagestr, 4); // Strip out the leading "page" string.
    $photoStream = $app['gallery']->getPhotoStream($galleryInfo); /** @var \Drivegal\PhotoStream $photoStream */
    $photoStream->setPerPage(60);
    $page = $photoStream->getPage($pg);
    $paginator = new Paginator(
        $photoStream->count(),
        $photoStream->getPerPage(),
        $pg,
        $app['url_generator']->generate('gallery', array('gallery_slug' => $galleryInfo->getSlug())) . '/page(:num)'
    );

    return $app['twig']->render('stream.twig', array(
        'galleryName' => $galleryInfo->getGalleryName(),
        'gallerySlug' => $galleryInfo->getSlug(),
        'files' => $page->getFiles(),
        'paginator' => $paginator,
        'showLightboxPhoto' => $request->query->get('show'),
    ));
})
->assert('gallery_slug', '^[^_][^/]+') // slug can't start with an underscore or contain a slash.
->assert('pagestr', '^page\d+')
->convert('galleryInfo', $galleryProvider)
->value('pagestr', 'page1')
->bind('gallery')
;

