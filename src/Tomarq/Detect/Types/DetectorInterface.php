<?php

namespace Tomarq\Detect\Types;

use Tomarq\Detect\FileType;

interface DetectorInterface {
    /**
     * Attempts to identify the given file.
     *
     * On success, returns a FileType
     * On failure, returns null
     *
     * @return FileType|null
     */
    public function getFileType($filename);

    /**
     * Returns an array of file types supported by this detector
     *
     * @return FileType[]
     */
    public function getSupportedTypes();
}