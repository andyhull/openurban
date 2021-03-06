<?php
/**
 * Handler for the Gimp's native file format (XCF)
 *
 * Overview:
 *   http://en.wikipedia.org/wiki/XCF_(file_format)
 * Specification in Gnome repository:
 *   http://svn.gnome.org/viewvc/gimp/trunk/devel-docs/xcf.txt?view=markup
 *
 * @file
 * @ingroup Media
 */

/**
 * Handler for the Gimp's native file format; getimagesize() doesn't
 * support these files
 *
 * @ingroup Media
 */
class XCFHandler extends BitmapHandler {

	/**
	 * @param $file
	 * @return bool
	 */
	function mustRender( $file ) {
		return true;
	}

	/**
	 * Render files as PNG
	 *
	 * @param $ext
	 * @param $mime
	 * @param $params
	 * @return array
	 */
	function getThumbType( $ext, $mime, $params = null ) {
		return array( 'png', 'image/png' );
	}

	/**
	 * Get width and height from the XCF header.
	 *
	 * @param $image
	 * @param $filename
	 * @return array
	 */
	function getImageSize( $image, $filename ) {
		return self::getXCFMetaData( $filename );
	}

	/**
	 * Metadata for a given XCF file
	 *
	 * Will return false if file magic signature is not recognized
	 * @author Hexmode
	 * @author Hashar
	 *
	 * @param $filename String Full path to a XCF file
	 * @return false|metadata array just like PHP getimagesize()
	 */
	static function getXCFMetaData( $filename ) {
		# Decode master structure
		$f = fopen( $filename, 'rb' );
		if( !$f ) {
			return false;
		}
		# The image structure always starts at offset 0 in the XCF file.
		# So we just read it :-)
		$binaryHeader = fread( $f, 26 );
		fclose($f);

		# Master image structure:
		#
		# byte[9] "gimp xcf "  File type magic
		# byte[4] version      XCF version
		#                        "file" - version 0
		#                        "v001" - version 1
		#                        "v002" - version 2
		# byte    0            Zero-terminator for version tag
		# uint32  width        With of canvas
		# uint32  height       Height of canvas
		# uint32  base_type    Color mode of the image; one of
		#                         0: RGB color
		#                         1: Grayscale
		#                         2: Indexed color
		#        (enum GimpImageBaseType in libgimpbase/gimpbaseenums.h)
		try {
			$header = wfUnpack(
				  "A9magic"     # A: space padded
				. "/a5version"  # a: zero padded
				. "/Nwidth"     # \
				. "/Nheight"    # N: unsigned long 32bit big endian
				. "/Nbase_type" # /
			, $binaryHeader
			);
		} catch( MWException $mwe ) {
			return false;
		}

		# Check values
		if( $header['magic'] !== 'gimp xcf' ) {
			wfDebug( __METHOD__ . " '$filename' has invalid magic signature.\n" );
			return false;
		}
		# TODO: we might want to check for sane values of width and height

		wfDebug( __METHOD__ . ": canvas size of '$filename' is {$header['width']} x {$header['height']} px\n" );

		# Forge a return array containing metadata information just like getimagesize()
		# See PHP documentation at: http://www.php.net/getimagesize
		$metadata = array();
		$metadata[0] = $header['width'];
		$metadata[1] = $header['height'];
		$metadata[2] = null;   # IMAGETYPE constant, none exist for XCF.
		$metadata[3] = sprintf(
			'height="%s" width="%s"', $header['height'], $header['width']
		);
		$metadata['mime'] = 'image/x-xcf';
		$metadata['channels'] = null;
		$metadata['bits'] = 8;  # Always 8-bits per color

		assert( '7 == count($metadata); # return array must contains 7 elements just like getimagesize() return' );

		return $metadata;
	}

	/**
	 * Must use "im" for XCF
	 *
	 * @return string
	 */
	protected static function getScalerType( $dstPath, $checkDstPath = true ) {
		return "im";
	}
}
