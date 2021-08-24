<?php
/*
 * author:  Greg Walker
 * website: http://learnmeabitcoin.com
 * license: GPLv3
*/

function swapEndian($data) {
    return implode('', array_reverse(str_split($data, 2)));
}

function fieldSize($field, $bytes = 4) {
    $length = $bytes * 2;
    $result = str_pad($field, $length, '0', STR_PAD_LEFT);
    return $result;
}

function ascii2hex($ascii) {
	$hex = '';
	for ($i = 0; $i < strlen($ascii); $i++) {
		$byte = strtoupper(dechex(ord($ascii[$i])));
		$byte = str_repeat('0', 2 - strlen($byte)).$byte;
		$hex .= $byte;
	}
	return $hex;
}

function hex2ascii($hex) {
    $str = '';
    for($i=0; $i<strlen($hex); $i+=2) {
		$byte = substr($hex, $i, 2);
		$dec = hexdec($byte);
		$str .= chr($dec);
	}
    return filter_var($str, FILTER_SANITIZE_SPECIAL_CHARS); // Prevent an XSS attack from a hex encoded ascii string
}

function varInt($data) { // Calculates the full variable integer and returns it
    $varint = strtolower(substr($data, 0, 2));

    if     ($varint == 'fd') { $value = substr($data, 2, 4);  $full = $varint.$value; $len = 6;}
    elseif ($varint == 'fe') { $value = substr($data, 2, 8);  $full = $varint.$value; $len = 10;}
    elseif ($varint == 'ff') { $value = substr($data, 2, 16); $full = $varint.$value; $len = 18;}
    else                     { $value = $varint; $full = $varint; $len = 2; }

    $value = hexdec(swapEndian($value)); // convert value to a usable decimal number

    return array($full, $value, $len);
}

function toVarInt($i) {
	if ($i < 253) {
		return fieldSize(dechex($i), 1);
	}
	if (253 <= $i && $i < 65535) {
		return 'fd'.swapEndian(fieldSize(dechex($i), 2));
	}
	if (65535 <= $i && $i < 4294967295) {
		return 'fe'.swapEndian(fieldSize(dechex($i), 4));
	}
	if (4294967295 <= $i) {
		return 'ff'.swapEndian(fieldSize(dechex($i), 8));
	}
}

function bchexdec($hex) {
    if(strlen($hex) == 1) {
        return hexdec($hex);
    } else {
        $remain = substr($hex, 0, -1);
        $last = substr($hex, -1);
        return bcadd(bcmul(16, bchexdec($remain)), hexdec($last));
    }
}

function bcdechex($dec) {
    $last = bcmod($dec, 16);
    $remain = bcdiv(bcsub($dec, $last), 16);

    if($remain == 0) {
        return dechex($last);
    } else {
        return bcdechex($remain).dechex($last);
    }
}

