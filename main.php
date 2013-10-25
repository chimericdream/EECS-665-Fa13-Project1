<?php
$cli = fopen("php://stdin","r");
if (empty($cli)) {
    //@TODO: display error
    exit;
}

echo 'Enter the name of the input file (press \'Enter\' to use \'sample-in.txt\'): ';
$inFileName = trim(fgets($cli));
if (empty($inFileName)) {
    $inFileName = 'sample-in.txt';
}
$inFileName = dirname(__FILE__) . '/' . $inFileName;

echo 'Enter the name of the output file (press \'Enter\' to use \'sample-out.txt\'): ';
$outFileName = trim(fgets($cli));
if (empty($outFileName)) {
    $outFileName = 'sample-out.txt';
}
$outFileName = dirname(__FILE__) . '/' . $outFileName;

// Build the base grammar
$inFile  = fopen($inFileName, 'r');
$rawData = fread($inFile, filesize($inFileName));
fclose($inFile);

$grammar     = array();
$productions = explode("\n", $rawData);
foreach ($productions as $p) {
    $prod = explode('->', $p);
    $lhs  = $prod[0];
    $rhs  = (isset($prod[1])) ? $prod[1] : null;
    $prod = array(
        'lhs' => $lhs,
        'rhs' => $rhs,
    );
    $grammar[] = $prod;
}

// Build the augmented grammar
$augGrammar = array();
foreach ($grammar as $i => $p) {
    if ($i == 0) {
        $lhs = "'";
        $rhs = $p['lhs'];
    } else {
        $lhs = $p['lhs'];
        $rhs = $p['rhs'];
    }
    $prod = array(
        'lhs' => $lhs,
        'rhs' => $rhs,
    );
    $augGrammar[] = $prod;
}

echo displayGrammar($grammar);
echo displayGrammar($augGrammar);

//$outFile = fopen($outFileName, 'r');

function displayGrammar($grammar) {
    $out = '';
    foreach ($grammar as $g) {
        $out .= $g['lhs'] . '->' . $g['rhs'] . "\n";
    }
    return $out;
}

exit;