<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Silex\Application;
use Drivegal\GalleryInfo;
use Drivegal\Authenticator;
use Drivegal\Exception\AlbumNotFoundException;
use Drivegal\Exception\ServiceAuthException;
use Drivegal\Exception\ServiceException;

//Request::setTrustedProxies(array('127.0.0.1'));

/** @var Application $app */
global $app;

//
// Helper function to convert a route parameter into a gallery.
//
$gallery_provider = function($galleryInfo, Request $request) use ($app) {
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
// Error handler
//
$app->error(function (\Exception $e, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    switch (get_class($e)) {
        case 'Drivegal\Exception\AlbumNotFoundException':
            $code = 404;
            break;
        case 'Drivegal\Exception\ServiceAuthException':
            $code = 502;
            $message = 'Authentication to Google Drive failed. If this is your gallery, please try re-connecting it to your Google Drive account.';
            break;
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

// Test the error handler
$app->get('/error/{code}/', function(Application $app, $code) {
    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/'.$code.'.html',
        'errors/'.substr($code, 0, 2).'x.html',
        'errors/'.substr($code, 0, 1).'xx.html',
        'errors/default.html',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
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


// Controller: Handle OAuth redirects from Google.
$app->get('/oauth', function(Application $app, Request $request) {
    $auth_result = $app['authenticator']->authorizeGallery($request->query->get('code'));
    if ($auth_result['success']) {
        // $app['session']->getFlashBag()->add('just-connected', true);
        // return $app->redirect('/edit-gallery/' . $auth_result['galleryInfo']->getGoogleUserId());
        $app['session']->getFlashBag()->add('success', 'Successfully connected to your Google Drive account.');
        return $app->redirect('/' . $auth_result['galleryInfo']->getSlug());
    } else {
        $app['session']->getFlashBag()->add('error', $auth_result['error']);
        return $app->redirect($app['url_generator']->generate('setup'));
    }
});

//
// Controller: Edit info about a gallery.
//
$app->get('/edit-gallery/{google_user_id}', function(Application $app, GalleryInfo $galleryInfo) {
    return $app['twig']->render('edit.twig', array('galleryInfo' => $galleryInfo));
})->convert('galleryInfo', $gallery_provider);

//
// Controller: View an album in a gallery
//
$app->get('/{gallery_slug}/{album_path}/', function(Application $app, GalleryInfo $galleryInfo, $album_path) {
    $albumContents = $app['gallery']->getAlbumContents($galleryInfo, $album_path);
    return $app['twig']->render('album.twig', array(
        'galleryName' => $galleryInfo->getGalleryName(),
        'albumTitle' => $albumContents->getTitle(),
        'files' => $albumContents->getFiles(),
        'subAlbums' => $albumContents->getSubAlbums(),
        'breadcrumbs' => $albumContents->getBreadcrumbs(),
    ));
})
->assert('gallery_slug', '^[^_][^/]+') // slug can't start with an underscore or contain a slash (we have to specify that manually since we override the default regex).
->assert('album_path', '.+') // album path *can* contain slashes.
->convert('galleryInfo', $gallery_provider)
;

//
// Controller: View a gallery.
//
$app->get('/{gallery_slug}/', function(Application $app, GalleryInfo $galleryInfo) {
    $albumContents = $app['gallery']->getAlbumContents($galleryInfo, '');
    return $app['twig']->render('gallery.twig', array(
        'galleryName' => $galleryInfo->getGalleryName(),
        'subAlbums' => $albumContents->getSubAlbums(),
    ));
})
->assert('gallery_slug', '^[^_][^/]+') // slug can't start with an underscore or contain a slash.
->convert('galleryInfo', $gallery_provider)
;
