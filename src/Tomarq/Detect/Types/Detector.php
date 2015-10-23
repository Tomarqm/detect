<?php

namespace Tomarq\Detect\Types;

use Tomarq\Detect\FileType;

abstract class Detector implements DetectorInterface {
	/**
	 * @param $extension
	 * @return null|FileType
	 */
	protected function getFileTypeWithExtension($extension) {
		foreach($this->getSupportedTypes() as $fileType) {
			if ($fileType->getExtension() == $extension) {
				return $fileType;
			}
		}

		return null;
	}

	/**
	 * @param $mimeType
	 * @return null|FileType
	 */
	protected function getFileTypeWithMimeType($mimeType) {
		foreach($this->getSupportedTypes() as $fileType) {
			if ($fileType->getMimeType() == $mimeType) {
				return $fileType;
			}
		}

		return null;
	}
}