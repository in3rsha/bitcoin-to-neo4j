<?php
/*
 * author:  Greg Walker
 * website: http://learnmeabitcoin.com
 * license: GPLv3
*/

require_once('basic.php');

const TARGET_MAX = '00000000FFFF0000000000000000000000000000000000000000000000000000';

function calculateBlockReward($blockcount) {
    // set the block rewards array
    $reward = 5000000000;
    $i = 1;
    while ($reward >= 1) {
        $blockrewards[$i] = $reward;
        $reward = floor($reward/2);
        $i++;
    }

    // work out the block reward level
    $block = 0;
    $level = 0;
    while ($blockcount >= $block) {
        $block += 210000;
        $level += 1;
    }
	
	// return the value in satoshis
	return $blockrewards[$level];
}

function bitstotarget($bits) {
 
	// remove the 0x prefix (if it's there)
	if (substr($bits, 0, 2) == '0x') {
		$bits = ltrim($bits, '0x');
	}
 
	// get the two parts
	$exponent = substr($bits, 0, 2);
	$coefficient = substr($bits, 2, 6);
 
	// calculate the size of the target in bytes
	$bytes = hexdec($exponent);
 
	// form the size of the target with the coefficient at the start
	$target = str_pad($coefficient, (($bytes) * 2), '0' );
 
	// return a 32-byte target (without the 0x at the start)
	return str_pad($target, 32*2, '0', STR_PAD_LEFT);
 
}

function bitstodifficulty($bits) {

	$targetmax = bchexdec(TARGET_MAX);
	$target    = bchexdec(bitstotarget($bits));
	$difficulty = $targetmax/$target;

	return $difficulty;
}


function difficultytotarget($difficulty) {
	$targetmax = bchexdec(TARGET_MAX); //26959535291011309493156476344723991336010898738574164086137773096960';
	$target = bcdiv($targetmax, $difficulty);
	
	return str_pad(bcdechex($target), 64, '0', STR_PAD_LEFT);
}

function targettobits($target) {
	$bytes = str_split($target, 2);

	// 1. coefficient
	$i = 1;
	$coefficient = '';
	foreach ($bytes as $byte) {
		if ($byte != '00' && $i <= 3) {
			$coefficient .= $byte;
			$i++;
		}
	}

	// 2. exponent
	$position = strpos($target, $coefficient);
	$exponent = dechex((64 - $position) / 2);

	// 3. bits
	$bits = $exponent.$coefficient;
	return $bits;
}

function merklerootbinary($txids) {
 
	// Stop recursion if there is only one hash value left, because that's the merkle root.
	if (count($txids) == 1) {
		$merkleroot = $txids[0];
		return $merkleroot;
	}
 
	else {
 
		// Create the new array of hashes		
		while (count($txids) > 0) {
 
			if (count($txids) >= 2) {
				// Get first two
				$pair_first = $txids[0];
				$pair_second = $txids[1];
 
				// Hash them (double SHA256)
				$pair = $pair_first.$pair_second;
				$pairhashes[] = hash('sha256', hash('sha256', $pair, true), true);
 
				// Remove those two from the array
				unset($txids[0]);
				unset($txids[1]);
 
				// Re-set the indexes (the above just nullifies the values) and make a new array without the original first two slots.
				$txids = array_values($txids);
			}
 
			if (count($txids) == 1) {
				// Get the first one twice
				$pair_first = $txids[0];
				$pair_second = $txids[0];
 
				// Hash it with itself (double SHA256)
				$pair = $pair_first.$pair_second;
				$pairhashes[] = hash('sha256', hash('sha256', $pair, true), true);
 
				// Remove it from the array
				unset($txids[0]);
 
				// Re-set the indexes (the above just nullifies the values) and make a new array without the original first two slots.
				$txids = array_values($txids);
			}
 
		}
 
		// Recursion bit. Re-apply this function to the new array of hashes we've just created.
		return merklerootbinary($pairhashes);
 
	}
 
}
 
function merkleroot($txids) {
 
	// Convert txids in to big endian (BE), because that's the format they need to be in to get the merkle root.
	foreach ($txids as $txid) {
		$txidsBE[] = swapEndian($txid);
	}
 
	// Now convert each of these txids in to binary, because the hash function wants the binary value, not the hex.
	foreach ($txidsBE as $txidBE) {
		$txidsBEbinary[] = hex2bin($txidBE);
	}
 
	// Work out the merkle root (in binary) using that lovely recursive function above.
	$merkleroot = merklerootbinary($txidsBEbinary);
 
	// Convert the merkle root in to hexadecimal and little-endian, because that's how it's stored in the block header.
	$merkleroot = swapEndian(bin2hex($merkleroot));
 
	// Return it :)
	return $merkleroot;
 
}
