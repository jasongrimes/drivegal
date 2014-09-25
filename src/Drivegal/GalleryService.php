<?php

namespace Drivegal;

use Drivegal\Exception\AlbumNotFoundException;
use Drivegal\Exception\ServiceAuthException;
use Drivegal\Exception\ServiceException;
use Google_Exception;
use Google_Auth_Exception;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Cocur\Slugify\Slugify;
use Drivegal\GalleryFile\AbstractFile as GalleryFile;
use Drivegal\GalleryFile\Album;
use Drivegal\GalleryFile\Image;
use Drivegal\GalleryFile\Movie;
use \DateTime;
use Doctrine\Common\Cache\Cache;

class GalleryService
{
    /** @var GalleryInfoMapper */
    protected $galleryInfoMapper;

    /** @var Authenticator */
    protected $authenticator;

    /** @var Slugify */
    protected $slugify;

    /** @var Cache */
    protected $cache;

    protected $cacheTtl = 900;

    /**
     * @param Authenticator $authenticator
     * @param GalleryInfoMapper $galleryInfoMapper
     * @param Slugify $slugify
     * @param Cache $cache
     */
    public function __construct(Authenticator $authenticator, GalleryInfoMapper $galleryInfoMapper, Slugify $slugify, Cache $cache)
    {
        $this->authenticator = $authenticator;
        $this->galleryInfoMapper = $galleryInfoMapper;
        $this->slugify = $slugify;
        $this->cache = $cache;
    }

    /**
     * @param GalleryInfo $galleryInfo
     * @return GalleryInfo
     */
    public function saveGalleryInfo(GalleryInfo $galleryInfo)
    {
        return $this->galleryInfoMapper->save($galleryInfo);
    }

    /**
     * @param int $id
     * @return GalleryInfo|null
     */
    public function findGalleryInfoByGoogleUserId($id)
    {
        return $this->galleryInfoMapper->findByGoogleUserId($id);
    }

    /**
     * @param string $slug
     * @return GalleryInfo|null
     */
    public function findGalleryInfoBySlug($slug)
    {
        return $this->galleryInfoMapper->findBySlug($slug);
    }

    /**
     * @param GalleryInfo $galleryInfo
     * @param string $albumPath
     * @return AlbumContents
     * @throws Exception\ServiceException
     * @throws Exception\AlbumNotFoundException
     * @throws Exception\ServiceAuthException
     */
    public function getAlbumContents(GalleryInfo $galleryInfo, $albumPath)
    {
        $files = $album = null;

        try {
            $driveService = $this->createDriveService($galleryInfo);
            $albumIndex = $this->fetchAlbumIndex($driveService);

            if ($albumPath) {
                $album = $albumIndex->findByPath($albumPath);
                if (!$album) {
                    throw new AlbumNotFoundException();
                }

                $files = $this->fetchGalleryFiles($driveService, array('album' => $album));

                // Order files in reverse chronological order.
                uasort($files, function(GalleryFile $a, GalleryFile $b) {
                    return strcmp($b->getDate(), $a->getDate());
                });
            }

        } catch (Google_Auth_Exception $e) {
            throw new ServiceAuthException($e->getMessage(), $e->getCode(), $e);
        } catch (Google_Exception $e) {
            if ($e->getCode() == 401) {
                throw new ServiceAuthException($e->getMessage(), $e->getCode(), $e);
            } else {
                throw new ServiceException($e->getMessage(), $e->getCode(), $e);
            }
        }

        // Create AlbumContents object and return it.
        $albumContents = new AlbumContents();
        $albumContents->setTitle($album ? $album->getTitle() : $galleryInfo->getGalleryName());
        $albumContents->setSubAlbums($albumIndex->getSubAlbums($album));
        if ($files) {
            $albumContents->setFiles($files);
        }
        if ($album) {
            $albumContents->setBreadcrumbs($this->getBreadcrumbs($albumPath, $galleryInfo, $albumIndex));
        }

        return $albumContents;
    }

    /**
     * @param string $albumPath
     * @param GalleryInfo $galleryInfo
     * @param AlbumIndex $albumIndex
     * @return array An array of breadcrumbs, in the format array(relativePath => $title, ...).
     *         The current album (the last one in $albumPath) is not included.
     */
    protected function getBreadcrumbs($albumPath, GalleryInfo $galleryInfo, AlbumIndex $albumIndex)
    {
        $breadcrumbs = array();

        $path = $galleryInfo->getSlug() . '/album/';
        // $breadcrumbs[$path] = $galleryInfo->getGalleryName();
        $breadcrumbs[$path] = 'Albums';

        $albumSlugs = explode('/', $albumPath);
        array_pop($albumSlugs); // The last one in the path is the current page, not part of the breadcrumb trail.

        $partialAlbumPath = '';
        foreach ($albumSlugs as $albumSlug) {
            $partialAlbumPath .= $albumSlug . '/';
            $path .= $partialAlbumPath;

            $album = $albumIndex->findByPath($partialAlbumPath);
            if ($album) {
                $breadcrumbs[$path] = $album->getTitle();
            }
        }

        return $breadcrumbs;
    }

