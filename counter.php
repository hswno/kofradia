<?php

require "base/mod.php_source.php";

ob_start();

// The images that contains a number from 0 to 9
$folder = "../imgs/tall_counter/";
$imgnum = array(
	$folder . "0.png",
	$folder . "1.png",
	$folder . "2.png",
	$folder . "3.png",
	$folder . "4.png",
	$folder . "5.png",
	$folder . "6.png",
	$folder . "7.png",
	$folder . "8.png",
	$folder . "9.png"
);

$count = intval($_GET['count']);

// Find the width and height to use on the number images
if (!file_exists($imgnum[0])) {
	die("Could not find image with the number...");
}
$numberinfo = getimagesize($imgnum[0]);
$width = $numberinfo[0];
$height = $numberinfo[1];


if (($count = (string)$count) < 1000)
{
	$count = str_pad((string)$count, 4, "0", STR_PAD_LEFT);
}

// Split the combination to an array
$comb = array();
for ($i = 0; $i < strlen($count); $i++) {
	$comb[] = substr($count, $i, 1);
}


// Opens the images
$img = array();
foreach ($comb as $num) {
	$img[] = call_user_func("imagecreatefrompng", $imgnum[$num]);
}

// Creates our new image
$image = imagecreatetruecolor($width*count($img), $height);

// Copies over the numbers to the new image
$i = 0;
foreach ($img as $num) {
	imagecopyresampled($image, $num, $width*$i, 0, 0, 0, $width, $height, $width, $height);
	imagedestroy($num);
	$i++;
}

// export the image
imagejpeg($image, "", 90);
imagedestroy($image);

$mimetype = "image/jpeg";

// set headers
header("Content-Type: $mimetype");
header("Content-Disposition: inline; filename=counter.$count.jpg");
header("Content-Length: ".ob_get_length());

header("Expires: Mon, 18 Jul 2005 00:00:00 GMT");
header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");

// HTTP/1.1
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);

// HTTP/1.0
header("Pragma: no-cache");
header("Connection: close");