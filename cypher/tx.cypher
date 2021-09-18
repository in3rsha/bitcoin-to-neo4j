// Create Transaction
MATCH (block :block {hash:$blockhash})
MERGE (tx:tx {txid:$txid})
MERGE (tx)-[:inc {i:$t}]->(block)
SET tx += $tx

// Inputs
WITH tx
FOREACH (input in $inputs |
	MERGE (in :output {index: input.index})
	MERGE (in)-[:in {vin: input.vin, scriptSig: input.scriptSig, sequence: input.sequence, witness: input.witness}]->(tx)
	REMOVE in:unspent
)

// Outputs
FOREACH (output in $outputs |
	MERGE (out :output {index: output.index})
	MERGE (tx)-[:out {vout: output.vout}]->(out)
  // This uses the foreach hack to only create an address node if the address value is not an empty string
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

// Fee
WITH tx
MATCH (i :output)-[:in]->(tx)
WITH tx, sum(i.value) - $outtotal as fee
SET tx.fee=fee

// Return
RETURN fee