    /**
     * Get an index of all public albums.
     *
     * @todo Filter out albums that contain no images?
     * @param Google_Service_Drive $driveService
     * @return AlbumIndex
     */
    protected function fetchAlbumIndex(Google_Service_Drive $driveService)
    {
        $albumIndex = new AlbumIndex();

        $folders = $driveService->files->listFiles(array(
            'q' => 'trashed=false and mimeType="application/vnd.google-apps.folder"',
            'maxResults' => 1000,
            // 'fields' => 'items(createdDate,description,downloadUrl,mimeType,originalFilename,parents,thumbnailLink,title,webContentLink,webViewLink)',
        ));
        foreach ($folders as $folder) { /** @var Google_Service_Drive_DriveFile $folder */
            // Only index public folders.
            // (The webViewLink property only exists for public folders.)
            if ($folder->getWebViewLink()) {
                $album = $this->createAlbumFromDriveFolder($folder);
                $albumIndex->add($album);
            }
        }

        $albumIndex->buildIndex();

        return $albumIndex;
    }

    /**
     * @param Google_Service_Drive_DriveFile $folder
     * @return Album
     */
    protected function createAlbumFromDriveFolder(Google_Service_Drive_DriveFile $folder)
    {
        $album = new Album(
            $folder->getId(),
            $folder->getTitle(),
            $this->createAlbumSlug($folder->getTitle()),
            $this->getParentIdsFromDriveFile($folder));

        $album->setOriginalFileUrl($folder->getWebViewLink());
        $album->setDate($folder->getModifiedDate());

        return $album;
    }

    /**
     * @param string $name
     * @return string
     */
    protected function createAlbumSlug($name)
    {
        return $this->slugify->slugify($name);
    }

    /**
     * @param Google_Service_Drive_DriveFile $file
     * @return array
     */
    protected function getParentIdsFromDriveFile(Google_Service_Drive_DriveFile $file)
    {
        $parentIds = array();
        foreach ($file->getParents() as $parent) {
            $parentIds[] = $parent->getId();
        }

        return $parentIds;
    }

    /**
     * @param Google_Service_Drive $driveService
     * @param array $criteria An array of criteria, with the following optional keys:<pre>
     *     album - (Album) Get files in this album.
     * </pre>
     * @param AlbumIndex $albumIndex If set, ensure that files have at least one parent album in this index (for filtering out files not in public albums).
     * @return GalleryFile[]
     */
    protected function fetchGalleryFiles(Google_Service_Drive $driveService, $criteria = array(), AlbumIndex $albumIndex = null)
    {
        $files = array();

        $q = 'trashed=false and (mimeType contains "image/" or mimeType contains "video/") ';
        if (array_key_exists('album', $criteria) && $criteria['album'] instanceof Album) {
            $q .= 'and "' . $criteria['album']->getId() . '" in parents ';
        }

        $driveFiles = $this->fetchAllDriveFiles($driveService, $q);
        foreach ($driveFiles as $driveFile) {
            if ($galleryFile = $this->createGalleryFileFromDriveFile($driveFile)) {
                if ($albumIndex && !$albumIndex->hasAtLeastOneId($galleryFile->getParentIds())) {
                    continue;
                }
                $files[$galleryFile->getId()] = $galleryFile;
            }
        }

        return $files;
    }

    /**
     * @param Google_Service_Drive $driveService
     * @param string $q
     * @return Google_Service_Drive_DriveFile[]
     * @throws Exception\ServiceException
     */
    protected function fetchAllDriveFiles(Google_Service_Drive $driveService, $q)
    {
        $result = array();
        $pageToken = null;

        // TODO: Specify the few fields we actually need to conserve bandwidth?
        $parameters = array(
            'q' => $q,
            'maxResults' => 1000,
        );

        do {
            try {
                if ($pageToken) {
                    $parameters['pageToken'] = $pageToken;
                }
                $files = $driveService->files->listFiles($parameters);

                $result = array_merge($result, $files->getItems());
                $pageToken = $files->getNextPageToken();
            } catch (\Exception $e) {
                throw new ServiceException($e->getMessage(), $e->getCode(), $e);
                $pageToken = null;
            }
        } while ($pageToken);

        return $result;
    }

