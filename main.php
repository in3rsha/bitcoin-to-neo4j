<?php
/*
 * title:   bitcoin-to-neo4j
 * desc:    Import Bitcoin's blk.dat files (the blockchain) in to a Neo4j graph database.
 * author:  Greg Walker
 * website: http://learnmeabitcoin.com
 * license: GPLv3
*/

// Config
require_once 'config.php';

// Redis
$redis = new Redis();
$redis->connect(REDIS_IP, REDIS_PORT);

// Composer
require_once 'vendor/autoload.php';

// Neo4j
use Laudis\Neo4j\ClientBuilder; // (neo4j-php/neo4j-php-client)

$neo = ClientBuilder::create()
    ->withDriver('bolt', 'bolt://'.NEO4J_USER.':'.NEO4J_PASS.'@'.NEO4J_IP.':'.NEO4J_PORT) // creates a bolt driver
    ->withDefaultDriver('bolt')
    ->build();

// Check Neo4j is running
try {
    $neo->run("SHOW DATABASES");
}
catch (\Throwable $th) {
    echo "Doesn't look like Neo4j is running or available yet. If you've just started Neo4j, give it a few moments.".PHP_EOL;
    exit;
}

// Create Neo4j constraints (for unique indexes, not regular indexes (should be faster))
$neo->run("CREATE CONSTRAINT IF NOT EXISTS ON (b:block) ASSERT b.hash IS UNIQUE");
$neo->run("CREATE CONSTRAINT IF NOT EXISTS ON (t:tx) ASSERT t.txid IS UNIQUE");
$neo->run("CREATE CONSTRAINT IF NOT EXISTS ON (o:output) ASSERT o.index IS UNIQUE");
$neo->run("CREATE INDEX IF NOT EXISTS FOR (b:block) ON (b.height)");
$neo->run("CREATE INDEX IF NOT EXISTS FOR (a:address) ON (a.address)"); // for getting outputs locked to an address

// Cypher Queries
$cypher['tx']            = file_get_contents("cypher/tx.cypher");
$cypher['tx-coinbase']   = file_get_contents("cypher/tx-coinbase.cypher");
$cypher['block']         = file_get_contents("cypher/block.cypher");
$cypher['block-genesis'] = file_get_contents("cypher/block-genesis.cypher");

// Functions
include('functions/tx.php');        // decode transaction
include('functions/block.php');     // calculate block reward
include('functions/readtx.php');    // read single transaction size quickly
include('cyphertx.php');            // insert tx in to neo4j

// Handy Functions
function blk00000($i) { return 'blk'.str_pad($i, 5, '0', STR_PAD_LEFT).'.dat'; }

// ---------
// PRE-CHECK
// ---------
if (!file_exists(BLOCKS)) {
    exit("Couldn't find ".BLOCKS.PHP_EOL."Make sure you have entered the correct path to Bitcoin's blk*.dat files.\n");
}

// -------------------
// READ THE BLOCKCHAIN
//--------------------

$start = $redis->hget('bitcoin-to-neo4j', 'blk.dat') ?: 0; // which blk.dat file to start with
$startfp = $redis->hget('bitcoin-to-neo4j', 'fp') ?: 0; // Zero if not set

