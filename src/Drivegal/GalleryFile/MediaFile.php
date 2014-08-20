<?php

namespace Drivegal\GalleryFile;

abstract class MediaFile extends AbstractFile
{

    protected $originalFilename;
    protected $cameraMake;
    protected $cameraModel;

    public function __construct($id, $title, $thumbnailUrl, $bigThumbnailUrl)
    {
        $this->id = $id;
        $this->title = $title;
        $this->thumbnailUrl = $thumbnailUrl;
        $this->bigThumbnailUrl = $bigThumbnailUrl;
    }

    /**
     * @param mixed $cameraMake
     */
    public function setCameraMake($cameraMake)
    {
        $this->cameraMake = $cameraMake;
    }

    /**
     * @return mixed
     */
    public function getCameraMake()
    {
        return $this->cameraMake;
    }

    /**
     * @param mixed $cameraModel
     */
    public function setCameraModel($cameraModel)
    {
        $this->cameraModel = $cameraModel;
    }

    /**
     * @return mixed
     */
    public function getCameraModel()
    {
        return $this->cameraModel;
    }

    /**
     * @return string
     */
    public function getCaption()
    {
        $caption = '';

        if (!$this->description || $this->originalFilename != $this->title) {
            $caption = $this->title;
        }

        if ($caption && $this->description) {
            $caption .= ' - ';
        }

        $caption .= $this->description;

        return trim($caption);
    }

    /**
     * @param mixed $originalFilename
     */
    public function setOriginalFilename($originalFilename)
    {
        $this->originalFilename = $originalFilename;
    }

    /**
     * @return mixed
     */
    public function getOriginalFilename()
    {
        return $this->originalFilename;
    }

}
