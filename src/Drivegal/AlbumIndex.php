<?php

namespace Drivegal;

use Drivegal\GalleryFile\Album;

class AlbumIndex
{
    /** @var Album[] $albums */
    protected $albums = array();

    /** @var Album[][] */
    protected $parent_children = array();

    /** @var Album[][] */
    protected $child_parents = array();

    /** @var Album[] */
    protected $orphans = array();

    public function buildIndex()
    {
        // Build family trees for album/folder hierarchies.
        foreach ($this->albums as $album) {
            $is_orphan = true;
            foreach ($album->getParentIds() as $parentId) {
                if ($this->hasId($parentId)) {
                    $is_orphan = false;
                    $this->parent_children[$parentId][] = $album;
                    $this->child_parents[$album->getId()] = $this->get($parentId);
                }
                if ($is_orphan) {
                    $this->orphans[$album->getId()] = $album;
                }
            }
        }
    }

    /**
     * @param Album $album
     */
    public function add(Album $album)
    {
        $this->albums[$album->getId()] = $album;
    }

    /**
     * @param $id
     * @return Album
     */
    public function get($id)
    {
        return $this->albums[$id];
    }

    /**
     * @param $id
     * @return bool
     */
    public function hasId($id)
    {
        return in_array($id, $this->getIds());
    }

    /**
     * @param Album $album
     * @return bool
     */
    public function hasAlbum(Album $album)
    {
        return $this->hasId($album->getId());
    }

    /**
     * @return array
     */
    public function getIds()
    {
        return array_keys($this->albums);
    }

    /**
     * @param Album $album
     * @return Album[]
     */
    public function getSubAlbums(Album $album = null)
    {
        $subAlbums = array();

        if ($album === null) {
            $subAlbums = $this->orphans;
        } else if (array_key_exists($album->getId(), $this->parent_children)) {
            $subAlbums = $this->parent_children[$album->getId()];
        }

        // Order albums by name
        uasort($subAlbums, function($a, $b) {
            return strcmp($a->getTitle(), $b->getTitle());
        });

        return $subAlbums;
    }

    /**
     * @param string $albumPath
     * @return Album|null
     */
    public function findByPath($albumPath)
    {
        $album = null;

        $slugs = explode('/', rtrim($albumPath, '/'));
        $isFirstSlug = true;
        foreach ($slugs as $slug) {
            $album = $isFirstSlug ? $this->findOrphanBySlug($slug) : $this->findChildBySlug($album, $slug);
            if (!$album) {
                return null;
            }
            $isFirstSlug = false;
        }

        return $album;
    }

    /**
     * @param string $slug
     * @return Album|null
     */
    protected function findOrphanBySlug($slug)
    {
        foreach ($this->orphans as $album) {
            if ($album->getSlug() == $slug) {
                return $album;
            }
        }

        return null;
    }

    /**
     * @param Album $parent
     * @param string $slug
     * @return Album|null
     */
    protected function findChildBySlug(Album $parent, $slug)
    {
        if (is_array($this->parent_children[$parent->getId()])) {
            foreach ($this->parent_children[$parent->getId()] as $album) {
                if ($album->getSlug() == $slug) {
                    return $album;
                }
            }
        }

        return null;
    }


}