<?php
/*
 * author:  Greg Walker
 * website: http://learnmeabitcoin.com
 * license: GPLv3
*/

function cypherTx($neo, $transaction, $t, $blockhash) {

	// ============
	// CYPHER BUILD
	// ============
	$decoded = decodeRawTransaction($transaction); // decode the raw transaction string

	$size = strlen($transaction)/2; $sizedisplay = str_pad('['. $size .' bytes]', 15, ' ');
	$relationshipsdisplay = str_pad('('. count($decoded['vin']) .':'. count($decoded['vout']) .')', 7, ' ');
	$txid = $decoded['txid'];
	$t_display = str_pad($t.'.', 5, ' ');

	echo "   $t_display $txid $sizedisplay $relationshipsdisplay ";
	
	// skip transaction if it already exists in database
	$check = $neo->run("MATCH (tx :tx {txid:'$txid'}) RETURN tx");
    $exists = $check->size() > 0; // is there a record for this txid?
    
	if ($exists) {
        $record = $check->getRecord();
        
		// if this is a coinbase transaction, always merge it to the block (because two coinbase txs can have the same txid)
		if ($decoded['vin'][0]['txid'] == '0000000000000000000000000000000000000000000000000000000000000000') {
			$vin_coinbase = $decoded['vin'][0]['scriptSig']['hex']; // miners can put what they like in it
			$vin_sequence = $decoded['vin'][0]['sequence'];

			$neo->run("
			MATCH (tx :tx {txid:'$txid'}), (block :block {hash:'$blockhash'})-[:coinbase]->(coinbase :output:coinbase)
			WITH tx, block, coinbase
			MERGE (tx)-[:inc {i:$t}]->(block)
			MERGE (coinbase)-[in :in]->(tx)
			ON CREATE SET
				in.vin=0,
				in.scriptSig='$vin_coinbase',
				in.sequence='$vin_sequence'
			");
			echo 'exists->block (+coinbase)';
		}

		else {
			// just connect this transaction to the block (in case we've got a transaction from an orphan block - don't want to forget to connect it to the block)
			$neo->run("
			MATCH (tx :tx {txid:'$txid'}), (block :block {hash:'$blockhash'})
			WITH tx, block
			MERGE (tx)-[:inc {i:$t}]->(block)
			");
			echo 'exists->block';
		}

	}
	// if this transaction doesn't exist in neo4j...
	else {
		
		$cypher = '';
		// ----------
		// 1. TX node
		// ----------
		$version = $decoded['version'];
		$locktime = $decoded['locktime'];
		
		// Set segwit property on tx with the value of [marker][flag] if it's a segwit tx
		if ($decoded['segwit']) {
			$segwit = $decoded['segwit'];
			$setsegwit = "SET tx.segwit='$segwit'";
		}
		else {
			$setsegwit = '';
		}
		
		$cypher .= "
		MATCH (block :block {hash:'$blockhash'})-[:coinbase]->(coinbase :output:coinbase)
		MERGE (tx:tx {txid:'$txid', version:$version, locktime:$locktime, size:$size}) 
		WITH tx, block, coinbase
		$setsegwit 
		";
		
		
		// ---------
		// 2. Inputs
		// ---------
		$i=0;
		$inputs = array(); $inputstack = '';
		$coinbase = false;
		$inputcount = count($decoded['vin']);
		
		foreach ($decoded['vin'] as $vin) {
			
			// Store new witness data if this is a new Segregated Witness transaction
			if (array_key_exists('witness', $vin)) {
				$witness = $vin['witness']['hex'];
				$witnessstack = ", witness:'$witness'";
				$witnessiterate = ", witness: input.witness";
			}
			else {
				$witness = '';
				$witnessstack = '';
				$witnessiterate = '';
			}
		
			// If coinbase transaction
			if ($vin['txid'] == '0000000000000000000000000000000000000000000000000000000000000000') { // the input txid is all zeros for coinbase transactions
				$coinbase = true;
				$vin_coinbase = $vin['scriptSig']['hex']; // miners can put what they like in it
				$vin_sequence = $vin['sequence'];
				
				$cypher .= "MERGE (coinbase)-[:in {vin:$i, scriptSig:'$vin_coinbase', sequence:'$vin_sequence'}]->(tx) ";
				break;
			}
			
			// If not coinbase transaction
			else {
				$vin_txid = $vin['txid'];
				$vin_vout = $vin['vout'];
				$vin_scriptSig = $vin['scriptSig']['hex'];
				$vin_sequence = $vin['sequence'];
				
				// Prepare a JSON array so that each input can be added using Neo4j's FOREACH
				$inputs[] = "{vin:$i, index:'$vin_txid:$vin_vout', scriptSig:'$vin_scriptSig', sequence:'$vin_sequence', witness:'$witness'}";
			
			}
			
			$i++;
		}
		
		
		$inputs = implode(", ", $inputs); // create json of each input array
		
		// iterate over the json array of inputs
		$cypher .= "
			FOREACH (input in [$inputs] |
				MERGE (in :output {index: input.index}) 
				MERGE (in)-[:in {vin: input.vin, scriptSig: input.scriptSig, sequence: input.sequence$witnessiterate}]->(tx)
			)
			";

		
		// ----------
		// 3. Outputs
		// ----------
		$i=0;
		$outputs = []; $outputstack = ''; $addressiterate = [];
		$outtotal = 0; // keep track of output values (for calculating fee later)

		foreach ($decoded['vout'] as $vout) {
			$value = $vout['value']; $outtotal += $value;
			$scriptPubKey = $vout['scriptPubKey']['hex'];
			$addresses = $vout['scriptPubKey']['addresses'];
			
			// Prepare a JSON array so that each output can be added using Neo4j's FOREACH
			$outputs[] = "{vout:$i, index:'$txid:$i', value:$value, scriptPubKey:'$scriptPubKey', addresses:'$addresses'}";
			
			$i++;
		}
		
		$outputs = implode(", ", $outputs); // create json of each input array
		
		// 1. MAIN: Iterate over the json array of outputs
		//   This uses the foreach hack to only create an address node if the address value is not an empty string
		//   If output is placeholder, it didn't have a value. Increase fee for the tx it's an input for.
		$cypher .= "
			FOREACH (output in [$outputs] |
				MERGE (out :output {index: output.index})
				MERGE (tx)-[:out {vout: output.vout}]->(out)
				FOREACH(ignoreMe IN CASE WHEN output.addresses <> '' THEN [1] ELSE [] END |
					MERGE (address :address {address: output.addresses})
					MERGE (out)-[:locked]->(address)
				)

				MERGE (out)-[:in]->(existing)
				ON CREATE SET
					out.value= output.value,
					out.scriptPubKey= output.scriptPubKey
				ON MATCH SET
					out.value= output.value,
					out.scriptPubKey= output.scriptPubKey,
					existing.fee = existing.fee + output.value
				
			)
			";

		
		// --------
		// 4. Block
		// --------
		$cypher .= "
		MERGE (tx)-[:inc {i:$t}]->(block) 
		";
		
		// ----------
		// 5. Set Fee (and return fee info)
		// ----------
		$cypher .= "
		WITH tx
		MATCH (i :output)-[:in]->(tx)
		WITH tx, sum(i.value) - $outtotal as fee
		SET tx.fee=fee
		RETURN fee
		";
		
		// ==========
		// CYPHER RUN
		// ==========

		// Run the full query to add the tx to the neo4j db (returns input total)
		// Error catching
		while (true) {
			// Catch any errors caught by locks on nodes when writing to Neo4j
			try {
				$result = $neo->run($cypher);
				break;
			}
			// Echo the error, then wait a second before trying again.
			catch (Exception $e) {
				echo '$neo->run($cypher) exception'.PHP_EOL;	
				sleep(1);
			}
		}

		// Get the fee (just to check) (Note: The fee will be negative if the inputs for this transaction are not in Neo4j yet, which is cool.)
		$record = $result->getRecord();
		$fee = $record->get('fee');
		echo "fee: $fee";
			
		return $fee;
	
	}

}
