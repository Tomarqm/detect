<?php

namespace Tomarq\Detect\Types;

use Tomarq\Detect\FileType;

class OfficeDetector extends Detector
{
	protected $h; // File handle
	protected $docinfo; // Document info header
	protected $sector_size; // Sector size
	protected $num_fat_entries_in_a_sector;
	protected $fat_table; // FAT table
	protected $root_dir; // Root directory entries
	
	public function getFileType($filename)
	{			
		$r = $this->detectClassicOfficeFormat($filename);

		if (!$r) {
			$r = $this->detectOfficeOpenXMLFormat($filename);
		}

		return $r;
	}
	
	public function getSupportedTypes() {
		return [
			new FileType('docx', 'application/msword'),
			new FileType('xlsx', 'application/vnd.ms-excel'),
			new FileType('pptx', 'application/vnd.mx-powerpoint'),
			new FileType('doc', 'application/msword'),
			new FileType('xls', 'application/vnd.ms-excel'),
			new FileType('ppt', 'application/vnd.mx-powerpoint'),
		];
	}
	
	/* --------------------------------------------------------------------------------
	 *  Detect newer Office Open XML formats...
	 * -------------------------------------------------------------------------------- */
	protected function detectOfficeOpenXMLFormat($filename)
	{
		$z = new \ZipArchive;
		$r = $z->open($filename);
		if ($r !== true) {
			return null;
		}
		
		// http://www.garykessler.net/library/file_sigs.html
		$data = $z->getFromName('[Content_Types].xml');
		$z->close();

		if (strpos($data, '/word/document.xml') !== false) {
			return $this->getFileTypeWithExtension('docx');
		}
		
		if (strpos($data, '/xl/workbook.xml') !== false) {
			return $this->getFileTypeWithExtension('xlsx');
		}
		
		if (strpos($data, '/ppt/presentation.xml') !== false) {
			return $this->getFileTypeWithExtension('pptx');
		}
		
		return null;
	}

	/* --------------------------------------------------------------------------------
	 *  Detect classic Office formats... (this stuff is crazy. FAT in a file?!)
	 * -------------------------------------------------------------------------------- */
	protected function detectClassicOfficeFormat($filename)
	{
		$this->h = fopen($filename, 'rb');
		if (!$this->h) {
			return null;
		}
		
		try {
			if ($this->readHeader() && $this->readFat() && $this->parseRootDirectory()) {
				fclose($this->h);
				
				foreach($this->root_dir as $name=>$info) {
					// http://blogs.msdn.com/b/vsofficedeveloper/archive/2008/05/08/office-2007-open-xml-mime-types.aspx
					switch($name)
					{						
						case 'WordDocument':
							return $this->getFileTypeWithExtension('doc');
						case 'Book':
						case 'Workbook':
						return $this->getFileTypeWithExtension('xls');
						case 'PowerPoint Document':
							return $this->getFileTypeWithExtension('ppt');
					}
				}

				return null; // Some other miscellaneous office document...
			}
		}
		catch(\Exception $e) {}
		fclose($this->h);
		return null;
	}
	
