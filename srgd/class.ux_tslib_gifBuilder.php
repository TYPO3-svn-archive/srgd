<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2010 Jan Bednarik (info@bednarik.org)
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

/**
 * @author	Jan Bednarik <info@bednarik.org>
 */
class ux_tslib_gifBuilder extends tslib_gifBuilder {

    /**
     * Converts $imagefile to another file in temp-dir of type $newExt (extension).
     *
     * @param	string		The image filepath
     * @param	string		New extension, eg. "gif", "png", "jpg", "tif". If $newExt is NOT set, the new imagefile will be of the original format. If newExt = 'WEB' then one of the web-formats is applied.
     * @param	string		Width. $w / $h is optional. If only one is given the image is scaled proportionally. If an 'm' exists in the $w or $h and if both are present the $w and $h is regarded as the Maximum w/h and the proportions will be kept
     * @param	string		Height. See $w
     * @param	string		Additional ImageMagick parameters.
     * @param	string		Refers to which frame-number to select in the image. '' or 0 will select the first frame, 1 will select the next and so on...
     * @param	array		An array with options passed to getImageScale (see this function).
     * @param	boolean		If set, then another image than the input imagefile MUST be returned. Otherwise you can risk that the input image is good enough regarding messures etc and is of course not rendered to a new, temporary file in typo3temp/. But this option will force it to.
     * @return	array		[0]/[1] is w/h, [2] is file extension and [3] is the filename.
     * @see getImageScale(), typo3/show_item.php, fileList_ext::renderImage(), tslib_cObj::getImgResource(), SC_tslib_showpic::show(), maskImageOntoImage(), copyImageOntoImage(), scale()
     */
    function imageMagickConvert($imagefile, $newExt='', $w='', $h='', $params='', $frame='', $options='', $mustCreate=0) {
        if ($this->NO_IMAGE_MAGICK) {
            if ($info = $this->getImageDimensions($imagefile)) {
                $newExt = strtolower(trim($newExt));
                if (!$newExt) { // If no extension is given the original extension is used
                    $newExt = $info[2];
                }
                if ($newExt == 'web') {
                    if (t3lib_div::inList($this->webImageExt, $info[2])) {
                        $newExt = $info[2];
                    } else {
                        $newExt = $this->gif_or_jpg($info[2], $info[0], $info[1]);
                        if (!$params) {
                            $params = $this->cmds[$newExt];
                        }
                    }
                }
                if (t3lib_div::inList($this->imageFileExt, $newExt)) {
                    if (strstr($w . $h, 'm')) {
                        $max = 1;
                    } else {
                        $max = 0;
                    }

                    $data = $this->getImageScale($info, $w, $h, $options);
                    $w = $data['origW'];
                    $h = $data['origH'];

                    // if no convertion should be performed
                    $wh_noscale = (!$w && !$h) || ($data[0] == $info[0] && $data[1] == $info[1]);  // this flag is true if the width / height does NOT dictate the image to be scaled!! (that is if no w/h is given or if the destination w/h matches the original image-dimensions....

                    if ($wh_noscale && !$data['crs'] && !$params && !$frame && $newExt == $info[2] && !$mustCreate) {
                        $info[3] = $imagefile;
                        return $info;
                    }
                    $info[0] = $data[0];
                    $info[1] = $data[1];

                    $frame = $this->noFramePrepended ? '' : intval($frame);

                    if (!$params) {
                        $params = $this->cmds[$newExt];
                    }

                    // Cropscaling:
                    if ($data['crs']) {
                        if (!$data['origW']) {
                            $data['origW'] = $data[0];
                        }
                        if (!$data['origH']) {
                            $data['origH'] = $data[1];
                        }
                        $offsetX = intval(($data[0] - $data['origW']) * ($data['cropH'] + 100) / 200);
                        $offsetY = intval(($data[1] - $data['origH']) * ($data['cropV'] + 100) / 200);
                        $params .= ' -crop ' . $data['origW'] . 'x' . $data['origH'] . '+' . $offsetX . '+' . $offsetY . ' ';
                    }

                    $command = $this->scalecmd . ' ' . $info[0] . 'x' . $info[1] . '! ' . $params . ' ';
                    $cropscale = ($data['crs'] ? 'crs-V' . $data['cropV'] . 'H' . $data['cropH'] : '');

                    if ($this->alternativeOutputKey) {
                        $theOutputName = t3lib_div::shortMD5($command . $cropscale . basename($imagefile) . $this->alternativeOutputKey . '[' . $frame . ']');
                    } else {
                        $theOutputName = t3lib_div::shortMD5($command . $cropscale . $imagefile . filemtime($imagefile) . '[' . $frame . ']');
                    }
                    if ($this->imageMagickConvert_forceFileNameBody) {
                        $theOutputName = $this->imageMagickConvert_forceFileNameBody;
                        $this->imageMagickConvert_forceFileNameBody = '';
                    }

                    // Making the temporary filename:
                    $this->createTempSubDir('pics/');
                    $output = $this->absPrefix . $this->tempPath . 'pics/' . $this->filenamePrefix . $theOutputName . '.' . $newExt;

                    // Register temporary filename:
                    $GLOBALS['TEMP_IMAGES_ON_PAGE'][] = $output;

                    if ($this->dontCheckForExistingTempFile || !$this->file_exists_typo3temp_file($output, $imagefile)) {
                        $this->GDImageExec($imagefile, $output, $data);
                    }
                    if (file_exists($output)) {
                        $info[3] = $output;
                        $info[2] = $newExt;
                        if ($params) { // params could realisticly change some imagedata!
                            $info = $this->getImageDimensions($info[3]);
                        }
                        if ($info[2] == $this->gifExtension && !$this->dontCompress) {
                            t3lib_div::gif_compress($info[3], '');  // Compress with IM (lzw) or GD (rle)  (Workaround for the absence of lzw-compression in GD)
                        }
                        return $info;
                    }
                }
            }
        }
        return parent::imageMagickConvert($imagefile, $newExt, $w, $h, $params, $frame, $options, $mustCreate);
    }

