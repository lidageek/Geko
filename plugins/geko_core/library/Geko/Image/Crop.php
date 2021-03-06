<?php

/*

// request parameters that can be sent to this class

$aParams = array(
	'src|source'							=> [absolute location of file],
	'w|wdt|width'							=> [width],
	'h|hgt|height'							=> [height],
	'x|xoff|xoffset'						=> [x-offset],
	'y|yoff|yoffset'						=> [y-offset],
	'o|om|offsetmethod'						=> [offset method, 'p' in pixels, 'u' for width/height units],
	'q|qlty|quality'						=> [quality (default is 75 and max is 100)],
	'mtime|modificationtime'				=> [date modification timestamp],
	'rmt|remote'							=> [TRUE or FALSE]
);

*/


class Geko_Image_Crop extends Geko_Image_CachedAbstract
{
	
	protected $sImageSrc;
	protected $iXOffset = 0;
	protected $iYOffset = 0;
	protected $sOffsetMethod = 'p';
	protected $iQuality = 80;
	protected $iModifiedTimestamp;
	protected $bIsRemote = FALSE;
	
	
	
	////// static methods
	
	
	
	
	////// methods
	
	// constructor
	public function __construct( $aParams = array() ) {
		
		if ( $aResolveParams = self::resolveImageSrc( self::paramCoalesce( $aParams, 'src|source' ) ) ) {
			$aParams = array_merge( $aParams, $aResolveParams );
		}
		
		$this
			->arrSetImageSrc( $aParams, 'src|source' )
			->arrSetWidth( $aParams, 'w|wdt|width' )
			->arrSetHeight( $aParams, 'h|hgt|height' )
			->arrSetXOffset( $aParams, 'x|xoff|xoffset' )
			->arrSetYOffset( $aParams, 'y|yoff|yoffset' )
			->arrSetOffsetMethod( $aParams, 'o|om|offsetmethod' )
			->arrSetQuality( $aParams, 'q|qlty|quality' )
			->arrSetModifiedTimestamp( $aParams, 'mtime|modtime|modificationtime' )
			->arrSetIsRemote( $aParams, 'rmt|remote' )
		;
	}
	
	
	
	//// accessors
	
	//
	public function setImageSrc( $sImageSrc ) {
		$this->sImageSrc = $sImageSrc;
		return $this;
	}
	
	//
	public function setXOffset( $iXOffset ) {
		$iXOffset = intval( preg_replace( "/[^0-9-]/", '', $iXOffset ) );		
		$this->iXOffset = $iXOffset;
		return $this;
	}
	
	//
	public function setYOffset( $iYOffset ) {
		$iYOffset = intval( preg_replace( "/[^0-9-]/", '', $iYOffset ) );		
		$this->iYOffset = $iYOffset;
		return $this;
	}
	
	//
	public function setXyOffset( $iXOffset, $iYOffset ) {
		return $this
			->setXOffset( $iXOffset )
			->setYOffset( $iYOffset )
		;
	}

	//
	public function setOffsetMethod( $sOffsetMethod ) {
		$sOffsetMethod = strtolower( $sOffsetMethod );
		if ( in_array( $sOffsetMethod, array( 'p', 'u' ) ) ) {
			$this->sOffsetMethod = $sOffsetMethod;		
		}
		return $this;
	}
	
	//
	public function setQuality( $iQuality ) {
		
		$iQuality = intval( preg_replace( "/[^0-9]/", '', $iQuality ) );
		if ( !$iQuality ) $iQuality = 80;
		
		$this->iQuality = $iQuality;
		return $this;
	}
	
	//
	public function setModifiedTimestamp( $iModifiedTimestamp ) {
		$this->iModifiedTimestamp = $iModifiedTimestamp;
		return $this;
	}
	
	//
	public function setIsRemote( $bIsRemote ) {
		$this->bIsRemote = $bIsRemote;
		return $this;
	}
	

	
	////
	
	//
	public function getMimeType() {
		
		$aAllowedMimeTypes = array(
			'image/jpeg',
			'image/png',
			'image/gif'
		);
		
		$sMime = Geko_File_MimeType::get( $this->sImageSrc );
		
		// if mime type was not determined, use the file extension
		if ( !$sMime ) {
			$sExt = strtolower( pathinfo( $this->sImageSrc, PATHINFO_EXTENSION ) );
			if ( 'jpg' == $sExt || 'jpeg' == $sExt ) $sMime = 'image/jpeg';
			elseif ( 'png' == $sExt ) $sMime = 'image/png';
			elseif ( 'gif' == $sExt ) $sMime = 'image/gif';
		}
		
		if ( in_array( $sMime, $aAllowedMimeTypes ) ) {
			return $sMime;
		} else {
			// mime type not allowed
			if ( self::$bLogging ) $this->logMessage( __METHOD__, 'Mime type not allowed: ' . $this->sImageSrc );
			return '';
		}
	}
	
