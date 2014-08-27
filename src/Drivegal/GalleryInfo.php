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
    protected $is_active = true;

    /** @var string  */
    protected $time_created;

    public function __construct($googleUserId, $slug, $galleryName)
    {
        $this->google_user_id = $googleUserId;
        $this->galleryName = $galleryName;
        $this->slug = $slug;
        $this->time_created = date('Y-m-d H:i:s');
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
     * @param boolean $is_active
     */
    public function setIsActive($is_active)
    {
        $this->is_active = $is_active;
    }

    /**
     * @return boolean
     */
    public function getIsActive()
    {
        return $this->is_active;
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
     * @param string $time_created
     */
    public function setTimeCreated($time_created)
    {
        $this->time_created = $time_created;
    }

    /**
     * @return string
     */
    public function getTimeCreated()
    {
        return $this->time_created;
    }
}