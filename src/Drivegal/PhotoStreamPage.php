<?php

namespace Drivegal;

class PhotoStreamPage
{
    protected $files = array();
    protected $pg;
    protected $nextPage;
    protected $prevPage;

    public function __construct($pg, array $files)
    {
        $this->pg = $pg;
        $this->files = $files;
    }

    /**
     * @return array
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param mixed $prevPage
     */
    public function setPrevPage($prevPage)
    {
        $this->prevPage = $prevPage;
    }

    /**
     * @return mixed
     */
    public function getPrevPage()
    {
        return $this->prevPage;
    }

    /**
     * @param mixed $nextPage
     */
    public function setNextPage($nextPage)
    {
        $this->nextPage = $nextPage;
    }

    /**
     * @return mixed
     */
    public function getNextPage()
    {
        return $this->nextPage;
    }

    public function getPage()
    {
        return $this->pg;
    }

}