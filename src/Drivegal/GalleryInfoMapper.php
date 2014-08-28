<?php

namespace Drivegal;

use Cocur\Slugify\Slugify;
use Doctrine\DBAL\Connection;

class GalleryInfoMapper
{
    /** @var \Doctrine\DBAL\Driver\Connection */
    protected $conn;

    /** @var Slugify */
    protected $slugify;

    /**
     * @param Connection $conn
     * @param Slugify $slugify
     */
    public function __construct(Connection $conn, Slugify $slugify)
    {
        $this->conn = $conn;
        $this->slugify = $slugify;
    }

    /**
     * @param string $slug
     * @return GalleryInfo|null
     */
    public function findBySlug($slug)
    {
        return $this->findOneBy(array('slug' => $slug));
    }

    /**
     * @param $id
     * @return GalleryInfo|null
     */
    public function findByGoogleUserId($id)
    {
        return $this->findOneBy(array('googleUserId' => $id));
    }

    /**
     * @param array $criteria
     * @param array $options
     * @return GalleryInfo|null
     */
    public function findOneBy(array $criteria, array $options = array())
    {
        $galleryInfos = $this->findBy($criteria, $options);

        return reset($galleryInfos) ?: null;
    }

    /**
     * @param array $criteria
     * @param array $options
     * @return GalleryInfo[]
     */
    public function findBy(array $criteria, array $options = array())
    {
        $params = array();
        $sql = 'SELECT * FROM galleryInfo ';

        $first_crit = true;
        foreach ($criteria as $key => $val) {
            $sql .= ($first_crit ? 'WHERE' : 'AND') . ' ' . $key . ' = :' . $key . ' ';
            $params[$key] = $val;
            $first_crit = false;
        }

        if (array_key_exists('order_by', $options)) {
            list ($order_by, $order_dir) = is_array($options['order_by']) ? $options['order_by'] : array($options['order_by']);
            $sql .= 'ORDER BY ' . $this->conn->quoteIdentifier($order_by) . ' ' . ($order_dir == 'DESC' ? 'DESC' : 'ASC') . ' ';
        }
        if (array_key_exists('limit', $options)) {
            list ($offset, $limit) = is_array($options['limit']) ? $options['limit'] : array(0, $options['limit']);
            $sql .=   ' LIMIT ' . (int) $limit . ' ' .' OFFSET ' . (int) $offset ;
        }

        $rows = $this->conn->fetchAll($sql, $params);

        $galleryInfos = array();
        foreach ($rows as $row) {
            $galleryInfo = $this->hydrateGalleryInfo($row);
            $galleryInfos[] = $galleryInfo;
        }

        return $galleryInfos;
    }

    /**
     * @param array $data
     * @return GalleryInfo
     */
    protected function hydrateGalleryInfo(array $data)
    {
        $galleryInfo = new GalleryInfo($data['googleUserId'], $data['slug'], $data['galleryName']);

        $galleryInfo->setCredentials($data['credentials']);
        $galleryInfo->setIsActive($data['isActive']);
        $galleryInfo->setTimeCreated($data['timeCreated']);

        return $galleryInfo;
    }

    /**
     * @param GalleryInfo $galleryInfo
     */
    public function save(GalleryInfo $galleryInfo)
    {
        $exists = $this->findByGoogleUserId($galleryInfo->getGalleryName()) ? true : false;

        $sql = ($exists ? 'UPDATE' : 'INSERT INTO') . ' galleryInfo ';
        $sql .= 'SET slug = :slug
            , galleryName = :galleryName
            , credentials = :credentials
            , isActive = :isActive
            , timeCreated = :timeCreated ';
        if ($exists) {
            $sql .= 'WHERE googleUserId = :googleUserId ';
        } else {
            $sql .= ', googleUserId = :googleUserId ';
        }

        $params = array(
            'googleUserId' => $galleryInfo->getGoogleUserId(),
            'slug' => $galleryInfo->getSlug(),
            'galleryName' => $galleryInfo->getGalleryName(),
            'credentials' => $galleryInfo->getCredentials(),
            'isActive' => $galleryInfo->getIsActive(),
            'timeCreated' => $galleryInfo->getTimeCreated(),
        );

        $this->conn->executeUpdate($sql, $params);
    }

    /**
     * Create a new GalleryInfo object, ensuring there are no collisions with existing gallery slugs.
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