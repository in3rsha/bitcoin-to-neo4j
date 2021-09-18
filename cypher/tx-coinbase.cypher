// Create Transaction
MATCH (block :block {hash:$blockhash})-[:coinbase]->(coinbase :coinbase)
MERGE (tx:tx {txid:$txid})
MERGE (tx)-[:inc {i:$t}]->(block)
SET tx += $tx

// Coinbase Input
MERGE (coinbase)-[coinbasein:in {vin:0, scriptSig:$coinbase_script, sequence:$coinbase_sequence}]->(tx)
FOREACH (input in $inputs |
  SET coinbasein.witness = input.witness
)

// Outputs
WITH tx
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