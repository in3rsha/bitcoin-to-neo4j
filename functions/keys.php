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

