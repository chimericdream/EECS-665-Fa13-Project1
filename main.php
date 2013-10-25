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

$baseGrammar = buildBaseGrammar($inFileName);
$augGrammar  = buildAugmentedGrammar($baseGrammar);
$stateList   = buildStateList($augGrammar);

$mainOutput  = "Augmented Grammar\n-----------------\n";
$mainOutput .= displayGrammar($augGrammar);
$mainOutput .= "\nSets of LR(0) Items\n-------------------\n";
$mainOutput  .= displayStateList($stateList);

writeOutput($mainOutput, $outFileName);

function buildStateList($grammar) {
    $states   = array();
    $states[] = buildStateZero($grammar);
    for ($i = 0; $i < count($states); $i++) {
        $curr = $states[$i];
        $s = 0;
        foreach ($curr as &$prod) {
            $prod['goto'] = getGotoState($prod, $curr, $states, $grammar);
            if (!is_null($prod['goto'])) {
                $prod['goto'] = formatGoto($prod);
            }
            $s++;
        }
    }
    return $states;
}

function formatGoto($production) {
    $prod = explode('@', $production['rhs']);
    $char = $prod[1][0];
    return array(
        'char'  => $char,
        'state' => $production['goto'],
    );
}

function getGotoState($rule, $from, &$stateList, $grammar) {
    $ruleParts = explode('@', $rule['rhs']);
    if (empty($ruleParts[1])) {
        return null;
    }

    $prods = array();
    foreach ($from as $prod) {
        $prodParts = explode('@', $prod['rhs']);
        if (empty($prodParts[1])) {
            continue;
        }
        if ($ruleParts[0] != $prodParts[0]) {
            continue;
        }
        if ($ruleParts[1][0] == $prodParts[1][0]) {
            $newRule = array(
                'lhs' => $prod['lhs'],
                'rhs' => $prodParts[0] . $prodParts[1][0] . '@' . substr($prodParts[1], 1),
            );
            if (!in_array($newRule, $prods)) {
                $prods[] = $newRule;
            }
        }
    }

    $prods = addClosureRules($prods, $grammar);

    if (!checkStateExists($prods, $stateList)) {
        $stateList[] = $prods;
        return count($stateList) - 1;
    } else {
        return array_search($prods, $stateList);
    }
}

function checkStateExists($state, $stateList) {
    if (in_array($state, $stateList)) {
        return true;
    }
    return false;
}

function buildStateZero($grammar) {
    $prods    = array(
        array(
            'lhs' => $grammar[0]['lhs'],
            'rhs' => '@' . $grammar[0]['rhs'],
        ),
    );
    $lhsNts   = array();
    $lhsNts[] = $grammar[0]['rhs'];
    for ($i = 0; $i < count($lhsNts); $i++) {
        foreach ($grammar as $prod) {
            if ($lhsNts[$i] == $prod['lhs']) {
                if (strlen($prod['rhs']) == 1 && ctype_upper($prod['rhs']) && !in_array($prod['rhs'], $lhsNts)) {
                    $lhsNts[] = $prod['rhs'];
                }
                $prods[] = array(
                    'lhs'  => $prod['lhs'],
                    'rhs'  => '@' . $prod['rhs'],
                );
            }
        }
    }
    return $prods;
}

function addClosureRules($state, $grammar) {
    $lhsNts = array();
    for ($i = 0; $i < count($state); $i++) {
        $rhs = explode('@', $state[$i]['rhs']);
        if (!isset($rhs[1]) || strlen($rhs[1]) == 0 || !ctype_upper($rhs[1][0])) {
            continue;
        }
        $char = $rhs[1][0];
        if (!in_array($char, $lhsNts)) {
            $lhsNts[] = $char;
        }
    }

    for ($i = 0; $i < count($lhsNts); $i++) {
        foreach ($grammar as $prod) {
            if ($lhsNts[$i] == $prod['lhs']) {
                if (strlen($prod['rhs']) == 1 && ctype_upper($prod['rhs']) && !in_array($prod['rhs'], $lhsNts)) {
                    $lhsNts[] = $prod['rhs'];
                }
                $prod = array(
                    'lhs'  => $prod['lhs'],
                    'rhs'  => '@' . $prod['rhs'],
                );
                if (!in_array($state, $prod)) {
                    var_dump($prod);
                    $state[] = $prod;
                }
            }
        }
    }
    return $state;
}

function buildBaseGrammar($inFileName) {
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
    return $grammar;
}

function buildAugmentedGrammar($grammar) {
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
    return $augGrammar;
}

function displayGrammar($grammar) {
    $out = '';
    foreach ($grammar as $g) {
        $out .= $g['lhs'] . '->' . $g['rhs'] . "\n";
    }
    return $out;
}

function displayStateList($stateList) {
    $out = '';
    $i   = 0;
    foreach ($stateList as $s) {
        $out .= "I{$i}:\n";
        foreach ($s as $prod) {
            $out .= '   ' . $prod['lhs'] . '->' . $prod['rhs'];
            if (isset($prod['goto']) && !empty($prod['goto'])) {
                $out .= str_repeat(' ', 17 - strlen($prod['rhs']));
                $out .= 'goto(' . $prod['goto']['char'] . ')=I' . $prod['goto']['state'];
            }
            $out .= "\n";
        }
        $i++;
        $out .= "\n";
    }
    return $out;
}

function writeOutput($o, $outFileName) {
//    $outFile = fopen($outFileName, 'r');
    echo $o;
}

exit;