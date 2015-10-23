<?php

namespace Tomarq\Detect;

class FileType {
    /**
     * @var string
     */
    protected $extension;

    /**
     * @var string
     */
    protected $mimeType;

    function __construct($extension, $mimeType)
    {
        $this->extension = $extension;
        $this->mimeType = $mimeType;
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }

    /**
     * @return string
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }
}