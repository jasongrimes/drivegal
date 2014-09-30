<?php

namespace Drivegal;

use Drivegal\GalleryFile\AbstractFile as GalleryFile;

class PhotoStream
{
    /** @var GalleryInfo */
    protected $galleryInfo;
    protected $files = array();
    protected $perPage = 60;

    public function __construct(GalleryInfo $galleryInfo)
    {
        $this->galleryInfo = $galleryInfo;
    }

    /**
     * @param int $perPage
     */
    public function setPerPage($perPage)
    {
        $this->perPage = $perPage;
    }

    /**
     * @return int
     */
    public function getPerPage()
    {
        return $this->perPage;
    }

    /**
     * @return GalleryInfo
     */
    public function getGalleryInfo()
    {
        return $this->galleryInfo;
    }

    /**
     * @param int|null $pg
     * @return PhotoStreamPage
     */
    public function getPage($pg = null)
    {
        if (!$pg) $pg = 1;

        $offset = ($pg - 1) * $this->perPage;

        $slice = array_slice($this->files, $offset, $this->perPage);

        $page = new PhotoStreamPage($pg, $slice);
        if (count($this->files) > ($offset + $this->perPage)) {
            $page->setNextPage($pg + 1);
        }
        if ($pg > 1) {
            $page->setPrevPage($pg - 1);
        }


        return $page;
    }

    /**
     * @param array $files
     * @throws \InvalidArgumentException
     */
    public function appendFiles(array $files)
    {
        foreach ($files as $file) {
            if (!$file instanceof GalleryFile) {
                throw new \InvalidArgumentException('Expected an instance of GalleryFile');
            }
        }

        $this->files = array_merge($this->files, $files);
    }

    /**
     * @return GalleryFile[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    public function count()
    {
        return count($this->files);
    }
}