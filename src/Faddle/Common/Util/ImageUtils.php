<?php namespace Faddle\Common\Util;


class ImageUtils {

	public static function base64_image_encode($image_file) {
		$image_info = getimagesize($image_file);
		return "data:{$image_info['mime']};base64," . chunk_split(base64_encode(file_get_contents($image_file)));
	}

	public static function base64_image_decode($image_data, $new_file) {
		if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $image_data, $result)) {
			if (! isset($new_file)) $new_file = static::gen_tmpfile();
			if (file_put_contents($new_file, base64_decode(str_replace($result[1], '', $image_data)))) {
				$ext = static::get_image_extension_name('image/'. $result[2], 'jpg');
				return ['file' => $new_file, 'mime' => 'image/'. $result[2], 'extension' => $ext];
			} else return false;
		} else return false;
	}

	public static function gen_tmpfile() {
		return array_search('uri', @array_flip(stream_get_meta_data($GLOBALS[mt_rand()]=tmpfile())));
	}

	public static function get_image_extension_name($mime, $ext) {
		$ext = $ext ?: 'jpg';
		foreach (static::get_image_mimes() as $exts => $mimes) {
			if (in_array($mime, $mimes)) {
				$ext = $exts;
				break;
			}
		}
		return $ext;
	}

	public static function get_image_mimes() {
		return array(
			'bmp'      => array('image/bmp'),
			'gif'      => array('image/gif'),
			'ico'      => array('image/x-icon'),
			'png'      => array('image/png', 'image/x-png'),
			'jpg'      => array('image/jpeg', 'image/pjpeg'),
			'jpeg'     => array('image/jpeg', 'image/pjpeg'),
			'jpe'      => array('image/jpeg', 'image/pjpeg'),
			'pbm'      => array('image/x-portable-bitmap'),
			'tif'      => array('image/tiff'),
			'tiff'     => array('image/tiff'),
			'svg'      => array('image/svg+xml'),
			
			);
	}


	public static function resize_thumbnail_image($destImage, $srcImage, $scale=1.0, $x=0, $y=0, $width=0, $height=0) {
		list($imageWidth, $imageHeight, $imageType) = getimagesize($srcImage);
		$imageType = image_type_to_mime_type($imageType);
		if ($width <= 0) $width = $imageWidth;
		if ($height <= 0) $height = $imageHeight;
		if (is_float($scale)) {
			$newImageWidth = ceil($width * $scale);
			$newImageHeight = ceil($height * $scale);
		} else {
			$scale = intval($scale); if ($scale < 10) $scale = 30;
			$newImageHeight = ceil($height * $scale / $width);
			$newImageWidth = $scale;
		}
		$newImage = imagecreatetruecolor($newImageWidth, $newImageHeight);
		switch($imageType) {
			case "image/gif":
				$source=imagecreatefromgif($srcImage); 
				break;
			case "image/pjpeg":
			case "image/jpeg":
			case "image/jpg":
				$source=imagecreatefromjpeg($srcImage); 
				break;
			case "image/png":
			case "image/x-png":
				$source=imagecreatefrompng($srcImage); 
				break;
		}
		imagecopyresampled($newImage, $source, 0, 0, $x, $y, $newImageWidth, $newImageHeight, $width, $height);
		switch($imageType) {
			case "image/gif":
				  imagegif($newImage, $destImage); 
				break;
			  case "image/pjpeg":
			case "image/jpeg":
			case "image/jpg":
				  imagejpeg($newImage, $destImage, 95); 
				break;
			case "image/png":
			case "image/x-png":
				imagepng($newImage, $destImage);  
				break;
		}
		@chmod($destImage, 0755);
		return $destImage;
	}

	/**
	 * Generate a jpeg thumbnail from an image
	 *
	 * @static
	 * @access public
	 * @param  string    $src_file         Source file image
	 * @param  string    $dst_file         Destination file image
	 * @param  integer   $resize_width     Desired image width
	 * @param  integer   $resize_height    Desired image height
	 */
	public static function generate_thumbnail($src_file, $dst_file, $resize_width=250, $resize_height=100) {
		$metadata = getimagesize($src_file);
		$src_width = $metadata[0];
		$src_height = $metadata[1];
		$dst_y = 0;
		$dst_x = 0;
		if (empty($metadata['mime'])) {
			return;
		}
		if ($resize_width == 0 && $resize_height == 0) {
			$resize_width = 100;
			$resize_height = 100;
		}
		if ($resize_width > 0 && $resize_height == 0) {
			$dst_width = $resize_width;
			$dst_height = floor($src_height * ($resize_width / $src_width));
			$dst_image = imagecreatetruecolor($dst_width, $dst_height);
		} elseif ($resize_width == 0 && $resize_height > 0) {
			$dst_width = floor($src_width * ($resize_height / $src_height));
			$dst_height = $resize_height;
			$dst_image = imagecreatetruecolor($dst_width, $dst_height);
		} else {
			$src_ratio = $src_width / $src_height;
			$resize_ratio = $resize_width / $resize_height;
			if ($src_ratio <= $resize_ratio) {
				$dst_width = $resize_width;
				$dst_height = floor($src_height * ($resize_width / $src_width));
				$dst_y = ($dst_height - $resize_height) / 2 * (-1);
			} else {
				$dst_width = floor($src_width * ($resize_height / $src_height));
				$dst_height = $resize_height;
				$dst_x = ($dst_width - $resize_width) / 2 * (-1);
			}
			$dst_image = imagecreatetruecolor($resize_width, $resize_height);
		}
		switch ($metadata['mime']) {
			case 'image/jpeg':
			case 'image/jpg':
				$src_image = imagecreatefromjpeg($src_file);
				break;
			case 'image/png':
				$src_image = imagecreatefrompng($src_file);
				break;
			case 'image/gif':
				$src_image = imagecreatefromgif($src_file);
				break;
			default:
				return;
		}
		imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, 0, 0, $dst_width, $dst_height, $src_width, $src_height);
		imagejpeg($dst_image, $dst_file);
		imagedestroy($dst_image);
	}



}
