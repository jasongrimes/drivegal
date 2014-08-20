<?php

namespace Drivegal;

use Drivegal\GalleryFile\Album;
use Drivegal\GalleryFile\AbstractFile as GalleryFile;

class AlbumContents
{
    /** @var Album[] */
    protected $subAlbums;

    /** @var GalleryFile[] */
    protected $files;

    /** @var string */
    protected $title;

    /**
     * An array of breadcrumbs in the format array($relativePath => $title, ...).
     *
     * The current album is not included in the list.
     *
     * @var array
     */
    protected $breadcrumbs = array();

    /**
     * @param mixed $breadcrumbs
     */
    public function setBreadcrumbs($breadcrumbs)
    {
        $this->breadcrumbs = $breadcrumbs;
    }

    /**
     * @return mixed
     */
    public function getBreadcrumbs()
    {
        return $this->breadcrumbs;
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

    /**
     * @param \Drivegal\GalleryFile\AbstractFile[] $files
     */
    public function setFiles($files)
    {
        $this->files = $files;
    }

    /**
     * @return \Drivegal\GalleryFile\AbstractFile[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param \Drivegal\GalleryFile\Album[] $subAlbums
     */
    public function setSubAlbums($subAlbums)
    {
        $this->subAlbums = $subAlbums;
    }

    /**
     * @return \Drivegal\GalleryFile\Album[]
     */
    public function getSubAlbums()
    {
        return $this->subAlbums;
    }
}