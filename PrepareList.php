<?php
if ($argc != 3)
	die("Usage: PrepareList [totalamount] [richlistasset]\n");

require __DIR__ . '/config.php';
require __DIR__ . '/vendor/autoload.php';
use deemru\WavesKit;
$wk = new WavesKit( $chainId );
$wk->setNodeAddress( $nodes[0], 1, array_slice( $nodes, 1 ) );
$wk->setSeed( $seed );
$wk->log( 's', 'WavesDrop @ ' . $wk->getAddress() );
$wk->setBestNode();
$wk->log( 'i', 'best node = ' . $wk->getNodeAddress() );
define( 'WK_CURL_TIMEOUT', 300 );

/** Configuration */
$distributedasset = '3tzzjdofocz8YhAMhoiR1xHkRJNGrpLTw28nNqgcDaP4'; // put WAVES for WAVES or assetID
$totalamount = $argv[1];
$richlistasset = $argv[2];
$height = $wk->height()-3;

/** Declarations **/
$distribution = array();
$distributedassetinfo = json_decode($wk->fetch( '/assets/details/' . $distributedasset ));
if ( is_null($distributedassetinfo))
{
    $wk->log('e', 'Error while loading asset info.');
    die();
}
$wk->log('i', "-------- Starting distribution of: [{$totalamount}] [{$distributedasset}], , Decimals: [{$distributedassetinfo->decimals}], Richlist asset: [{$richlistasset}] ---------");

$totalamount *= pow(10,$distributedassetinfo->decimals);

$wk->log('i', "Loading Richlist at height {$height}...");                    
$res = json_decode($wk->fetch("/assets/{$richlistasset}/distribution/{$height}/limit/999"));
$richlist = (array) $res->items;

while ($res->hasNext == true )
{        
    $res = json_decode($wk->fetch("/assets/{$richlistasset}/distribution/{$height}/limit/999?after={$res->lastItem}"));        
    $richlist_append = (array) $res->items;
    $richlist = array_merge( $richlist, $richlist_append );        
}    

if ($richlist === false )
{
    $log -> logError("Could not query richlist.");
    die();
}   

$wk->log('i', "Filtering Richlist from unwanted addresses...");
$wk->log('i', "Addresses in richlist before filter: " . count($richlist));
$total = 0;

// Calculate total amount in circulation in allowed addresses
foreach ( $richlist as $address => $amount )
{
    if ( in_array( $address, $excludedWallets ) )
    {
        unset($richlist[$address]);
        $wk->log('i', "Excluding address {$address} from richlist.");
    }
    else
    {        
        $total += $amount;
    }
}

$wk->log('i', "Addresses in richlist after filter: " . count($richlist));
$wk->log('i', "Preparing distribution quotas...");
asort($richlist);

foreach ((array) $richlist as $address => $amountowned)
{
    $distribution[$address]['share']= $amountowned/$total;
    $distribution[$address]['amounttopay']=intval($totalamount*$distribution[$address]['share']);
}		
foreach ($distribution as $address => $data )	
    if ($data['amounttopay'] > 0 )
        echo '"'.$address.'": '.$data['amounttopay'].",\n";
?>
