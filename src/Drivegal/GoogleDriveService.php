<?php

namespace Drivegal;

class GoogleDriveService
{
    /** @var Authenticator */
    protected $authenticator;

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }


}