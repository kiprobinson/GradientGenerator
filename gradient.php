<?php

/**
 * Programmatically generates a gradient image.
 * 
 * This file operates as a stand-alone PHP file, and will output necessary HTTP headers
 * for the browser to render as an image.
 * 
 * Example: <img src="gradient.php?start=ff0000&end=ffff00&length=200" />
 * 
 * Further documentation available on GitHub Wiki page: https://github.com/kiprobinson/GradientGenerator/wiki
 * 
 * $_GET parameters:
 * start       The color at the start of the gradient.  Specified as a 3-, 6-, or 8-digit
 *             hexadecimal string. The two most-significant-digits specify the 7-bit
 *             alpha channel, from 00 (opaque) to 7f (transparent).
 * end         The color at the end of the gradient. Specified as a 3-, 6-, or 8-digit
 *             hexadecimal string. The two most-significant-digits specify the 7-bit
 *             alpha channel, from 00 (opaque) to 7f (transparent).
 * length      The length of this gradient, in pixels.
 * angle       The angle of the gradient, in degrees. An integer from 0 to 360, inclusive.
 *             0 indicates a gradient from left to right; 90 indicates a gradient from top
 *             to bottom. This is backwards from trigonometry, because the y-axis in an image
 *             points downward. For shorthand, this can be 'h' for horizontal (0), or 'v' for
 *             vertical (90). Defaults to 0 in the case of invalid input.
 * extend      A boolean flag, indicating whether or not the gradient image should be extended
 *             in order to include the entire image.
 * 
 * 
 * define'd constants:
 * CACHE_DIR      The directory in which generated gradients will be stored.
 *                This directory must have rwx privileges.
 * MAX_CACHE_SIZE The maximum disk space which will be used, in bytes, by the
 *                cache directory.
 * MIN_WIDTH      The minimum width of the gradient. Mainly applicable to
 *                horizontal or vertical gradients.
 * MIN_LENGTH     The minimum acceptable value for $_GET['length'].
 * MAX_LENGTH     The maximum acceptable value for $_GET['length'].
 * ERR_DIFFUSION  Whether or not to use error diffusion.
 * 
 * @author Kip Robinson, https://github.com/kiprobinson
 */

define('CACHE_DIR', $_SERVER['DOCUMENT_ROOT'] . '/../gradient_cache');
define('MAX_CACHE_SIZE', 5*1024*1024); //5 MB
define('MIN_WIDTH', 4);
define('MIN_LENGTH', 4);
define('MAX_LENGTH', 9999);
define('ERR_DIFFUSION', true);

//Validate/sanitize $_GET parameters
//---------------------------------------------------------
$start = get_color(strval($_GET['start']));
$start_hex = str_pad(dechex(intval($start)), 8, '0', STR_PAD_LEFT);

$end =  get_color(strval($_GET['end']));
$end_hex = str_pad(dechex(intval($end)), 8, '0', STR_PAD_LEFT);

$extend = intboolval($_GET['extend']);

$angle = strval($_GET['angle']);
if($angle == 'h')
  $angle = 0;
else if($angle == 'v')
  $angle = 90;
else
  $angle = clampAngle($angle, $extend);

$length = clamp(intval($_GET['length']), MIN_LENGTH, MAX_LENGTH);

//If we haven't cached this gradient, generate it.
//---------------------------------------------------------
$file = CACHE_DIR . "/$start_hex-$end_hex-$length-$angle-$extend.png";
if(!file_exists($file))
{
  clean_cache();
  
  $r = $length - 1; //length of gradient. (Use 1 less than $length so that last pixel is $end color.)
  $theta = deg2rad($angle); //$angle in radians
  $sinTheta = sin($theta); //precompute for convenience
  $cosTheta = cos($theta); //precompute for convenience
  $Rx = $r * $cosTheta; //x coordinate of end of gradient
  $Ry = $r * $sinTheta; //y coordinate of end of graident
  
  $w = $extend && abs($cosTheta) > 0.0001 ? $length / $cosTheta : $length * $cosTheta;
  $h = $extend && abs($sinTheta) > 0.0001 ? $length / $sinTheta : $length * $sinTheta;
  
  $offsetX = $w < 0 ? $w : 0;
  $offsetY = $h < 0 ? $h : 0;
  
  $w = max(abs($w), MIN_WIDTH);
  $h = max(abs($h), MIN_WIDTH);
  
  $start_a = ($start >> 24) & 0x7f;
  $start_r = ($start >> 16) & 0xff;
  $start_g = ($start >>  8) & 0xff;
  $start_b = ($start      ) & 0xff;
  
  $end_a = ($end >> 24) & 0x7f;
  $end_r = ($end >> 16) & 0xff;
  $end_g = ($end >>  8) & 0xff;
  $end_b = ($end      ) & 0xff;
  
  $image = imagecreatetruecolor($w, $h);
  
  //Allow us to save the alpha channel if either color is not opaque.
  if($start_a > 0 || $end_a > 0)
  {
    imagealphablending($image, false);
    imagesavealpha($image, true);
  }
  
  $den = $r*$r;
  $err_a = 0.0;
  $err_r = 0.0;
  $err_g = 0.0;
  $err_b = 0.0;
  
  for($col = 0, $x = $offsetX; $col < $w; $col++, $x++)
  {
    $xComp = $Rx * $x;
    
    for($row = 0, $y = $offsetY; $row < $h; $row++, $y++)
    {
      $yComp = $Ry * $y;
      
      //Note: The color c at point P=<x,y> is given by:
      // m = P*R / |R|^2 = (x * Rx + y * Ry) / r^2
      // c = start * (1 - m) + end * m
      $m = ($xComp + $yComp) / $den;
      
      if($m <= 0)
      {
        $act_a = $start_a;
        $act_r = $start_r;
        $act_g = $start_g;
        $act_b = $start_b;
      }
      if($m >= 1)
      {
        $act_a = $end_a;
        $act_r = $end_r;
        $act_g = $end_g;
        $act_b = $end_b;
      }
      else
      {
        $act_a = $start_a*(1 - $m) + $end_a*$m;
        $act_r = $start_r*(1 - $m) + $end_r*$m;
        $act_g = $start_g*(1 - $m) + $end_g*$m;
        $act_b = $start_b*(1 - $m) + $end_b*$m;
      }
      
      $a = round($act_a);
      $r = round($act_r);
      $g = round($act_g);
      $b = round($act_b);
      
      if(ERR_DIFFUSION)
      {
        $err_a += $act_a - $a;
        $err_r += $act_r - $r;
        $err_g += $act_g - $g;
        $err_b += $act_b - $b;
        
        $cor_a = abs_floor($err_a);
        $cor_r = abs_floor($err_r);
        $cor_g = abs_floor($err_g);
        $cor_b = abs_floor($err_b);
        
        $err_a -= $cor_a;
        $err_r -= $cor_r;
        $err_g -= $cor_g;
        $err_b -= $cor_b;
      }
      else
      {
        $cor_a = 0;
        $cor_r = 0;
        $cor_g = 0;
        $cor_b = 0;
      }
      
      $color = (clamp($a + $cor_a, 0, 0x7f) << 24)
             | (clamp($r + $cor_r, 0, 0xff) << 16)
             | (clamp($g + $cor_g, 0, 0xff) <<  8)
             | (clamp($b + $cor_b, 0, 0xff)      );
      
      imagesetpixel($image, $col, $row, $color);
    }
  }
  
  //Write the image to the cache.
  imagepng($image, $file);
  imagedestroy($image);
}

