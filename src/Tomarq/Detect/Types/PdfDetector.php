<?php

namespace Tomarq\Detect\Types;

use Tomarq\Detect\FileType;

class PdfDetector extends Detector
{
	public function getFileType($filename)
	{
		$h = fopen($filename, 'rb');
		if (!$h) return false;
					
		$b = fread($h, 5);
		if ((ftell($h) == 5) && ($b == '%PDF-')) {
			return $this->getFileTypeWithExtension('pdf');
		}
		return false;
	}
	
	public function getSupportedTypes() {
		return [
			new FileType('pdf', 'application/pdf'),
		];
	}
}