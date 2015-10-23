<?php

namespace Tomarq\Detect\Types;

use Tomarq\Detect\FileType;

class ImageDetector extends Detector
{
	public function getFileType($filename)
	{
		$data = @getimagesize($filename); // This can produce a "Read error" if the claimed "image file" is very short - e.g. 4 bytes
		if ($data === false) {
			return null;
		}

		return $this->getFileTypeWithMimeType($data['mime']);
	}
	
	public function getSupportedTypes() {
		return [
			new FileType('jpg', 'image/jpeg'),
			new FileType('png', 'image/png'),
			new FileType('gif', 'image/gif'),
		];
	}
}