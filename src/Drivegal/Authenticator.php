<?php

namespace Drivegal;

use Google_Client;
use Google_Exception;
use Google_Auth_Exception;
use Google_Service_Oauth2;
use Google_Service_Oauth2_Userinfoplus;

class Authenticator
{
    /** @var GalleryInfoMapper */
    protected $galleryInfoMapper;

    protected $client_id;
    protected $client_secret;
    protected $redirect_uri;
    protected $scopes;

    /**
     * @param GalleryInfoMapper $galleryInfoMapper
     * @param string $client_id
     * @param string $client_secret
     * @param string $redirect_uri
     * @param array $scopes
     */
    public function __construct(GalleryInfoMapper $galleryInfoMapper, $client_id, $client_secret, $redirect_uri, array $scopes)
    {
        $this->galleryInfoMapper = $galleryInfoMapper;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->redirect_uri = $redirect_uri;
        $this->scopes = $scopes;
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
            $galleryInfo = $this->galleryInfoMapper->findByGoogleUserId($user_info->getId());
            if (!$galleryInfo instanceof GalleryInfo) {
                $galleryInfo = $this->createGalleryInfoFromUserInfo($user_info);
            }

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
        } catch (Google_Auth_Exception $e) {
            return array('success' => false, 'error' => 'Google reported an authentication error: "' . $e->getMessage() . '"');
        } catch (Google_Exception $e) {
            return array('success' => false, 'error' => 'Google reported an error: ' . $e->getMessage());
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

}