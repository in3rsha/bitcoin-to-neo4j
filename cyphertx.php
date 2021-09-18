<?php
/*
 * author:  Greg Walker
 * website: http://learnmeabitcoin.com
 * license: GPLv3
*/

function cypherTx($neo, $transaction, $t, $blockhash, $cypher) {

	// =================
	// Cypher Parameters
	// =================
	$decoded = decoderawtransaction($transaction); // decode the raw transaction string

	$size = strlen($transaction)/2; $sizedisplay = str_pad('['. $size .' bytes]', 15, ' ');
	$relationshipsdisplay = str_pad('('. count($decoded['vin']) .':'. count($decoded['vout']) .')', 7, ' ');
	$txid = $decoded['txid'];
	$t_display = str_pad($t.'.', 5, ' ');

	echo "   $t_display $txid $sizedisplay $relationshipsdisplay ";

	// skip transaction if it already exists in database
	$check = $neo->run('MATCH (tx :tx {txid:$txid}) RETURN tx', ['txid' => $txid]);
	$exists = !$check->isEmpty(); // is there a record for this txid?

	if ($exists) {

		// if this is a coinbase transaction, always merge it to the block (because two coinbase txs can have the same txid)
		if ($decoded['vin'][0]['txid'] == '0000000000000000000000000000000000000000000000000000000000000000') {
			$vin_coinbase = $decoded['vin'][0]['scriptSig']['hex']; // miners can put what they like in it
			$vin_sequence = $decoded['vin'][0]['sequence'];

			$neo->run('
			MATCH (tx :tx {txid:$txid}), (block :block {hash:$blockhash})-[:coinbase]->(coinbase :output:coinbase)
			WITH tx, block, coinbase
			MERGE (tx)-[:inc {i:$t}]->(block)
			MERGE (coinbase)-[in :in]->(tx)
			ON CREATE SET
				in.vin=0,
				in.scriptSig=$vin_coinbase,
				in.sequence=$vin_sequence
			',
			[
				'txid'         => $txid,
				'blockhash'    => $blockhash,
				't'            => $t,
				'vin_coinbase' => $vin_coinbase,
				'vin_sequence' => $vin_sequence,
			]
			);

			echo 'exists->block (+coinbase)';
		}

		else {
			// just connect this transaction to the block (in case we've got a transaction from an orphan block - don't want to forget to connect it to the block)
			$neo->run('
			MATCH (tx :tx {txid:$txid}), (block :block {hash:$blockhash})
			WITH tx, block
			MERGE (tx)-[:inc {i:$t}]->(block)
			',
			[
				'txid'         => $txid,
				'blockhash'    => $blockhash,
				't'            => $t,
			]
			);

			echo 'exists->block';
		}

	}
	// if this transaction doesn't exist in neo4j...
	else {

		// Build Parameter Array
		$parameters = array();
		$parameters['txid']      = $txid;
		$parameters['blockhash'] = $blockhash;
		$parameters['t']         = $t;

		// ----------
		// 1. TX node
		// ----------
		$parameters['tx']['version']  = $decoded['version'];
		$parameters['tx']['locktime'] = $decoded['locktime'];
		$parameters['tx']['size']     = $decoded['size'];
		if ($decoded['segwit']) {
			$parameters['tx']['segwit'] = $decoded['segwit']; // [marker][flag]
		}

		// ---------
		// 2. Inputs
		// ---------
		$i=0;
		$inputs = array();
		$coinbase = false; // will use this later to choose correct cypher query (coinbase transaction is slightly different to standard transaction)

		foreach ($decoded['vin'] as $vin) {

			// Store new witness data if this is a new Segregated Witness transaction
			if (array_key_exists('witness', $vin)) {
				$witness = $vin['witness']['hex'];
			}
			else {
				$witness = '';
			}

			$vin_txid                = $vin['txid']; // (no need to swapEndian - txid is already in searchable order)
			$vin_vout                = $vin['vout'];
			$vin_scriptSig           = $vin['scriptSig']['hex'];
			$vin_sequence            = $vin['sequence'];
				
			$inputs[$i]['vin']       = $i;
			$inputs[$i]['index']     = "$vin_txid:$vin_vout";
			$inputs[$i]['scriptSig'] = $vin_scriptSig;
			$inputs[$i]['sequence']  = $vin_sequence;
			$inputs[$i]['witness']   = $witness;

			$i++;
		}

		// If coinbase transaction
		if ($decoded['vin'][0]['txid'] == '0000000000000000000000000000000000000000000000000000000000000000') { // the input txid is all zeros for coinbase transactions
			$coinbase = true;
			$parameters['coinbase_script'] = $inputs[0]['scriptSig']; // miners can put what they like in this
			$parameters['coinbase_sequence'] = $inputs[0]['sequence'];
		}

		$parameters['inputs'] = $inputs;

		// ----------
		// 3. Outputs
		// ----------
		$i=0;
		$outputs = [];
		$outtotal = 0; // keep track of output values (for calculating fee later)

		foreach ($decoded['vout'] as $vout) {

			$value = $vout['value'];
			$scriptPubKey = $vout['scriptPubKey']['hex'];
			$addresses = $vout['scriptPubKey']['addresses'];

			$outputs[$i]['vout'] = $i;
			$outputs[$i]['index'] = "$txid:$i";
			$outputs[$i]['value'] = $value;
			$outputs[$i]['scriptPubKey'] = $scriptPubKey;
			$outputs[$i]['addresses'] = $addresses;
			
			$outtotal += $value;
			$i++;

		}

		$parameters['outputs'] = $outputs;
		$parameters['outtotal'] = $outtotal;


		// ============
		// Cypher Query
		// ============

		// Select Cypher Query
		if ($coinbase) {
			$query = $cypher['tx-coinbase'];
		}
		else {
			$query = $cypher['tx'];
		}

		// Run the full query to add the tx to the neo4j db (returns input total)
		while (true) {
			// Catch any errors caught by locks on nodes when writing to Neo4j
			try {
				$result = $neo->run($query, $parameters);
				break;
			}
			// Echo the error, then wait a second before trying again.
			catch (Exception $e) {
				echo $e;
				exit;
				sleep(1);
			}
		}

		// Get the fee (just to check) (Note: The fee will be negative if the inputs for this transaction are not in Neo4j yet, which is cool.)
		$fee = $result->first()->get('fee');
		echo "fee: $fee";

		return $fee;

	}

}
