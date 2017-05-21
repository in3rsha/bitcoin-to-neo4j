<?php
/*
 * author:  Greg Walker
 * website: http://learnmeabitcoin.com
 * license: GPLv3
*/

include_once "functions/basic.php";

function readtx($transactions, $p=0) {

	// Start Storing
	$txbuffer = ''; // clear the tx buffer, ready to start storing a tx data
	
	// version (4 bytes)
	$txbuffer .= substr($transactions, $p, 8); $p+=8;

	// if segwit [00][01]
	if (hexdec(substr($transactions, $p, 2)) == 0 && hexdec(substr($transactions, $p+2, 2)) > 0) {
		$segwit = true;
		$txbuffer .= substr($transactions, $p, 4); $p+=4; // take the [marker][flag] and move on
	}
	else {
		$segwit = false;
	}

	// inputs
	list($full, $value, $len) = varInt(substr($transactions, $p));
	$txbuffer .= $full; $p+=$len; // inputcount (varint)
	$inputcount = $value;
	
	for ($i=1; $i<=$inputcount; $i++) {
		$txbuffer .= substr($transactions, $p, 64); $p+=64; // txid (32 bytes)
		$txbuffer .= substr($transactions, $p, 8); $p+=8; // vout (4 bytes)
		list($full, $value, $len) = varInt(substr($transactions, $p)); // (varint)
		$txbuffer .= $full; $p+=$len; // scriptSig size
		$size = $value*2; // number of chars
		$txbuffer .= substr($transactions, $p, $size); $p += $size; // scriptSig
		$txbuffer .= substr($transactions, $p, 8); $p+=8; // sequence
	}

	// outputs
	list($full, $value, $len) = varInt(substr($transactions, $p));
	$txbuffer .= $full; $p+=$len; // outputcount (varint)
	$outputcount = $value;

	for ($i=1; $i<=$outputcount; $i++) {
		$txbuffer .= substr($transactions, $p, 16); $p+=16; // value (8 bytes)
		list($full, $value, $len) = varInt(substr($transactions, $p)); // (varint)
		$txbuffer .= $full; $p+=$len; // scriptPubKeysize
		$size = $value*2; //  number of chars
		$txbuffer .= substr($transactions, $p, $size); $p += $size; // scriptPubKey
	}

	// get witnesses (if segwit)
	if ($segwit) {

		// number of witnesses (same as input count)
		for ($i=1; $i<=$inputcount; $i++) {
			
			// number of witness elements
			list($full, $value, $len) = varInt(substr($transactions, $p)); 
			$txbuffer .= $full; $p+=$len;
			$witnesscount = $value;
		
			for ($j=1; $j<=$witnesscount; $j++) {
				// size of witness
				list($full, $value, $len) = varInt(substr($transactions, $p));
				$txbuffer .= $full; $p+=$len;

				// witness
				$size = $value*2;
				$txbuffer .= substr($transactions, $p, $size); $p+=$size;
			}
		}
	}

	// locktime (4 bytes)
	$txbuffer .= substr($transactions, $p, 8); $p+=8;

	return array($txbuffer, $p);

}
