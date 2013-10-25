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

$baseGrammar  = buildBaseGrammar($inFileName);
$terminals    = buildTerminalList($baseGrammar);
$nonTerminals = buildNonTerminalList($baseGrammar);
$augGrammar   = buildAugmentedGrammar($baseGrammar);
$stateList    = buildStateList($augGrammar, $terminals, $nonTerminals);

$mainOutput   = "Augmented Grammar\n-----------------\n";
$mainOutput  .= displayGrammar($augGrammar);
$mainOutput  .= "\nSets of LR(0) Items\n-------------------\n";
$mainOutput  .= displayStateList($stateList);

writeOutput($mainOutput, $outFileName);

function buildStateList($g, $t, $nt) {
    $state0 = array();
    foreach ($g as $prod) {
        $state0[] = array(
            'lhs'  => $prod['lhs'],
            'rhs'  => '@' . $prod['rhs'],
            'goto' => '',
        );
    }

    $currIdx = 0;
    $states  = array($state0);
    do {
        $statesAdded = false;
        $goto        = array();
        $currState   = $states[$currIdx];

        foreach ($currState as $prod) {
            $rest = explode('@', $prod['rhs']);
            $nextChar = $rest[1][0];
            $next = $rest[0] . $rest[1][0] . '@' . substr($rest[1], 1);
            $goto[$nextChar][] = array(
                'lhs' => $prod['lhs'],
                'rhs' => $next,
            );
        }
        foreach ($goto as $char => &$state) {
            $oldCount = count($states);
            $state['num'] = getStateNum($state, $states);
            $newCount = count($states);

            if ($oldCount != $newCount) {
                $statesAdded = true;
            }
        }
        foreach ($currState as &$prod) {
            $rest = explode('@', $prod['rhs']);
            $nextChar = $rest[1][0];
            if (isset($goto[$nextChar])) {
                $prod['goto'] = "goto({$nextChar})=I{$goto[$nextChar]['num']}";
                unset($goto[$nextChar]);
            }
        }
    } while ($statesAdded);

    return $states;
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

function buildTerminalList($baseGrammar) {
    $t = array();
    foreach ($baseGrammar as $p) {
        $lhs = str_split($p['lhs']);
        $rhs = str_split($p['rhs']);
        foreach ($lhs as $char) {
            if (!ctype_upper($char) && $char != "'" && !empty($char)) {
                $t[] = $char;
            }
        }
        foreach ($rhs as $char) {
            if (!ctype_upper($char) && !empty($char)) {
                $t[] = $char;
            }
        }
    }
    $t = array_unique($t);
    return $t;
}

function buildNonTerminalList($baseGrammar) {
    $nt = array();
    foreach ($baseGrammar as $p) {
        $lhs = str_split($p['lhs']);
        $rhs = str_split($p['rhs']);
        foreach ($lhs as $char) {
            if (ctype_upper($char) && !empty($char)) {
                $nt[] = $char;
            }
        }
        foreach ($rhs as $char) {
            if (ctype_upper($char) && !empty($char)) {
                $nt[] = $char;
            }
        }
    }
    $nt = array_unique($nt);
    return $nt;
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
//                $out .= $prod['goto'];
                $out .= '...';
            }
            $out .= "\n";
        }
    }
    return $out;
}

function getStateNum($state, &$stateList) {
    $n = 0;
    foreach ($stateList as $existing) {
        if (count($existing) != count($state)) {
            continue;
        }
        $matches = true;
        for ($i = 0; $i < count($existing); $i++) {
            if ($state[$i]['lhs'] != $existing[$i]['lhs']) {
                $matches = false;
                break;
            }
            if ($state[$i]['rhs'] != $existing[$i]['rhs']) {
                $matches = false;
                break;
            }
        }
        if ($matches) {
            return $n;
        }
        $n++;
    }
    $stateList[] = $state;
    return count($stateList) - 1;
}

function writeOutput($o, $outFileName) {
//    $outFile = fopen($outFileName, 'r');
    echo $o;
}

exit;