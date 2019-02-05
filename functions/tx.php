<?php
/*
 * author:  Greg Walker
 * website: http://learnmeabitcoin.com
 * license: GPLv3
*/

include_once 'basic.php';
include_once 'script.php';

function decoderawtransaction($data) {

	// save the original
	$raw = $data;

	// SegWit
	if (hexdec(substr($data, 8, 2)) == 0 && hexdec(substr($data, 10, 2)) > 0) { // numinputs = 0, numoutputs > 0
		$segwit = true;
		$array['segwit'] = substr($data, 8, 4);
	}
	else {
		$segwit = false;
		$array['segwit'] = false;
	}

	// version
	$version = substr($data, 0, 8);
	$array['version'] = hexdec(swapEndian($version));
	$data = substr($data, 8);


	// remove segwit flag
	if ($segwit) {
		$data = substr($data, 4);
	}


	// inputcount
	list($invarint_full, $invarint_value, $invarint_len) = varInt($data);
	// $array['inputcount'] = $invarint_full;
	$data = substr($data, $invarint_len);

	// txins
	for ($i=0; $i<$invarint_value; $i++) {
		// txids
		$array['vin'][$i]['txid'] = swapEndian(substr($data, 0, 64));
		$data = substr($data, 64);

		// vouts
		$array['vin'][$i]['vout'] = hexdec(swapEndian(substr($data, 0, 8)));
		$data = substr($data, 8);

		// signaturesizes
		list($sigvarint_full, $sigvarint_value, $sigvarint_len) = varInt($data);
		//$array['signaturesizes'][$i] = $sigvarint_full;
		$data = substr($data, $sigvarint_len);

		// signatures
		$signaturelength = 2 * $sigvarint_value;
		$array['vin'][$i]['scriptSig']['hex'] = substr($data, 0, $signaturelength);
		$data = substr($data, $signaturelength);

		// sequence
		$sequence = substr($data, 0, 8);
		$array['vin'][$i]['sequence'] = hexdec(swapEndian($sequence));
		$data = substr($data, 8);
	}

	// outputcount
	list($outvarint_full, $outvarint_value, $outvarint_len) = varInt($data);
	//$array['outputcount'] = $outvarint_full;
	$data = substr($data, $outvarint_len);

	// txouts
	for ($i=0; $i<$outvarint_value; $i++) {
		// value
		$array['vout'][$i]['vout'] = $i;

		// value
		$array['vout'][$i]['value'] = hexdec(swapEndian(substr($data, 0, 16)));
		$data = substr($data, 16);

		// locksize
		list($lockvarint_full, $lockvarint_value, $lockvarint_len) = varInt($data);
		//$array['lockingscriptsizes'][$i] = $lockvarint_full;
		$data = substr($data, $lockvarint_len);

		// lockingscript
		$lockingscriptlength = 2 * $lockvarint_value;
		$lockingscript = substr($data, 0, $lockingscriptlength);
		$array['vout'][$i]['scriptPubKey'] = decodeScript($lockingscript);
		$data = substr($data, $lockingscriptlength);

	}

	if ($segwit) {

		$witnessdata = ''; // start storing all witness data, so it can be subtracted from full data to get the original txid

		// for each input
		for ($i=0; $i<$invarint_value; $i++) {

			$witnesshex = ''; // store individual input's witness hex data
			// 02
			// 48 3045...901
			// 21 0382...0ac

			// number of witness elements
			list($witvarint_full, $witvarint_value, $witvarint_len) = varInt($data);
			$witnesshex .= $witvarint_full;
			$data = substr($data, $witvarint_len);

			for ($j=0; $j<$witvarint_value; $j++) {

				// witnessesizes
				list($witsizevarint_full, $witsizevarint_value, $witsizevarint_len) = varInt($data);
				$witnesshex .= $witsizevarint_full;
				$data = substr($data, $witsizevarint_len);

				// witnesses
				$witnesslength = 2 * $witsizevarint_value;
				$array['vin'][$i]['witness'][$j] = substr($data, 0, $witnesslength);
				$witnesshex .= substr($data, 0, $witnesslength);
				$data = substr($data, $witnesslength);

			}

			$array['vin'][$i]['witness']['hex'] = $witnesshex;
			$witnessdata .= $witnesshex;

		}

	}


	// locktime
	$locktime = substr($data, 0, 8);
	$array['locktime'] = hexdec(swapEndian($locktime));
	$data = substr($data, 8);


	// TXID
	if ($segwit) {
		// wtxid
		$wtxid = hash("sha256", pack('H*', $raw));
		$wtxid = hash("sha256", pack('H*', $wtxid));
		$array['wtxid'] = swapEndian($wtxid);

		// original txid (remove flag and witness data)
		$withoutflag = substr($raw, 0, 8).substr($raw, 12); // remove flag
		$txid_orig = str_replace($witnessdata, '', $withoutflag); // remove witness data

		$txid = hash("sha256", pack('H*', $txid_orig));
		$txid = hash("sha256", pack('H*', $txid));
		$array['txid'] = swapEndian($txid);
	}
	else {
		$txid = hash("sha256", pack('H*', $raw));
		$txid = hash("sha256", pack('H*', $txid));
		$array['txid'] = swapEndian($txid);
	}

	// size
	$array['size'] = strlen($raw)/2;

	// return the PHP array

	return $array;

} // end function

?>
