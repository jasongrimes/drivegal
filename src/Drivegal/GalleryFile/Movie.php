<?php

namespace Drivegal\GalleryFile;

class Movie extends MediaFile
{
    protected $playUrl;

    /**
     * @param mixed $playUrl
     */
    public function setPlayUrl($playUrl)
    {
        $this->playUrl = $playUrl;
    }

    /**
     * @return mixed
     */
    public function getPlayUrl()
    {
        return $this->playUrl;
    }

    public function getType()
    {
        return 'movie';
    }

}