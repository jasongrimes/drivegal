<?php

namespace Drivegal;

class GalleryInfo
{
    /** @var int */
    protected $google_user_id;

    /** @var string */
    protected $slug;

    /** @var string */
    protected $galleryName;

    /** @var string */
    protected $credentials;

    /** @var boolean */
    protected $isActive = true;

    /** @var string  */
    protected $timeCreated;

    public function __construct($googleUserId, $slug, $galleryName)
    {
        $this->google_user_id = $googleUserId;
        $this->galleryName = $galleryName;
        $this->slug = $slug;
        $this->timeCreated = date('Y-m-d H:i:s');
    }

    //
    // Accessors
    //

    /**
     * @param string $galleryName
     */
    public function setGalleryName($galleryName)
    {
        $this->galleryName = $galleryName;
    }

    /**
     * @return string
     */
    public function getGalleryName()
    {
        return $this->galleryName;
    }


    /**
     * @param string $credentials
     */
    public function setCredentials($credentials)
    {
        $this->credentials = $credentials;
    }

    /**
     * @return string
     */
    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * @param int $google_user_id
     */
    public function setGoogleUserId($google_user_id)
    {
        $this->google_user_id = $google_user_id;
    }

    /**
     * @return int
     */
    public function getGoogleUserId()
    {
        return $this->google_user_id;
    }

    /**
     * @param boolean $isActive
     */
    public function setIsActive($isActive)
    {
        $this->isActive = $isActive;
    }

    /**
     * @return boolean
     */
    public function getIsActive()
    {
        return $this->isActive;
    }

    /**
     * @param string $slug
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param string $timeCreated
     */
    public function setTimeCreated($timeCreated)
    {
        $this->timeCreated = $timeCreated;
    }

    /**
     * @return string
     */
    public function getTimeCreated()
    {
        return $this->timeCreated;
    }
}