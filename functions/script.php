<?php
/*
 * author:  Greg Walker
 * website: http://learnmeabitcoin.com
 * license: GPLv3
*/

include_once 'keys.php';

function decodeScript($script) {

if($script) {

	// store the original
	$hex = $script;

	$opcodes = [

		// Constants (number of bytes to push on to stack)
		// ---------
		'00' => 'OP_FALSE',
		// 01-4b = number of bytes to be pushed on to the stack
		'4c' => 'OP_PUSHDATA1', // next byte = number of bytes to push
		'4d' => 'OP_PUSHDATA2', // next 2 bytes = number of bytes to push
		'4e' => 'OP_PUSHDATA4', // next 4 bytes = number of bytes to push
		'4f' => 'OP_1NEGATE',   // number -1 pushed on to stack
		'51' => 'OP_1',         // number 1 pushed on to stack
		'52' => 'OP_2',
		'53' => 'OP_3',
		'54' => 'OP_4',
		'55' => 'OP_5',
		'56' => 'OP_6',
		'57' => 'OP_7',
		'58' => 'OP_8',
		'59' => 'OP_9',
		'6a' => 'OP_10',
		'6b' => 'OP_11',
		'6c' => 'OP_12',
		'6d' => 'OP_13',
		'6e' => 'OP_14',
		'6f' => 'OP_15',
		'60' => 'OP_16',
		// 52-60 = The number in the word name (OP_2-OP_16) is pushed onto the stack.

		// Flow Control
		// ------------
		'61' => 'OP_NOP', // does nothing
		'63' => 'OP_IF',
		'64' => 'OP_NOTIF',
		'67' => 'OP_ELSE',
		'68' => 'OP_ENDIF',
		'69' => 'OP_VERIFY',
		'6a' => 'OP_RETURN',

		// Stack
		// -----
		'6b' => 'OP_TOALTSTACK',
		'6c' => 'OP_FROMALTSTACK',
		'73' => 'OP_IFDUP',
		'74' => 'OP_DEPTH',
		'75' => 'OP_DROP', // Removes the top stack item.
		'76' => 'OP_DUP',
		'77' => 'OP_NIP',
		'78' => 'OP_OVER',
		'79' => 'OP_PICK',
		'7a' => 'OP_ROLL',
		'7b' => 'OP_ROT',
		'7c' => 'OP_SWAP',
		'7d' => 'OP_TUCK',
		'6d' => 'OP_2DROP',
		'6e' => 'OP_2DUP',
		'6f' => 'OP_3DUP',
		'70' => 'OP_2OVER',
		'71' => 'OP_2ROT',
		'72' => 'OP_2SWAP',

		// Splice
		// ------
		'7e' => 'OP_CAT',
		'7f' => 'OP_SUBSTR',
		'80' => 'OP_LEFT',
		'81' => 'OP_RIGHT',
		'82' => 'OP_SIZE',

		// Bitwise Logit
		// -------------
		'83' => 'OP_INVERT',
		'84' => 'OP_AND',
		'85' => 'OP_OR',
		'86' => 'OP_XOR',
		'87' => 'OP_EQUAL', // Returns 1 if the inputs are exactly equal, 0 otherwise.
		'88' => 'OP_EQUALVERIFY',

		// Arithmetic
		// ----------
		'8b' => 'OP_1ADD',
		'8c' => 'OP_1SUB',
		'8d' => 'OP_2MUL',
		'8e' => 'OP_2DIV',
		'8f' => 'OP_NEGATE',
		'90' => 'OP_ABS', // The input is made positive.
		'91' => 'OP_NOT',
		'92' => 'OP_0NOTEQUAL',
		'93' => 'OP_ADD', // a is added to b
		'94' => 'OP_SUB',
		'95' => 'OP_MUL',
		'96' => 'OP_DIV',
		'97' => 'OP_MOD',
		'98' => 'OP_LSHIFT',
		'99' => 'OP_RSHIFT',
		'9a' => 'OP_BOOLAND',
		'9b' => 'OP_BOOLOR',
		'9c' => 'OP_NUMEQUAL',
		'9d' => 'OP_NUMEQUALVERIFY',
		'9e' => 'OP_NUMNOTEQUAL',
		'9f' => 'OP_LESSTHAN',
		'a0' => 'OP_GREATERTHAN',
		'a1' => 'OP_LESSTHANOREQUAL',
		'a2' => 'OP_GREATERTHANOREQUAL',
		'a3' => 'OP_MIN',
		'a4' => 'OP_MAX',
		'a5' => 'OP_WITHIN',

		// Crypto
		// ------
		'a6' => 'OP_RIPEMD160',
		'a7' => 'OP_SHA1',
		'a8' => 'OP_SHA256',
		'a9' => 'OP_HASH160',
		'aa' => 'OP_HASH256',
		'ab' => 'OP_CODESEPARATOR',
		'ac' => 'OP_CHECKSIG',
		'ad' => 'OP_CHECKSIGVERIFY',
		'ae' => 'OP_CHECKMULTISIG',
		'af' => 'OP_CHECKMULTISIGVERIFY',

		// Locktime
		'b1' => 'OP_CHECKLOCKTIMEVERIFY',
		'b2' => 'OP_CHECKSEQUENCEVERIFY',

		// Pseudo-Words
		'fd' => 'OP_PUBKEYHASH',
		'fe' => 'OP_PUBKEY',
		'ff' => 'OP_INVALIDOPCODE',

		// Reserved Words
		'50' => 'OP_RESERVED',
		'62' => 'OP_VER',
		'65' => 'OP_VERIF',
		'66' => 'OP_VERNOTIF',
		'89' => 'OP_RESERVED1',
		'8a' => 'OP_RESERVED2',
		'b0' => 'OP_NOP1', // The word is ignored. Does not mark transaction as invalid.
		'b2' => 'OP_NOP3',
		'b3' => 'OP_NOP4',
		'b4' => 'OP_NOP5',
		'b5' => 'OP_NOP6',
		'b6' => 'OP_NOP7',
		'b7' => 'OP_NOP8',
		'b8' => 'OP_NOP9',
		'b9' => 'OP_NOP10',

	];

	// run through the string, getting the opcodes or specified number of bytes
	while (strlen($script) > 0) {

		// run through every byte (2 characters)
		$byte = substr($script, 0, 2);

		// store this byte in opcodes array
		$lockpieces[] = $byte;

		// now remove that byte from the string
		$script = substr($script, strpos($script, $byte) + strlen($byte));

		// ----------
		// Push Bytes (0x01 to 0x4e)
		// ----------
		if (ctype_xdigit($byte) && hexdec($byte) >= hexdec('00') && hexdec($byte) < hexdec('4e')) {

			// 00
			if ($byte == '00') { // Push empty bytes on to stack
				$pushbytes = '0';
			}
			// <= 4b
			if (hexdec($byte) >= 1 and hexdec($byte) <= hexdec('4b')) { // $byte indicates the number of bytes
				$pushbytes = substr($script, 0, hexdec($byte)*2);
			}

			// 4c, 4d, 4e
			$pushers = array(
				'4c' => 1,
				'4d' => 2,
				'4e' => 4,
			);
			if (array_key_exists($byte, $pushers)) {
				// get the number of bytes to push
				$bytestopush = substr($script, 0, $pushers[$byte]*2);

				// if no errors
				if ($bytestopush) {
					$script = substr($script, strpos($script, $bytestopush) + strlen($bytestopush)); // remove
					$pushbytes = substr($script, 0, hexdec($bytestopush)*2);
				}
				else {
					$script = NULL;
					$pushbytes = '[error]';
				}
			}

			// pop that opcode off the end of the array and replace it with the specified number of bytes
			array_pop($lockpieces);
			if ($pushbytes) {
				$lockpieces[] = $pushbytes;
			}

			// now remove those bytes from the string too
			if ($pushbytes) {
				$script = substr($script, strpos($script, $pushbytes) + strlen($pushbytes));
			}

		}

	}

	// convert the hex values to their corresponding opcodes
	$i=0;
	$lockops = [];
	foreach ($lockpieces as $piece) {

		// GET OPCODES

		if (strlen($piece) == 2) {
			if (array_key_exists($piece, $opcodes)) {
				$lockops[] = $opcodes[$piece];
			}
			else {
				$lockops[] = 'OP_???';
			}
		}
		else {
			$lockops[] = $piece;
		}

		$i++;
	}


	// -------------
	// GET ADDRESSES
	// -------------
	$addresses = [];

	if (count($lockops) > 0) {

		// 1. pubkey (P2PK): <pubkey> OP_CHECKSIG
		if (count($lockops) == 2) {
			if ($lockops[1] == 'OP_CHECKSIG') {
				if (ctype_xdigit($lockops[0]) && (strlen($lockops[0]) == 66 or strlen($lockops[0]) == 130)) {
					$addresses[] = pubkey_to_address($lockops[0]);
				}
			}
		}


		// 2. pubkeyhash (P2PKH): OP_DUP OP_HASH160 <hash160> OP_EQUALVERIFY OP_CHECKSIG
		if (count($lockops) == 5) {
			if ($lockops[0] == 'OP_DUP' && $lockops[1] == 'OP_HASH160' && $lockops[3] == 'OP_EQUALVERIFY' && $lockops[4] == 'OP_CHECKSIG') {

				// check the "hash160" is hex and 40 chars
				if (ctype_xdigit($lockops[2]) && strlen($lockops[2]) == 40) {
					$addresses[] = hash160_to_address($lockops[2], '00');
				}
			}
		}

		// 3. scripthash (P2SH): OP_HASH160 <hash160> OP_EQUAL
		if (count($lockops) == 3) {
			if ($lockops[0] == 'OP_HASH160' && $lockops[2] == 'OP_EQUAL') {

				// check the "hash160" is hex and 40 chars
				if (ctype_xdigit($lockops[1]) && strlen($lockops[1]) == 40) {
					$addresses[] = hash160_to_address($lockops[1], '05');
				}
			}
		}

		// 4. <n pubkeys> OP_n OP_CHECKMULTISIG
		if (array_slice($lockops, -1, 1)[0] == 'OP_CHECKMULTISIG') { // last opcode is op_checkmultisig
			if (substr(array_slice($lockops, -2, 1)[0], 0, 3) == 'OP_') { // second to last begins with OP_
				$op_n = preg_replace("/[^0-9]/", '', array_slice($lockops, -2, 1)[0]);
				$pubkeys = array_slice($lockops, -2-$op_n, $op_n); // get the expected number of pubkeys
				foreach ($pubkeys as $pubkey) {

					// check that it is a pubkey
					// example error:
					// OP_2 OP_FALSE 021d69e2b68c3960903b702af7829fadcd80bd89b158150c85c4a75b2c8cb9c394 OP_2 OP_CHECKMULTISIG

					if (ctype_xdigit($pubkey) && (strlen($pubkey) == 66 or strlen($pubkey) == 130)) {
						$addresses[] = pubkey_to_address($pubkey);
					}
				}
			}
		}

	} // if count($lockops) > 0

	// 5. P2WPKH and P2WSH - 0014{20-bytes} or 0020{32-bytes}
	// Use '00' version to identify a native segwit transaction
	if (substr($hex, 0, 2) == '00') { // && (substr($hex, 2, 2) == '14' || substr($hex, 2, 2) == '20')) {
		// |version|push|witnessprogram|
		//  00      14   751e76e8199196d454941c45d1b3a323f1433bd6
		//  00      20   88e2e40cd889901733cb2f922be01199d334f3232a34cffee6143482d8eb6c19

		// [ ] Can remove. Just doing an extra check to make sure we haven't already got an address.
		if (count($addresses) > 0) {
			throw new Exception("Already got an address for what looks like a witness scriptpubkey: ".print_r($addresses));
		}

		// Determine type
		if (substr($hex, 2, 2) == '14') { // 20-byte witess program (hash160 of a public key)
			$type = 'P2WPKH';
			$addresses[] = bech32_address($hex);
		}
		elseif (substr($hex, 2, 2) == '20') { // 32-byte witess program (hash256 of a script)
			$type = 'P2WSH';
			$addresses[] = bech32_address($hex);
		}
		else {
			throw new Exception("Unknown witness program size: $hex");
		}
	}

	// -------
	// RESULT!
	// -------
	$result = array(
		'hex' => $hex,
		'opcodes' => implode(' ', $lockops),
		'addresses' => implode(', ', $addresses),
	);
}

// if script empty
else {
	$result = array(
		'hex' => '',
		'opcodes' => '',
		'addresses' => '',
	);

}

return $result;

}

// test
// print_r(decodeScript('0201'));

?>