	protected function parseRootDirectory()
	{
		$this->root_dir = Array();
		
		$entries_per_sector = $this->sector_size / 128;
		$fat_size = count($this->fat_table);
		
		$sector = $this->fixUnsignedLong($this->docinfo['directory_starting_sector']);
		$count = 0;

		do {
			$this->seekToSector($sector);
			
			for($x=0; $x<$entries_per_sector; $x++) {
							
							$dir_name_raw = $this->readBytes(64);
							$dir_data = $this->readBytes(64);
							
							$dirname_data = unpack('v32', $dir_name_raw);
							$dirinfo = unpack('vdirectory_entry_name_length/Cobject_type/Ccolor_flag/Vleft_sibling/Vright_sibling/Vchild_id/H16CLSID_1/H8CLSID_2/H8CLSID_3/Vstate_flags/V2creation_time/V2modification_time/Vstarting_sector_location/V2stream_size', $dir_data);
							
							if ($dirinfo['object_type'] != 0) {
								// Ok, since we're really just looking for "WordDocument" etc, which is going to be in ASCII, I'm just going
								// to throw away the high byte.
								$dirname = '';
								for($y=0; $y<(($dirinfo['directory_entry_name_length']/2) - 1); $y++) {
									$dirname .= chr($dirname_data[$y+1] & 0xFF);
								}
								
								$this->root_dir[$dirname] = $dirinfo;
							}
							$count++;
			}

			// Find the next sector in the chain
			$sector = $this->fat_table[$sector];
			
			// Points to a sector outside of the FAT??!
			if (($sector < 0xFFFFFFFB) && ($sector > $fat_size)) return false;				
		} while($sector < 0xFFFFFFFB);
		
		// Should be the END OF CHAIN marker...
		if ($sector != 0xFFFFFFFE) return false;
		
		return true;
	}
	
	protected function readFAT()
	{
		$this->fat_table = Array();
		
		// Initial DIFAT data is immediately after the header (109 entries)
		$difat_data = $this->readBytes(436);
		$this->addToFat($difat_data, 109);
					
		$difat_num_sectors = $this->fixUnsignedLong($this->docinfo['num_difat_sectors']);
		$sector = $this->fixUnsignedLong($this->docinfo['difat_starting_sector']);
		
		for($i=0; $i<$difat_num_sectors; $i++) {			
			$this->seekToSector($sector);
			
			$difat_data = $this->readBytes($this->sector_size - 4);
			$difat_next = $this->readBytes(4);
			
			$this->addToFat($difat_data, $this->num_fat_entries_in_a_sector - 1);
			
			$difat_next_unpack = unpack('V', $difat_next);
			$sector = $difat_next_unpack[1];
		}
		return true;
	}
	
	protected function addToFat($data, $count)
	{
		$values = unpack('V'.$count, $data);
		foreach($values as $v) {
			if ($v == 0xFFFFFFFF) return;
			$this->seekToSector($v);
			$fat_data = $this->readBytes($this->sector_size);
			$fat_unpack = unpack('V'.$this->num_fat_entries_in_a_sector, $fat_data);
			foreach($fat_unpack as $f) $this->fat_table[] = $this->fixUnsignedLong($f);
		}
	}		
	
	protected function readHeader()
	{
		fseek($this->h, 0, SEEK_SET);
		
		$docinfo_data = $this->readBytes(76);			
		$this->docinfo = unpack('H16magic/H32cls/vmajver/vminver/vbyte_order/vsector_size/vmini_stream_sector_size/vreserved/Vreserved/Vunused/Vnum_fat_sectors/Vdirectory_starting_sector/Vunused/Vmini_stream_size_cutoff/Vminifat_starting_sector/Vnum_minifat_sectors/Vdifat_starting_sector/Vnum_difat_sectors', $docinfo_data);
		
		if ($this->docinfo['magic'] != 'd0cf11e0a1b11ae1') return false;
		
		if ($this->docinfo['sector_size'] == 9) {
			$this->sector_size = 512;
			$this->num_fat_entries_in_a_sector = 128;
		}
		else if ($this->docinfo['sector_size'] == 0xC) {
			$this->sector_size = 4096;
			$this->num_fat_entries_in_a_sector = 1024;
		}
		else return false;
		return true;
	}
	
	protected function readBytes($length)
	{
		$p = ftell($this->h);
		$data = fread($this->h, $length);
		
		// Did we successfully read the bytes required?
		if (ftell($this->h) != ($p + $length)) {
			throw new \Exception('EOF error');
		}
		
		return $data;
	}
	
	protected function fixUnsignedLong($v)
	{
		// Ugg PHP. http://www.php.net/manual/en/function.unpack.php
		return ($v < 0)?($v + 4294967296):$v;
	}
	
	protected function seekToSector($sector)
	{
		fseek($this->h, ($sector + 1) * $this->sector_size, SEEK_SET);
	}		
}