    function GDImageExec($imageSrc, $output, $data) {

        $image = ImageCreateFromString(file_get_contents($imageSrc));


        // Get the size and MIME type of the requested image
        $size = GetImageSize($imageSrc);
        $mime = $size['mime'];

        $width = $size[0];
        $height = $size[1];

        $maxWidth = $data['origW'];
        $maxHeight = $data['origH'];

        $color = FALSE;

        // Ratio cropping
        $offsetX = 0;
        $offsetY = 0;

        if ($maxWidth <= 0 && $maxHeight > 0) {
            $maxWidth = $width / $height * $maxHeight;
        } else if ($maxHeight <= 0 && $maxWidth > 0) {
            $maxHeight = $height / $width * $maxWidth;
        } else if ($maxHeight <= 0 && $maxWidth <= 0){
            $maxWidth = $width;
            $maxHeight = $height;
        }
        
        $cropRatio[0] = $maxWidth;
        $cropRatio[1] = $maxHeight;

        //var_dump($cropRatio);

        if (count($cropRatio) == 2) {
            $ratioComputed = $width / $height;
            $cropRatioComputed = (float) $cropRatio[0] / (float) $cropRatio[1];

            if ($ratioComputed < $cropRatioComputed) {
                // Image is too tall so we will crop the top and bottom
                $origHeight = $height;
                $height = $width / $cropRatioComputed;
                $offsetY = ($origHeight - $height) / 2;
            } else if ($ratioComputed > $cropRatioComputed) {
                // Image is too wide so we will crop off the left and right sides
                $origWidth = $width;
                $width = $height * $cropRatioComputed;
                $offsetX = ($origWidth - $width) / 2;
            }
        }

        // Setting up the ratios needed for resizing. We will compare these below to determine how to
        // resize the image (based on height or based on width)
        $xRatio = $maxWidth / $width;
        $yRatio = $maxHeight / $height;

        if ($xRatio * $height < $maxHeight) {
            // Resize the image based on width
            $tnHeight = ceil($xRatio * $height);
            $tnWidth = $maxWidth;
        } else {
            // Resize the image based on height
            $tnWidth = ceil($yRatio * $width);
            $tnHeight = $maxHeight;
        }

        $quality = 90;

        // Set up a blank canvas for our resized image (destination)
        $dst = imagecreatetruecolor($tnWidth, $tnHeight);

        // Set up the appropriate image handling functions based on the original image's mime type
        switch ($size['mime']) {
            case 'image/gif':
                // We will be converting GIFs to PNGs to avoid transparency issues when resizing GIFs
                // This is maybe not the ideal solution, but IE6 can suck it
                $creationFunction = 'ImageCreateFromGif';
                $outputFunction = 'ImagePng';
                $mime = 'image/png'; // We need to convert GIFs to PNGs
                $doSharpen = FALSE;
                $quality = round(10 - ($quality / 10)); // We are converting the GIF to a PNG and PNG needs a compression level of 0 (no compression) through 9
                break;

            case 'image/x-png':
            case 'image/png':
                $creationFunction = 'ImageCreateFromPng';
                $outputFunction = 'ImagePng';
                $doSharpen = FALSE;
                $quality = round(10 - ($quality / 10)); // PNG needs a compression level of 0 (no compression) through 9
                break;

            default:
                $creationFunction = 'ImageCreateFromJpeg';
                $outputFunction = 'ImageJpeg';
                $doSharpen = TRUE;
                break;
        }

        // Read in the original image
        $src = $creationFunction($imageSrc);

        if (in_array($size['mime'], array('image/gif', 'image/png'))) {
            if (!$color) {
                // If this is a GIF or a PNG, we need to set up transparency
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            } else {
                // Fill the background with the specified color for matting purposes
                if ($color[0] == '#')
                    $color = substr($color, 1);

                $background = FALSE;

                if (strlen($color) == 6)
                    $background = imagecolorallocate($dst, hexdec($color[0] . $color[1]), hexdec($color[2] . $color[3]), hexdec($color[4] . $color[5]));
                else if (strlen($color) == 3)
                    $background = imagecolorallocate($dst, hexdec($color[0] . $color[0]), hexdec($color[1] . $color[1]), hexdec($color[2] . $color[2]));
                if ($background)
                    imagefill($dst, 0, 0, $background);
            }
        }

        // Resample the original image into the resized canvas we set up earlier
        ImageCopyResampled($dst, $src, 0, 0, $offsetX, $offsetY, $tnWidth, $tnHeight, $width, $height);

        if ($doSharpen && function_exists('imageconvolution')) {
            // Sharpen the image based on two things:
            //	(1) the difference between the original size and the final size
            //	(2) the final size
            $sharpness = $this->findSharp($width, $tnWidth);

            $sharpenMatrix = array(
                array(-1, -2, -1),
                array(-2, $sharpness + 12, -2),
                array(-1, -2, -1)
            );
            $divisor = $sharpness;
            $offset = 0;
            imageconvolution($dst, $sharpenMatrix, $divisor, $offset);
        }

        // Write the resized image to the cache
        $outputFunction($dst, $output, $quality);

        return true;
    }

    // function from Ryan Rud (http://adryrun.com)
    function findSharp($orig, $final) {
        $final = $final * (750.0 / $orig);
        $a = 52;
        $b = -0.27810650887573124;
        $c = .00047337278106508946;

        $result = $a + $b * $final + $c * $final * $final;

        return max(round($result), 0);
    }

}

?>
