<?php

namespace Drivegal\GalleryFile;

class Album extends AbstractFile
{
    protected $slug;

    public function __construct($id, $title, $slug, $parentIds)
    {
        $this->id = $id;
        $this->title = $title;
        $this->slug = $slug;
        $this->parentIds = $parentIds;
    }

    /**
     * @param mixed $slug
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    /**
     * @return mixed
     */
    public function getSlug()
    {
        return $this->slug;
    }

    public function getType()
    {
        return 'album';
    }
}