    /**
     * @param Google_Service_Drive_DriveFile $driveFile
     * @return GalleryFile|null
     */
    protected function createGalleryFileFromDriveFile(Google_Service_Drive_DriveFile $driveFile)
    {
        $class = $playUrl = null;

        if (strpos($driveFile->getMimeType(), 'image/') !== false) {
            $class = 'Drivegal\GalleryFile\Image';
        } elseif (strpos($driveFile->getMimeType(), 'video/') !== false) {
            $class = 'Drivegal\GalleryFile\Movie';
            // $viewUrl = $driveFile->getEmbedLink();
            // $viewUrl = $album->getOriginalFileUrl() . '/' . $driveFile->getOriginalFilename();
            // echo '<pre>' . print_r($driveFile, true) . '</pre>';
            $playUrl = preg_replace('/edit/', 'preview', $driveFile->getAlternateLink()); // alternateLink is "A link for opening the file in using a relevant Google editor or viewer."
        }

        if (!$class) {
            return null;
        }

        /** @var GalleryFile $galleryFile */
        $galleryFile = new $class($driveFile->getId(), $driveFile->getTitle(), $driveFile->getThumbnailLink(), $this->changeThumbnailLinkSize($driveFile->getThumbnailLink(), 1000));

        $galleryFile->setParentIds($this->getParentIdsFromDriveFile($driveFile));
        $galleryFile->setDescription($driveFile->getDescription());
        $datetime = '';
        if ($galleryFile instanceof Image && $metadata = $driveFile->getImageMediaMetadata()) {
            $datetime = DateTime::createFromFormat('Y:m:d H:i:s', $metadata->getDate());
            $galleryFile->setCameraMake($metadata->getCameraMake());
            $galleryFile->setCameraModel($metadata->getCameraModel());
        }
        if (!$datetime) {
            $datestr = $driveFile->getModifiedDate();
            $datestr = substr($datestr, 0, strpos($datestr, '.')); // Chop off trailing fractional seconds and Z timezone, which DateTime doesn't understand.
            $datetime = DateTime::createFromFormat('Y-m-d\TH:i:s', $datestr);
        }
        if ($datetime) {
            $galleryFile->setDate($datetime->format(DATE_ISO8601));
        }
        $galleryFile->setOriginalFilename($driveFile->getOriginalFilename());
        // $galleryFile->setOriginalFileUrl($album->getOriginalFileUrl() . $driveFile->getOriginalFilename());
        $galleryFile->setMimeType($driveFile->getMimeType());
        if ($playUrl && $galleryFile instanceof Movie) {
            $galleryFile->setPlayUrl($playUrl);
        }

        return $galleryFile;
    }

    /**
     * Modify a DriveFile thumbnailLink to request a thumbnail of the given size.
     *
     * @param string $link
     * @param string $new_size
     * @return string
     */
    protected function changeThumbnailLinkSize($link, $new_size)
    {
        return preg_replace('/=s\d+$/', '=s' . $new_size, $link);
    }

    /**
     * @param GalleryInfo $galleryInfo
     * @return Google_Service_Drive
     */
    protected function createDriveService(GalleryInfo $galleryInfo)
    {
        $client = $this->authenticator->createClient();
        $client->setAccessToken($galleryInfo->getCredentials());

        return new Google_Service_Drive($client);

    }

    /**
     * Validate a GalleryInfo object.
     *
     * @param GalleryInfo $galleryInfo
     * @return array An array of error messages. If there are no errors, an empty array is returned.
     */
    public function validateGalleryInfo(GalleryInfo $galleryInfo)
    {
        $errors = array();

        if (!$galleryInfo->getGalleryName()) {
            $errors['galleryName'] = 'Please specify a gallery name.';
        }

        if (!$galleryInfo->getSlug()) {
            $errors['slug'] = 'Please specify a web address (slug).';
        } else {
            if ($match = $this->galleryInfoMapper->findBySlug($galleryInfo->getSlug())) {
                if ($match->getGoogleUserId() != $galleryInfo->getGoogleUserId()) {
                    $errors['slug'] = 'That web address (slug) is already in use by another gallery. Please specify a different one.';
                }
            }
        }

        return $errors;
    }

    /**
     * Disconnect a gallery from a Google Drive account (by telling Google to revoke its OAuth2 access token).
     *
     * @param GalleryInfo $galleryInfo
     * @return bool True on success, false otherwise.
     */
    public function deactivateGallery(GalleryInfo $galleryInfo)
    {
        $client = $this->authenticator->createClient();
        $result = $client->revokeToken($galleryInfo->getRefreshToken());

        if (!$result) {
            return false;
        }

        $galleryInfo->setIsActive(false);
        $this->galleryInfoMapper->save($galleryInfo);

        return true;
    }

    public function getPhotoStreamPage(GalleryInfo $galleryInfo, $pg = null)
    {
        $photoStream = new PhotoStream($galleryInfo);
        $this->populatePhotoStream($photoStream, $pg);

        return $photoStream->getPage($pg);
    }

    protected function populatePhotoStream(PhotoStream $photoStream, $pg = null)
    {
        // Get gallery files
        $driveService = $this->createDriveService($photoStream->getGalleryInfo());
        $albumIndex = $this->fetchAlbumIndex($driveService);

        $cacheKey = $this->getGalleryStreamCacheKey($photoStream->getGalleryInfo());
        if ($this->cache->contains($cacheKey)) {
            $files = $this->cache->fetch($cacheKey);
        } else {
            $files = $this->fetchGalleryFiles($driveService, array(), $albumIndex);
            $this->cache->save($cacheKey, $files, $this->cacheTtl);
        }

        // Order files in reverse chronological order.
        uasort($files, function(GalleryFile $a, GalleryFile $b) {
            return strcmp($b->getDate(), $a->getDate());
        });

        // Add to photo stream
        $photoStream->appendFiles($files);
    }

    protected function getGalleryStreamCacheKey(GalleryInfo $galleryInfo)
    {
        return $galleryInfo->getGoogleUserId() . ':stream';
    }
}