//Handle If-Modified-Since header
if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']))
{
  $if_modified_since=strtotime(preg_replace('/;.*$/','',$_SERVER['HTTP_IF_MODIFIED_SINCE']));
  if($if_modified_since !== false && $if_modified_since >= filemtime($file))
  {
    header('HTTP/1.1 304 Not Modified', true, 304);
    exit();
  }
}

//Write headers to convince browser that this is an image
//and not a web page.  Use "Expires" header to let browser
//cache the image locally (it's not like it's going to change).
//---------------------------------------------------------
header('Content-type: image/png');
header('Content-Length: ' . filesize($file));
header('Pragma:'); //undo the pragma garbage PHP already wrote
header('Expires: ' . gmdate('r', mktime() + 3600*24*365));
header('Cache-Control: max-age=' . 3600*24*365 . ', public');
header('Last-Modified: ' . gmdate('r', filemtime($file)));

//Finally, output the file and we're done!
//---------------------------------------------------------
readfile($file);


// ========================================================
// Helper functions
// ========================================================


/**
 * Converts a 3-, 6-, or 8-digit hex string into a 32-bit int color.
 */ 
function get_color($strVal)
{
  if(strlen($strVal) == 3)
    $strVal = $strVal[0] . $strVal[0] . $strVal[1] . $strVal[1] . $strVal[2] . $strVal[2];
  return clamp(hexdec(strval($strVal)), 0, 0x7fffffff);
}

/**
 * Rounds $x towards zero (like floor for positive $x, ciel for negative $x).
 * 
 * Examples:
 *   abs_floor(1.999) :  1
 *   abs_floor(1.2)   :  1
 *   abs_floor(0.9)   :  0
 *   abs_floor(-0.9)  :  0
 *   abs_floor(-1.2)  : -1
 *   abs_floor(-1.999): -1
 */
function abs_floor($x)
{
  return $x < 0 ? ceil($x) : floor($x);
}

/**
 * Returns 1 or 0 based on whether the given value evaluates to true or false.
 */ 
function intboolval($b)
{
  return $b ? 1 : 0;
}


/**
 * Limits $val to the range [$min..$max].
 */ 
function clamp($val, $min, $max)
{
  if ($min > $max)
    return clamp($val, $max, $min);
  else if ($val < $min)
    return $min;
  else if ($val > $max)
    return $max;
  else
    return $val;
}


function clampAngle($angle, $extend)
{
  $angle = clamp(intval($angle), 0, 360);
  if($extend)
  {
    if($angle % 90 < 15)
      $angle -= $angle % 90;
    elseif($angle % 90 > 75)
      $angle += 90 - $angle % 90;
  }
  return $angle % 360;
}

/**
 * Cleans up the cache to limit the total size of all cached gradients
 * to MAX_CACHE_SIZE.  Least-recently-used files will be deleted first.
 * Requires execute permission on cache directory.
 */ 
function clean_cache()
{
  $tmp_files = scandir(CACHE_DIR);
  $files = array();
  foreach($tmp_files as $file)
  {
    $path = CACHE_DIR . "/$file";
    //ignore subdirectories if there are any...
    if (is_file($path))
      $files[] = $path;
  }
  unset($tmp_files);
  
  //sort newest to oldest
  usort($files, create_function('$a, $b', 'return fileatime($b)-fileatime($a);') );
  
  //Compute the size (storage space) of cached files, starting with most recent.  Once that
  //  size is over MAX_CACHE_SIZE, delete that file and any older files.
  $cache_size = 0;
  foreach($files as $file)
  {
    $cache_size += filesize($file);
    if ($cache_size > MAX_CACHE_SIZE)
      @unlink($file);
  }
}

