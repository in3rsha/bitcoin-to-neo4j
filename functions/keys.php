<?php
/*
 * author:  Greg Walker
 * website: http://learnmeabitcoin.com
 * license: GPLv3
*/

include_once 'hash.php'; // hash256 and hash160

// Taken from bitcoin-lib-php
function base58_encode($hex)
  {
  		$base58chars = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz";

      if (strlen($hex) == 0) {
          return '';
      }

      // Convert the hex string to a base10 integer
      $num = gmp_strval(gmp_init($hex, 16), 58);

      // Check that number isn't just 0 - which would be all padding.
      if ($num != '0') {
          $num = strtr(
              $num,
              '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuv',
              $base58chars
          );
      } else {
          $num = '';
      }

      // Pad the leading 1's
      $pad = '';
      $n = 0;
      while (substr($hex, $n, 2) == '00') {
          $pad .= '1';
          $n += 2;
      }

      return $pad . $num;
  }

function base58_encode_checksum($hex)
  {
      $checksum = hash256($hex);
      $checksum = substr($checksum, 0, 8);
      $hash = $hex . $checksum;
      return base58_encode($hash);
  }

function hash160_to_address($hash160, $prefix = '00')
	{
	  // 00 = 1address
	  // 05 = 3address
      $hash160 = $prefix.$hash160;
      return base58_encode_checksum($hash160);
	}

function pubkey_to_address($pubkey)
	{
		return hash160_to_address(hash160($pubkey));
	}

// Bech32
function bech32_polymod($values) {
    // Internal function that computes the Bech32 checksum.
    $generator = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];

    $chk = 1;
    foreach ($values as $value) {
        $top = $chk >> 25;
        $chk = ($chk & 0x1ffffff) << 5 ^ $value;
        for ($i=0; $i<5; $i++) {
            if (($top >> $i) & 1 == 1) {
                $chk ^= $generator[$i];
            }
        }
    }

    return $chk;
}

function bech32_hrp_expand($hrp) {
    // Expand the HRP in to values for checksum computation.
    $expand1 = [];
    $expand2 = [];
    foreach (str_split($hrp) as $c) {
        $expand1[] = ord($c) >> 5; // ord char, right shifted
        $expand2[] = ord($c) & 31;
    }

    return array_merge($expand1, [0], $expand2);
}

function bech32_create_checksum($hrp, $data) {
    // Compute the checksum values given HRP and data.
    $values = array_merge(bech32_hrp_expand($hrp), $data);
    $polymod = bech32_polymod(array_merge($values, [0, 0, 0, 0, 0, 0])) ^ 1;

    $checksum = [];
    for ($i=0; $i<6; $i++) {
        $checksum[] = ($polymod >> 5 * (5 - $i)) & 31;
    }

    return $checksum;
}

function convertbits($data, $from, $to, $pad=true) {
    // General power-of-2 base conversion.
    // This is used to prepare a scriptpubkey ready for bech32 encoding.
    $acc    = 0;
    $bits   = 0;
    $ret    = [];
    $maxv   = (1 << $to) - 1;               // 0b100000 (31)
    $maxacc = (1 << ($from + $to - 1)) - 1; // 0b111111111111 (4095)

    foreach ($data as $value) {
        if ($value < 0 || ($value >> $from) != 0) {
            throw new Bech32Exception("Invalid data range for converting bits.");
        }

        $acc = (($acc << $from) | $value) & $maxacc;
        $bits += $from;
        while ($bits >= $to) {
            $bits -= $to;
            $ret[] = ($acc >> $bits) & $maxv;
        }
    }

    if ($pad) {
        if ($bits) {
            $ret[] = ($acc << ($to - $bits)) & $maxv;
        }
        elseif ($bits >= $from || (($acc << ($to - $bits)) & $maxv)) {
            throw new Bech32Exception("Invalid data.");

        }
    }

    return $ret;
}

function bech32_encode($hrp, $data) {
    // Compute a Bech32 string given HRP and data values.

    // Settings
    $separator = '1'; // always 1
    $charset = ['q', 'p', 'z', 'r', 'y', '9', 'x', '8', 'g', 'f', '2', 't', 'v', 'd', 'w', '0', 's', '3', 'j', 'n', '5', '4', 'k', 'h', 'c', 'e', '6', 'm', 'u', 'a', '7', 'l'];

    // Create the checksum from the data
    $checksum = bech32_create_checksum($hrp, $data);

    // Convert data + checksum to Bech32
    $combined = array_merge($data, $checksum);

    $base32 = [];
    foreach ($combined as $d) {
        $base32[] = $charset[$d];
    }

    // Return Bech32 string
    return $hrp . $separator . implode('', $base32);
    // human readable | separator | bech32 data (with checksum)

}

function bech32_address($scriptpubkey) {
    // Convert scriptpubkey to a Bech32 address.

    // Convert hex scriptpubkey to 8-bit integer values
    $values = unpack("C*", pack("H*", $scriptpubkey));

    // Get the version and witness program bytes
    // |00|14|751e76e8199196d454941c45d1b3a323f1433bd6
    $version = array_slice($values, 0, 1);
    $push = array_slice($values, 1, 1); // not needed
    $program = array_slice($values, 2); // must be in 5-bit groups...

    // Create address
    $programconv = convertbits($program, 8, 5); // 5-bit groups
    $data = array_merge($version, $programconv);
    $bech32 = bech32_encode("bc", $data);

    return $bech32;
    // |hrp|sep|data                             [chk ]|
    //  bc  1   qw508d6qejxtdg4y5r3zarvary0c5xw7kv8f3t4
}
