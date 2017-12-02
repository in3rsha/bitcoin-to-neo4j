# Benchmarks

I did a few rough tests for common/useful bitcoin queries, each returning various numbers of nodes. I repeated each query 3 times.

Times are in ms (milliseconds).

## Blocks

Getting a block and all the transactions connected to it.

|Rows    |Time  |Time|Time|
|--------|------|----|----|
|2764    |1335  |282 |49  |
|1745    |1261  |37  |35  |
|687     |212   |19  |18  |
|550     |187   |18  |15  |

### Query

```
PROFILE MATCH (b:block)<-[:inc]-(t:tx) WHERE b.hash='000000000000000000ebaa7b3a804d9ba856b3bd61659f8f363bd42dc9c4a94c' RETURN b, t
```

## Transactions

Getting a transaction and all the inputs/outputs connected to it.

|Rows    |Time|Time|Time|
|--------|----|----|----|
|5026    |897 |282 |269 |
|94      |85  |17  |17  |
|4       |33  |8   |6   |

### Query

```
PROFILE MATCH (inputs)-[:in]->(tx:tx)-[:out]->(outputs) WHERE tx.txid='c21e2592abcd3eea532f51f3e18bbc9d9ad23b44f643d9aea580bf0ce0d4d0bc' OPTIONAL MATCH (inputs)-[:locked]->(inputsaddresses) OPTIONAL MATCH (outputs)-[:locked]->(outputsaddresses) OPTIONAL MATCH (tx)-[:inc]->(block) RETURN inputs, tx, outputs, block, inputsaddresses, outputsaddresses
```

## Addresses

Getting an address and all the outputs connected to it.

|Rows    |Time  |Time|Time|
|--------|------|----|----|
|3195800 |-     |-   |-   |
|27071   |12904 |344 |357 |
|830     |560   |13  |15  |
|188     |191   |15  |7   |

Note: The top query took over 60s to run each time, so I didn't get a final time for it.

### Query

```
MATCH (address :address)<-[:locked]-(output :output) WHERE address.address='$address' RETURN address, output
```

## Conclusion

Neo4j is fast enough for practical use.

The only query that really struggles is the addresses that have 300,000+ outputs attached to them. But these are rare. However for those the time becomes impractical.