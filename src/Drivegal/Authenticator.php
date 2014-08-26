<?php

namespace Drivegal;

use Drivegal\Exception\CreateUserException;
use Google_Client;
use Google_Exception;
use Google_Auth_Exception;
use Google_Service_Oauth2;
use Google_Service_Oauth2_Userinfoplus;
use SimpleUser\User;
use Symfony\Component\Security\Core\SecurityContext;
use Gigablah\Silex\OAuth\Security\Authentication\Token\OAuthToken;
use Drivegal\OAuthSimpleUserProvider;

class Authenticator
{
    /** @var GalleryInfoMapper */
    protected $galleryInfoMapper;
    protected $client_id;
    protected $client_secret;
    protected $redirect_uri;
    protected $scopes;
    protected $userProvider;
    protected $securityContext;

    /**
     * @param GalleryInfoMapper $galleryInfoMapper
     * @param $client_id
     * @param $client_secret
     * @param $redirect_uri
     * @param array $scopes
     * @param OAuthSimpleUserProvider $userProvider
     * @param SecurityContext $securityContext
     */
    public function __construct(
        GalleryInfoMapper $galleryInfoMapper,
        $client_id,
        $client_secret,
        $redirect_uri,
        array $scopes,
        OAuthSimpleUserProvider $userProvider,
        SecurityContext $securityContext)
    {
        $this->galleryInfoMapper = $galleryInfoMapper;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->redirect_uri = $redirect_uri;
        $this->scopes = $scopes;
        $this->userProvider = $userProvider;
        $this->securityContext = $securityContext;
    }

    /**
     * @param string|null $state
     * @return string
     */
    public function getAuthUrl($state = null)
    {
        $client = $this->createClient();
        if ($state !== null) {
            $client->setState($state);
        }

        return $client->createAuthUrl();
    }

    /**
     * @return Google_Client
     */
    public function createClient()
    {
        $client = new Google_Client();

        $client->setClientId($this->client_id);
        $client->setClientSecret($this->client_secret);
        $client->setRedirectUri($this->redirect_uri);
        // Should the following only be set in getAuthUrl() ?
        $client->setAccessType('offline');
        $client->setApprovalPrompt('force');
        $client->setScopes($this->scopes);

        return $client;
    }

    /**
     * @param string $auth_code
     * @return array An array of result data, in the form array(success => true|false, error => {error message})
     */
    public function authorizeGallery($auth_code)
    {
        try {
            // Exchange the authorization code for OAuth2 credentials.
            $client = $this->createClient();
            $credentials = $client->authenticate($auth_code);
            $user_info = $this->getUserInfo($credentials);
        } catch (Google_Auth_Exception $e) {
            return array('success' => false, 'error' => 'Google reported an authentication error: "' . $e->getMessage() . '"');
        } catch (Google_Exception $e) {
            return array('success' => false, 'error' => 'Google reported an error: ' . $e->getMessage());
        }

        $galleryInfo = $this->galleryInfoMapper->findByGoogleUserId($user_info->getId());
        if (!$galleryInfo instanceof GalleryInfo) {
            $galleryInfo = $this->createGalleryInfoFromUserInfo($user_info);
        }

        try {
            $user = $this->userProvider->loadAndUpdateUser($user_info->getId(), $user_info->getEmail(), $user_info->getName());
        } catch (CreateUserException $e) {
            return array('success' => false, 'error' => 'Error creating a user account: ' . $e->getMessage());
        }
        /*
        // Create a user account if there isn't one already, and log them in.
        $user = $this->userManager->findOneBy(array('customFields' => array('googleUserId' => $user_info->getId())));
        if (!$user) {
            $user = $this->userManager->createUser($user_info->getEmail(), '', $user_info->getName());
            $user->setCustomField('googleUserId', $user_info->getId());
            $this->userManager->insert($user);
        }
        */
        $this->setViewerLoggedInAs($user);

        // Save the refresh token if we got one.
        $credentials_array = json_decode($credentials, true);
        if (isset($credentials_array['refresh_token'])) {
            $galleryInfo->setCredentials($credentials);
            $this->galleryInfoMapper->save($galleryInfo);

            return array('success' => true, 'galleryInfo' => $galleryInfo);

        } else {
            $credentials_array = json_decode($galleryInfo->getCredentials(), true);
            if ($credentials_array != null && isset($credentials_array['refresh_token'])) {

                return array('success' => true, 'galleryInfo' => $galleryInfo);
            }
        }

        // No refresh token was received, and we don't have one already.
        return array('success' => false, 'error' => 'No refresh token was received.'); // 'auth_url' => $this->getAuthUrl());
    }

    /**
     * Send a request to the UserInfo API to retrieve the user's information.
     *
     * @param string $credentials
     * @return \Google_Service_Oauth2_Userinfoplus User's information.
     */
    public function getUserInfo($credentials)
    {
        $client = $this->createClient();
        $client->setAccessToken($credentials);

        $user_info_service = new Google_Service_Oauth2($client);

        return $user_info_service->userinfo->get();
    }

    /**
     * @param Google_Service_Oauth2_Userinfoplus $user_info
     * @return GalleryInfo
     */
    protected function createGalleryInfoFromUserInfo(Google_Service_Oauth2_Userinfoplus $user_info)
    {

        $galleryInfo = $this->galleryInfoMapper->createGalleryInfo($user_info->getId(), $user_info->getName());
        $galleryInfo->setEmail($user_info->getEmail());

        return $galleryInfo;
    }

    /**
     * Log the current viewer in as the given user.
     *
     * @param User $user
     */
    protected function setViewerLoggedInAs(User $user)
    {
        $firewallName = 'default';


        $authenticatedToken = new OAuthToken($firewallName, $user->getRoles());
        // $authenticatedToken->setAccessToken($token->getAccessToken());
        $authenticatedToken->setService('google');
        $authenticatedToken->setUid($user->getCustomField('googleUserId'));
        $authenticatedToken->setAuthenticated(true);
        $authenticatedToken->setUser($user);

        $this->securityContext->setToken($authenticatedToken);

    }

}