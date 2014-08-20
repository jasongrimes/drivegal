<?php

namespace Drivegal\GalleryFile;

abstract class AbstractFile
{
    protected $id;
    protected $title;
    protected $description;
    protected $parentIds;
    protected $thumbnailUrl;
    protected $bigThumbnailUrl;
    protected $originalFileUrl;
    protected $date;
    protected $mimeType;

    /**
     * @param mixed $mimeType
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;
    }

    /**
     * @return mixed
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * The file type. One of "album", "image", or "movie".
     *
     * @return string
     */
    abstract public function getType();

    /**
     * @param mixed $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * @return mixed
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $originalFileUrl
     */
    public function setOriginalFileUrl($originalFileUrl)
    {
        $this->originalFileUrl = $originalFileUrl;
    }

    /**
     * @return mixed
     */
    public function getOriginalFileUrl()
    {
        return $this->originalFileUrl;
    }

    /**
     * @param mixed $thumbnailUrl
     */
    public function setThumbnailUrl($thumbnailUrl)
    {
        $this->thumbnailUrl = $thumbnailUrl;
    }

    /**
     * @return mixed
     */
    public function getThumbnailUrl()
    {
        return $this->thumbnailUrl;
    }

    /**
     * @param mixed $bigThumbnailUrl
     */
    public function setBigThumbnailUrl($bigThumbnailUrl)
    {
        $this->bigThumbnailUrl = $bigThumbnailUrl;
    }

    /**
     * @return mixed
     */
    public function getBigThumbnailUrl()
    {
        return $this->bigThumbnailUrl;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $parentIds
     */
    public function setParentIds($parentIds)
    {
        $this->parentIds = $parentIds;
    }

    /**
     * @return mixed
     */
    public function getParentIds()
    {
        return $this->parentIds;
    }

    /**
     * @param mixed $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }
}