	//
	protected function generateCacheFile() {
		
		//// do checks
		
		// gd library
		if ( FALSE == function_exists( 'imagecreatetruecolor' ) ) {
			// gd library is not installed
			if ( self::$bLogging ) $this->logMessage( __METHOD__, 'GD image library is not installed.' );
			return FALSE;
		}
		
		// file/mime type
		if ( '' == ( $sMimeType = $this->getMimeType() ) ) {
			// incorrect kind of file specified
			return FALSE;
		}
		
		if ( self::$bLogging ) $this->logMessage( __METHOD__, 'Attempting to create cropped file with mime type: ' . $sMimeType );
		
		// make sure cache directory exists
		if ( FALSE == $this->assertCacheDir() ) {
			return FALSE;
		}
		
		// create cache file
		if ( FALSE == touch( $this->sCacheFilePath ) ) {
			if ( self::$bLogging ) $this->logMessage( __METHOD__, 'Failed to create cache file.' . $this->sCacheFilePath );
			return FALSE;
		}
		
		
		
		//// generate cache file
		
		// check if image is remote or local, then open it
		
		if ( TRUE == $this->bIsRemote ) {
			
			// image is remote
			$rImage = imagecreatefromstring( Geko_RemoteFile::getContents( $this->sImageSrc ) );
			
		} else {
			
			// image is local
			if ( TRUE == stristr( $sMimeType, 'gif' ) ) {
				$rImage = imagecreatefromgif( $this->sImageSrc );
			} elseif ( TRUE == stristr( $sMimeType, 'png' ) ) {
				$rImage = imagecreatefrompng( $this->sImageSrc );
			} else {
				// jpeg is default
				$rImage = imagecreatefromjpeg( $this->sImageSrc );
			}		
		}
		
		if ( FALSE == $rImage ) {
			if ( self::$bLogging ) $this->logMessage( __METHOD__, 'GD failed to open image: ' . $this->sImageSrc );
			return FALSE;
		}
		
		if ( self::$bLogging ) $this->logMessage( __METHOD__, 'Attempting to create cropped file from source: ' . $this->sImageSrc );		
		
		// Get original width and height
		$iCurWidth = imagesx( $rImage );
		$iCurHeight = imagesy( $rImage );

		$iWidth = ( $this->iWidth ) ? $this->iWidth : $this->iHeight;
		$iHeight = ( $this->iHeight ) ? $this->iHeight : $this->iWidth;
		
		if ( 'u' == $this->sOffsetMethod ) {
			// use width/height as offset units
			$iXOffset = $iWidth * $this->iXOffset;
			$iYOffset = $iHeight * $this->iYOffset;			
		} else {
			// default, offset is in pixel values
			$iXOffset = $this->iXOffset;
			$iYOffset = $this->iYOffset;		
		}
		
		// if offsets go beyond width/height then throw an exception
		if (
			( $iXOffset < 0 ) || ( $iXOffset >= $iCurWidth ) || 
			( $iYOffset < 0 ) || ( $iYOffset >= $iCurHeight )
		) {
			unlink( $this->sCacheFilePath );
			throw new Exception( 'Offset values are out of bounds for: ' . __METHOD__ );
			return FALSE;
		}
		
		// create a new true color image
		$rCanvas = imagecreatetruecolor( $iWidth, $iHeight );

		imagecopyresampled( $rCanvas, $rImage, 0, 0, $iXOffset, $iYOffset, $iWidth, $iHeight, $iWidth, $iHeight );
		
		// write the image to file
		if ( TRUE == stristr( $sMimeType, 'gif' ) ) {
			imagegif( $rCanvas, $this->sCacheFilePath );
		} elseif( TRUE == stristr( $sMimeType, 'png' ) ) {
			imagepng( $rCanvas, $this->sCacheFilePath, ceil( $this->iQuality / 10 ) );
		} else {
			// jpeg is default
			imagejpeg( $rCanvas, $this->sCacheFilePath, $this->iQuality );
		}
		
		if ( self::$bLogging ) $this->logMessage( __METHOD__, 'Cache image created: ' . $this->sCacheFilePath );
		
		// free up memory
		imagedestroy( $rImage );
		imagedestroy( $rCanvas );
		
	}
	
	//
	public function getCacheFileKey() {
		
		if ( '' == $this->sImageSrc ) {
			
			// image source given is empty
			if ( self::$bLogging ) $this->logMessage( __METHOD__, 'Image source given is empty.' );
			return FALSE;
			
		} else {

			// this should create a unique "signature" for the cached file
			return md5(
				$this->sImageSrc . '_' .
				$this->iWidth . '_' .
				$this->iHeight . '_' .
				$this->iXOffset . '_' .
				$this->iYOffset . '_' .
				$this->sOffsetMethod . '_' .
				$this->iQuality . '_' .
				$this->iModifiedTimestamp . '_' .
				intval( $this->bIsRemote )
			);
			
		}
	}
	
	
	//
	public function buildThumbUrl( $sThumbUrl, $bRetObj = FALSE ) {
		
		$oUrl = new Geko_Uri( $sThumbUrl );
		$oUrl
			->setVar( 'src', $this->sImageSrc, FALSE )
			->setVar( 'w', $this->iWidth, FALSE )
			->setVar( 'h', $this->iHeight, FALSE )
			->setVar( 'x', $this->iXOffset, FALSE )
			->setVar( 'y', $this->iYOffset, FALSE )
			->setVar( 's', $this->sOffsetMethod, FALSE )
			->setVar( 'q', $this->iQuality, FALSE )
			->setVar( 'mtime', $this->iModifiedTimestamp, FALSE )
			->setVar( 'rmt', intval( $this->bIsRemote ), FALSE )
		;
		
		return ( $bRetObj ) ? $oUrl : strval( $oUrl );
	}
	
	
	
}


