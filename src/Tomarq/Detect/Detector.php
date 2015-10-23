<?php

namespace Tomarq\Detect;

use Tomarq\Detect\Types\DetectorInterface;
use Tomarq\Detect\Types\ImageDetector;
use Tomarq\Detect\Types\PdfDetector;
use Tomarq\Detect\Types\OfficeDetector;

class Detector {
    /**
     * @var DetectorInterface[]
     */
    protected $detectors;

    public function __construct() {
        $this->detectors = [
            new ImageDetector,
            new PdfDetector,
            new OfficeDetector,
        ];
    }

    /**
     * @param $filename
     * @return FileType|null
     */
    public function getFileType($filename)
    {
        foreach($this->detectors as $detector) {
            $fileType = $detector->getFileType($filename);
            if ($fileType) {
                return $fileType;
            }
        }

        return null;
    }
}