while(true) { // Keep trying to read files forever

    $file = blk00000($start); // format file number (e.g. blk00420.dat instead of blk420.dat)
    $path = BLOCKS."/$file";

    $fh = fopen($path, 'rb'); echo "Reading $path...\n\n"; sleep(1);

    $dat_start = microtime(true); // track how long it takes to import a blk.dat file
    $b = 1; // for counting the blocks in each file

    // keep track of which blk.dat file we are on (store it in Redis)
    $redis->hset('bitcoin-to-neo4j', 'blk.dat', $start);

    while(true) { // Read through a blk*.dat file

        // pick up from where we left off
        if (isset($startfp)) { fseek($fh, $startfp); unset($startfp); }

        // keep track of where the file pointer is (before each block).
        $fp = ftell($fh);

        // store file pointer in redis (only after a block has been fully ran through)
        $redis->hset('bitcoin-to-neo4j', 'fp', $fp);

        // =====
        // BLOCK
        // =====
        $b_start = microtime(true); // track how long it takes to import a block

        // 1. Read one byte at a time until we hit a block header (magic bytes)
        $buffer = '';
        $bytesread = 0;
        while (true) {

            // Read 1 byte at a time
            $buffer .= bin2hex(fread($fh, 1));
            $bytesread++;
            $buffer = substr($buffer, -8); // magic bytes is 4 bytes

            // Magic Bytes
            $magicbytes = TESTNET ? '0b110907' : 'f9beb4d9';

            if (strlen($buffer) == 8) {
                // hit a block header
                if ($buffer == $magicbytes) {
                    $blocksize = fread($fh, 4);
                    $blocksize = hexdec(swapEndian(bin2hex($blocksize)));

                    // Read the full block of data
                    $block = bin2hex(fread($fh, $blocksize));

                    // if last 500 characters are all zeros, then we probably haven't got the full block data, so wait for it
                    if (hexdec(substr($block, -500)) == 0) {
                        echo "Doesn't look like the blk.dat file has all the bytes of data for the block. Wait a second for it to arrive...\n";
                        file_put_contents('log/blockwait.txt', "$block\n\n");

                        // wait a second
                        sleep(1);

                        // go back to end of last block
                        fseek($fh, $fp);
                        $fp = ftell($fh);
                        $bytesread = 0;     // reset bytes read

                        // go back to start of loop and try reading block again
                        continue;
                    }
                    else {
                        // reset buffer
                        $buffer = '';

                        // break out and start reading transactions
                        break;
                    }

                }
                // if we do not hit a block header
                else {
                    // if we have read forward another 1000 bytes and not found another magic bytes
                    if ($bytesread > 1000) {

                        // go back to end of last block
                        fseek($fh, $fp);
                        $fp = ftell($fh);

                        // reset bytes read
                        $bytesread = 0;
                        sleep(1);

                        echo "Doesn't look like there's another block yet. Re-reading... ($fp)\n";
                    }
                }
            }


            // hit end of file
            if (feof($fh)) {

                // if there is a next file, go to it
                $nextfile = blk00000($start+1);
                if (file_exists(BLOCKS."/$nextfile")) {
                    echo "\nThere is a file $nextfile.\n"; sleep(1);
                    $start = $start+1;  // Set the file number to the next one
                    break 2;            // ... Restart main loop (opens next file)
                }

            }
        }


        // Block Header (human format)
        $version =      hexdec(swapEndian(substr($block, 0, 8)));
        $prevblock =    swapEndian(substr($block, 8, 64)); // searchable byte order
        $merkleroot =   swapEndian(substr($block, 72, 64));
        $timestamp =    hexdec(swapEndian(substr($block, 136, 8)));
        $bits =         swapEndian(substr($block, 144, 8));
        $nonce =        hexdec(swapEndian(substr($block, 152, 8)));


        // i. Work out this block's hash
        $blockheader = substr($block, 0, 160); // header is 80 bytes total
        $blockhash = swapEndian(hash('sha256', hash('sha256', hex2bin($blockheader), true)));
        $hash = $blockhash; // this is for possibly setting the tip height in redis

        // a. Number of upcoming transactions (varint)
        $varint = substr($block, 160); list($full, $value, $len) = varInt($varint);
        $txcount = $value;

        $transactions = substr($block, 160+$len); // +$len: start from the end of the length of the tx count varint

        // 3. Save Block
        $b_start = microtime(true);
        $blocksizekb = number_format($blocksize/1000, 2);
        echo " $b: $blockhash [$blocksizekb kb] (fp:$fp) ";

        // Select Cypher Query
        if ($prevblock == '0000000000000000000000000000000000000000000000000000000000000000') { // Genesis Block
            $query = $cypher['block-genesis'];
        }
        else {
            $query = $cypher['block'];
        }

        // Save this block to Neo4j
        $run = $neo->run($query,
        [
            'blockhash'  => $blockhash,
            'blocksize'  => $blocksize,
            'txcount'    => $txcount,
            'version'    => $version,
            'prevblock'  => $prevblock,
            'merkleroot' => $merkleroot,
            'timestamp'  => $timestamp,
            'bits'       => $bits,
            'nonce'      => $nonce
        ]
        );

        // ------------------
        // HEIGHT BASED STUFF
        // ------------------

        // Get the height
        foreach ($run as $record) {
            $height = $record->get('height');
            echo $height;
            $prevblock = $record->get('prevblock');
        }
        // If we have a height for this block, set value for coinbase input.
        if ($height !== NULL) {
            $blockreward = calculateBlockReward($height);

            $neo->run('
            MATCH (block :block {hash:$blockhash})-[:coinbase]->(coinbase :output:coinbase)
            SET coinbase.value=$blockreward
            ',
            [
                'blockhash' => $blockhash,
                'blockreward' => $blockreward,
            ]
            );

        }

        // If we don't have a height, save this block hash for future updating
        else {
            echo "\n  This block's prevblock is not in database. Saving it.\n";

            // save preblock->blockhash to redis
            $redis->hset("bitcoin-to-neo4j:orphans", $prevblock, $blockhash);

            // print out how many orphan blocks we have saved in Redis
            echo '  - blocks needed = '.$redis->hlen('bitcoin-to-neo4j:orphans')."\n";
        }

        // ----------
        // ORPHAN RUN
        // ----------

        // If we've got a prevblock for a block with no height (and has a height for populating blocks above it)
        if ($redis->hExists('bitcoin-to-neo4j:orphans', $blockhash) && $height !== NULL) {
            echo "\n  Parent block! Updating block height, coinbase values and coinbase tx fees for blocks above it...\n";

            // Get all the blocks that are chained to this one (above it)
            $chainabove = $neo->run('
            MATCH (dependency :block {hash:$blockhash})<-[:chain*]-(blocks :block)
            RETURN collect(blocks.hash) as chainabove
            ',
            [
                'blockhash' => $blockhash
            ]
            );

            // Get the array of blocks to be populated
            foreach ($chainabove as $record) {
                $chainabove = $record->get('chainabove');
            };

            $heights = array();
            // Set height for each of these blocks
            foreach ($chainabove as $orphan) {
                echo "    $orphan ";

                $orphanrun = $neo->run('
                MATCH (block :block {hash:$orphan})-[:chain]->(prevblock :block)
                SET block.height=prevblock.height+1
                RETURN block
                ',
                [
                    'orphan' => $orphan,
                ]
                );

                foreach ($orphanrun as $record) {
                    $orphanblock = $record->get('block');
                }
                $orphanheight = $orphanblock->properties()->get('height');
                $orphanprevblock = $orphanblock->properties()->get('prevblock');

                echo "$orphanheight\n";

                // Set the coinbase values based on the height (can also set the fee now we know the block reward)
                $blockreward = calculateBlockReward($orphanheight);

                // Update coinbase and fee (if the coinbase input value has not been set)
                $coinbaserun = $neo->run('
                MATCH (block :block {hash:$orphan})-[:coinbase]->(coinbase :output:coinbase)-[:in]->(tx :tx)
                WHERE NOT exists(coinbase.value)
                SET coinbase.value=$blockreward
                SET tx.fee= tx.fee + $blockreward
                ',
                [
                    'orphan'      => $orphan,
                    'blockreward' => $blockreward,
                ]
                );

                // Keep log of heights that have been added
                $heights[$orphan] = $orphanheight;

                // Remove block from redis orphans
                $redis->hdel("bitcoin-to-neo4j:orphans", $orphan);

            }

            // Remove this current block from redis orphan too
            $redis->hdel("bitcoin-to-neo4j:orphans", $blockhash);

            // Find the max height and hash we've managed to get
            asort($heights);
            $max = array_slice($heights, -1, 1);
            $hash = key($max);
            $height = $max[$hash];

        }

        // Store longest known blockchain height in Redis
        if ($height > $redis->hget("bitcoin-to-neo4j:tip", 'height')) {
            $redis->hset("bitcoin-to-neo4j:tip", 'height', $height);
            $redis->hset("bitcoin-to-neo4j:tip", 'hash', $hash);
        }


        // ============
        // TRANSACTIONS
        // ============
        echo "\n  $txcount\n";

        // Read Individual Transactions

        // 1. Read each transaction in this string of transactions
        $p = 0; // pointer
        $t = 1; // tx count
        while (isset($transactions[$p])) { // continue until end of string of transactions
            // store the current pointer in case we need to go back to it
            $pbefore = $p;

            // read one tx (give a start pointer and it returns end pointer)
            list($transaction, $p) = readtx($transactions, $p);

            // get the txid ready so that it can be used in error handler
            $txid = swapEndian(hash('sha256', hash('sha256', hex2bin($transaction), true)));

            // ----------------
            // CYPHER TX INSERT
            // ----------------
            $tx_start =  microtime(true);
            cypherTx($neo, $transaction, $t, $blockhash, $cypher); // IMPORT THE TRANSACTION IN TO NEO4J! (using functions/cyphertx.php)
            $tx_time = microtime(true)-$tx_start;

            // Display the time it took to insert transaction
            echo '  '.number_format($tx_time, 5)."\n";

            // next tx...
            $t++;

        } // transaction block string loop


        $b_end = microtime(true);
        echo '  '.number_format(($b_end-$b_start)/60, 5)." mins \n\n";

        // next block...
        $b++; // update block count for this blk.dat file

    } // blk*.dat loop

    // log that the file has been done
    $dat_end = microtime(true); $dat_time = number_format(($dat_end-$dat_start)/60, 2);
    $b--;
    $redis->hset('bitcoin-to-neo4j:log', $file, "[$b] $dat_time mins");


} // Infinite Loop
