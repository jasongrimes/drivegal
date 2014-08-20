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

class GalleryService
{
    /** @var GalleryInfoMapper */
    protected $galleryInfoMapper;

    /** @var Authenticator */
    protected $authenticator;

    /** @var Slugify */
    protected $slugify;

    public function __construct(Authenticator $authenticator, GalleryInfoMapper $galleryInfoMapper, Slugify $slugify)
    {
        $this->authenticator = $authenticator;
        $this->galleryInfoMapper = $galleryInfoMapper;
        $this->slugify = $slugify;
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

                $files = $this->fetchGalleryFiles($driveService, $album);
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

        $path = $galleryInfo->getSlug() . '/';
        $breadcrumbs[$path] = $galleryInfo->getGalleryName();

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
     * @param Album $album
     * @return GalleryFile[]
     */
    protected function fetchGalleryFiles(Google_Service_Drive $driveService, Album $album)
    {
        $files = array();

        $q = 'trashed=false
                and "' . $album->getId() . '" in parents
                and (mimeType contains "image/" or mimeType contains "video/")';

        // TODO: Specify the few fields we actually need to conserve bandwidth?
        $driveFiles = $driveService->files->listFiles(array(
            'q' => $q,
            'maxResults' => 1000,
            // 'fields' => '"items(createdDate,description,downloadUrl,mimeType,originalFilename,parents,thumbnailLink,title,webContentLink,webViewLink)"',
        ));

        foreach ($driveFiles as $driveFile) { /** @var Google_Service_Drive_DriveFile $driveFile */
            if ($galleryFile = $this->createGalleryFileFromDriveFile($driveFile, $album)) {
                $files[$galleryFile->getId()] = $galleryFile;
            }
        }

        // Order files in reverse chronological order.
        uasort($files, function(GalleryFile $a, GalleryFile $b) {
            return strcmp($b->getDate(), $a->getDate());
        });

        return $files;
    }

    /**
     * @param Google_Service_Drive_DriveFile $driveFile
     * @return GalleryFile|null
     */
    protected function createGalleryFileFromDriveFile(Google_Service_Drive_DriveFile $driveFile, Album $album)
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
        $galleryFile->setOriginalFileUrl($album->getOriginalFileUrl() . $driveFile->getOriginalFilename());
        $galleryFile->setMimeType($driveFile->getMimeType());
        if ($playUrl && $galleryFile instanceof Movie) {
            $galleryFile->setPlayUrl($playUrl);
        }

        return $galleryFile;
    }

    protected function changeThumbnailLinkSize($link, $new_size)
    {
        return preg_replace('/=s\d+$/', '=s' . $new_size, $link);
    }

    protected function createDriveService(GalleryInfo $galleryInfo)
    {
        $client = $this->authenticator->createClient();
        $client->setAccessToken($galleryInfo->getCredentials());

        return new Google_Service_Drive($client);

    }
}