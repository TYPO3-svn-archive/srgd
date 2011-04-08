<?php
if (!defined ('TYPO3_MODE'))     die ('Access denied.');

//turn image processing on
$TYPO3_CONF_VARS['GFX']['image_processing'] = '1';
//disable imagemagick
$TYPO3_CONF_VARS['GFX']['im'] = '0';
//enable use of gd
$TYPO3_CONF_VARS['GFX']['gdlib'] = '1';

$TYPO3_CONF_VARS['GFX']['gdlib'] = '1';

$GLOBALS['TYPO3_CONF_VARS']['GFX']['thumbnails'] = 1;

$TYPO3_CONF_VARS['FE']['XCLASS']['tslib/class.tslib_gifbuilder.php']=t3lib_extMgm::extPath($_EXTKEY).'class.ux_tslib_gifBuilder.php';
$TYPO3_CONF_VARS['BE']['XCLASS']['t3lib/thumbs.php']=t3lib_extMgm::extPath($_EXTKEY).'thumbs.php';
?>
