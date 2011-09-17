<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2009 Jan Bednarik (info@bednarik.org)
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
class ux_SC_t3lib_thumbs extends SC_t3lib_thumbs {

    /**
     * Create the thumbnail
     * Will exit before return if all is well.
     *
     * @return	void
     */
    function main() {
        global $TYPO3_CONF_VARS;

        // If file exists, we make a thumbsnail of the file.
        if ($this->input && @file_exists($this->input)) {

            // Check file extension:
            $reg = array();
            if (preg_match('/(.*)\.([^\.]*$)/i', $this->input, $reg)) {
                $ext = strtolower($reg[2]);
                $ext = ($ext == 'jpeg') ? 'jpg' : $ext;
                if ($ext == 'ttf') {
                    $this->fontGif($this->input); // Make font preview... (will not return)
                } elseif (!t3lib_div::inList($this->imageList, $ext)) {
                    $this->errorGif('Not imagefile!', $ext, basename($this->input));
                }
            } else {
                $this->errorGif('Not imagefile!', 'No ext!', basename($this->input));
            }

            // ... so we passed the extension test meaning that we are going to make a thumbnail here:
            if (!$this->size)
                $this->size = $this->sizeDefault; // default



                
// I added extra check, so that the size input option could not be fooled to pass other values. That means the value is exploded, evaluated to an integer and the imploded to [value]x[value]. Furthermore you can specify: size=340 and it'll be translated to 340x340.
            $sizeParts = explode('x', $this->size . 'x' . $this->size); // explodes the input size (and if no "x" is found this will add size again so it is the same for both dimensions)
            $sizeParts = array(t3lib_div::intInRange($sizeParts[0], 1, 1000), t3lib_div::intInRange($sizeParts[1], 1, 1000)); // Cleaning it up, only two parameters now.
            $this->size = implode('x', $sizeParts);  // Imploding the cleaned size-value back to the internal variable
            $sizeMax = max($sizeParts); // Getting max value

            $info = $this->getImageDimensions($this->input);
            $options['maxW'] = array();

            $data = $this->getImageScale($info, $sizeParts[0], $sizeParts[1], $options);
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

            $data['origW'] = $sizeParts[0];
            $data['origH'] = $sizeParts[1];

            // Init
            $outpath = PATH_site . $this->outdir;

            // Should be - ? 'png' : 'gif' - , but doesn't work (ImageMagick prob.?)
            // Ren�: png work for me
            $thmMode = t3lib_div::intInRange($TYPO3_CONF_VARS['GFX']['thumbnails_png'], 0);
            $outext = ($ext != 'jpg' || ($thmMode & 2)) ? ($thmMode & 1 ? 'png' : 'gif') : 'jpg';

            $outfile = 'tmb_' . substr(md5($this->input . $this->mtime . $this->size), 0, 10) . '.' . $outext;
            $this->output = $outpath . $outfile;

            // If thumbnail does not exist, we generate it
            if (!@file_exists($this->output)) {
            $this->GDImageExec($this->input, $this->output, $data);
            }
            // The thumbnail is read and output to the browser
            if ($fd = @fopen($this->output, 'rb')) {
                header('Content-type: image/' . $outext);
                fpassthru($fd);
                fclose($fd);
            } else {
                $this->errorGif('Read problem!', '', $this->output);
            }
        } else {
            $this->errorGif('No valid', 'inputfile!', basename($this->input));
        }
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

    /**
     * Gets the input image dimensions.
     *
     * @param	string		The image filepath
     * @return	array		Returns an array where [0]/[1] is w/h, [2] is extension and [3] is the filename.
     * @see imageMagickConvert(), tslib_cObj::getImgResource()
     */
    function getImageDimensions($imageFile) {
        if ($temp = @getImageSize($imageFile)) {
            $returnArr = Array($temp[0], $temp[1], strtolower($reg[0]), $imageFile);
        } else {
            $returnArr = $this->imageMagickIdentify($imageFile);
        }
        return $returnArr;
    }

    /**
     * Get numbers for scaling the image based on input
     *
     * @param	array		Current image information: Width, Height etc.
     * @param	integer		"required" width
     * @param	integer		"required" height
     * @param	array		Options: Keys are like "maxW", "maxH", "minW", "minH"
     * @return	array
     * @access private
     * @see imageMagickConvert()
     */
    function getImageScale($info, $w, $h, $options) {
        if (strstr($w . $h, 'm')) {
            $max = 1;
        } else {
            $max = 0;
        }

        if (strstr($w . $h, 'c')) {
            $out['cropH'] = intval(substr(strstr($w, 'c'), 1));
            $out['cropV'] = intval(substr(strstr($h, 'c'), 1));
            $crs = true;
        } else {
            $crs = false;
        }
        $out['crs'] = $crs;

        $w = intval($w);
        $h = intval($h);
        // if there are max-values...
        if ($options['maxW']) {
            if ($w) { // if width is given...
                if ($w > $options['maxW']) {
                    $w = $options['maxW'];
                    $max = 1; // height should follow
                }
            } else {
                if ($info[0] > $options['maxW']) {
                    $w = $options['maxW'];
                    $max = 1; // height should follow
                }
            }
        }
        if ($options['maxH']) {
            if ($h) { // if height is given...
                if ($h > $options['maxH']) {
                    $h = $options['maxH'];
                    $max = 1; // height should follow
                }
            } else {
                if ($info[1] > $options['maxH']) { // Changed [0] to [1] 290801
                    $h = $options['maxH'];
                    $max = 1; // height should follow
                }
            }
        }
        $out['origW'] = $w;
        $out['origH'] = $h;
        $out['max'] = $max;

        if (!$this->mayScaleUp) {
            if ($w > $info[0]) {
                $w = $info[0];
            }
            if ($h > $info[1]) {
                $h = $info[1];
            }
        }
        if ($w || $h) { // if scaling should be performed
            if ($w && !$h) {
                $info[1] = ceil($info[1] * ($w / $info[0]));
                $info[0] = $w;
            }
            if (!$w && $h) {
                $info[0] = ceil($info[0] * ($h / $info[1]));
                $info[1] = $h;
            }
            if ($w && $h) {
                if ($max) {
                    $ratio = $info[0] / $info[1];
                    if ($h * $ratio > $w) {
                        $h = round($w / $ratio);
                    } else {
                        $w = round($h * $ratio);
                    }
                }
                if ($crs) {
                    $ratio = $info[0] / $info[1];
                    if ($h * $ratio < $w) {
                        $h = round($w / $ratio);
                    } else {
                        $w = round($h * $ratio);
                    }
                }
                $info[0] = $w;
                $info[1] = $h;
            }
        }
        $out[0] = $info[0];
        $out[1] = $info[1];
        // Set minimum-measures!
        if ($options['minW'] && $out[0] < $options['minW']) {
            if (($max || $crs) && $out[0]) {
                $out[1] = round($out[1] * $options['minW'] / $out[0]);
            }
            $out[0] = $options['minW'];
        }
        if ($options['minH'] && $out[1] < $options['minH']) {
            if (($max || $crs) && $out[1]) {
                $out[0] = round($out[0] * $options['minH'] / $out[1]);
            }
            $out[1] = $options['minH'];
        }

        return $out;
    }

}

?>