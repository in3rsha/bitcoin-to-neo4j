<?php
/*
 * author:  Greg Walker
 * website: http://learnmeabitcoin.com
 * license: GPLv3
*/

function hash256($hex) {
	$binary = hex2bin($hex);
	$hash1 = hash("sha256", $binary, true); // "true" returns binary value (or will return hex by default)
	$hash2 = hash("sha256", $hash1);
	return $hash2;
}
  
function hash160($hex) {
	$binary = hex2bin($hex);
	$hash1 = hash("sha256", $binary, true);
	$hash2 = hash("ripemd160", $hash1);
	return $hash2;
}
