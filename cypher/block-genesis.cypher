// Create Block
MERGE (block:block {hash:$blockhash})
MERGE (block)-[:coinbase]->(:output:coinbase)
SET
    block.size=$blocksize,
    block.txcount=$txcount,
    block.version=$version,
    block.prevblock=$prevblock,
    block.merkleroot=$merkleroot,
    block.time=$timestamp,
    block.bits=$bits,
    block.nonce=$nonce

// Set Height
SET block.height=0

// Return
RETURN block.height as height, block.prevblock as prevblock