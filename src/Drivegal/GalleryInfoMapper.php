<?php

namespace Drivegal;

use Cocur\Slugify\Slugify;

class GalleryInfoMapper
{
    /** @var Slugify */
    protected $slugify;

    /**
     * @param Slugify $slugify
     */
    public function __construct(Slugify $slugify)
    {
        $this->slugify = $slugify;
    }

    /**
     * @param string $slug
     * @return GalleryInfo|null
     */
    public function findBySlug($slug)
    {
        // Super quick hack just for testing until I get a db set up.
        $id = null;
        $file = shell_exec('grep -l \'"' . $slug . '"\' ' . __DIR__ . '/../../var/gallery-*');
        if ($file) {
            $id = trim(substr($file, strrpos($file, '-') + 1));
        }

        return $id ? $this->findByGoogleUserId($id) : null;
    }

    /**
     * @param $id
     * @return GalleryInfo|null
     */
    public function findByGoogleUserId($id)
    {
        $contents = file_get_contents(__DIR__ . '/../../var/gallery-' . $id);
        if (!$contents) {
            return null;
        }

        return unserialize($contents);
    }

    /**
     * @param GalleryInfo $galleryInfo
     * @return GalleryInfo
     */
    public function save(GalleryInfo $galleryInfo)
    {
        file_put_contents(__DIR__ . '/../../var/gallery-' . $galleryInfo->getGoogleUserId(), serialize($galleryInfo));

        return $galleryInfo;
    }

    /**
     * Create a new GalleryInfo object.
     *
     * @param int $googleUserId
     * @param string|null $galleryName
     * @return GalleryInfo
     */
    public function createGalleryInfo($googleUserId, $galleryName = null)
    {
        if (empty($galleryName)) $galleryName = $googleUserId;

        $slug = $this->createGallerySlug($galleryName);

        // Ensure the slug is unique.
        if ($match = $this->findBySlug($slug)) {
            if ($match->getGoogleUserId() != $googleUserId) {
                $slug .= '-' . $googleUserId;
            }
        }

        $galleryInfo = new GalleryInfo($googleUserId, $slug, $galleryName);
        // $galleryInfo->setGoogleUserName($googleUserName);

        return $galleryInfo;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function createGallerySlug($name)
    {
        $slug = $this->slugify->slugify($name);
        $slug = ltrim($slug, '_- '); // Gallery slugs can't start with a leading underscore (due to routing issues)

        return $slug;
    }


}