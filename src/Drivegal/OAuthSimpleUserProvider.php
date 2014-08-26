<?php

namespace Drivegal;

use Gigablah\Silex\OAuth\Security\Authentication\Token\OAuthTokenInterface;
use Gigablah\Silex\OAuth\Security\Authentication\Token\OAuthToken;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use SimpleUser\UserManager;
use Gigablah\Silex\OAuth\Security\User\Provider\OAuthUserProviderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Drivegal\Exception\CreateUserException;

class OAuthSimpleUserProvider implements OAuthUserProviderInterface, UserProviderInterface
{
    /**
     * @var \SimpleUser\UserManager
     */
    protected $userManager;

    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * Loads a user based on OAuth credentials.
     *
     * @param OAuthTokenInterface $token
     *
     * @return UserInterface|null
     */
    public function loadUserByOAuthCredentials(OAuthTokenInterface $token)
    {
        return $this->loadAndUpdateUser($token->getUid(), $token->getEmail(), $token->getUser());
    }

    /**
     * Load a user based on Google user ID. Update the name and email, if necessary.
     * If a user with that ID doesn't exist, create one and store it.
     *
     * @param string $googleUserId
     * @param string $email
     * @param string $name
     * @return null|\SimpleUser\User
     * @throws CreateUserException
     */
    public function loadAndUpdateUser($googleUserId, $email, $name)
    {
        $user = $this->userManager->findOneBy(array('customFields' => array('googleUserId' => $googleUserId)));

        if ($user) {
            $updated = false;
            if ($user->getEmail() != $email) {
                $user->setEmail($email);
                $updated = true;
            }
            if ($user->getName() == $name) {
                $user->setName($name);
                $updated = true;
            }
            if ($updated) {
                $this->userManager->update($user);
            }
        } else {
            // A user with this ID doesn't already exist. Try to create one.
            if ($this->userManager->findBy(array('email' => $email))) {
                throw new UserDuplicateEmailException('A user account already exists with the email address "' . $email . '"');
            }
            $user = $this->userManager->createUser($email, '', $name);
            $user->setCustomField('googleUserId', $googleUserId);
            $this->userManager->insert($user);
        }

        return $user;

    }

    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string $username The username
     *
     * @return UserInterface
     *
     * @see UsernameNotFoundException
     *
     * @throws UsernameNotFoundException if the user is not found
     *
     */
    public function loadUserByUsername($username)
    {
        return $this->userManager->loadUserByUsername($username);
    }

    /**
     * Refreshes the user for the account interface.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     * @param UserInterface $user
     *
     * @return UserInterface
     *
     * @throws UnsupportedUserException if the account is not supported
     */
    public function refreshUser(UserInterface $user)
    {
        return $this->userManager->refreshUser($user);
    }

    /**
     * Whether this provider supports the given user class
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass($class)
    {
        return $this->userManager->supportsClass($class);
    }
}