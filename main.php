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

$g = new Lr0Grammar($inFileName, $outFileName);
$g->buildGrammar();
$g->buildAugmentedGrammar();
$g->buildStateList();
$g->setGotoInfo();
$g->writeToConsole();

//$baseGrammar = buildBaseGrammar($inFileName);
//$augGrammar  = buildAugmentedGrammar($baseGrammar);
//$stateList   = buildStateList($augGrammar);
//var_dump($stateList);
//exit;
//
//$mainOutput  = "Augmented Grammar\n-----------------\n";
//$mainOutput .= displayGrammar($augGrammar);
//$mainOutput .= "\nSets of LR(0) Items\n-------------------\n";
//$mainOutput  .= displayStateList($stateList);
//
//writeOutput($mainOutput, $outFileName);

class Lr0Grammar {
    private $inFileName  = '';
    private $outFileName = '';
    private $inFile      = NULL;
    private $outFile     = NULL;

    private $rawInput    = '';

    private $grammar     = array();
    private $augGrammar  = array();
    private $states      = array();

    public function __construct($inFileName, $outFileName) {
        $this->inFileName  = $inFileName;
        $this->outFileName = $outFileName;
    }

    public function buildGrammar() {
        $this->inFile  = fopen($this->inFileName, 'r');
        $this->rawInput = fread($this->inFile, filesize($this->inFileName));
        fclose($this->inFile);

        $productions = explode("\n", $this->rawInput);
        foreach ($productions as $p) {
            $prod = explode('->', $p);
            $lhs  = $prod[0];
            $rhs  = (isset($prod[1])) ? $prod[1] : null;
            $prod = array(
                'lhs' => $lhs,
                'rhs' => $rhs,
            );
            $this->grammar[] = $prod;
        }
    }

    public function buildAugmentedGrammar() {
        foreach ($this->grammar as $i => $p) {
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
            $this->augGrammar[] = $prod;
        }
    }

    public function buildStateList() {
        $this->states   = array();
        $this->states[] = $this->buildStateZero();
        for ($i = 0; $i < count($this->states); $i++) {
            $curr = $this->states[$i];
            foreach ($curr as $prod) {
                $gotoState = $this->getGotoState($prod, $curr);
                if (is_array($gotoState)) {
                    $this->states[]  = $gotoState;
                }
            }
        }
    }

    public function setGotoInfo() {
        foreach ($this->states as &$state) {
            $usedChars = array();
            foreach ($state as &$prod) {
                $prod['goto'] = null;
                $gotoState    = $this->getGotoState($prod, $state);
                if (!is_null($gotoState)) {
                    $goto = $this->formatGoto($prod, $gotoState);
                    if (!in_array($goto['char'], $usedChars)) {
                        $usedChars[]  = $goto['char'];
                        $prod['goto'] = $goto;
                    }
                }
            }
        }
    }

    private function buildStateZero() {
        $prods    = array(
            array(
                'lhs'  => $this->augGrammar[0]['lhs'],
                'rhs'  => '@' . $this->augGrammar[0]['rhs'],
            ),
        );
        $lhsNts   = array();
        $lhsNts[] = $this->augGrammar[0]['rhs'];
        for ($i = 0; $i < count($lhsNts); $i++) {
            foreach ($this->augGrammar as $prod) {
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

    private function getGotoState($rule, $from) {
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
            if ($ruleParts[1][0] == $prodParts[1][0]) {
                $newRule = array(
                    'lhs'  => $prod['lhs'],
                    'rhs'  => $prodParts[0] . $prodParts[1][0] . '@' . substr($prodParts[1], 1),
                );
                if (!in_array($newRule, $prods)) {
                    $prods[] = $newRule;
                }
            }
        }

        $prods = $this->addClosureRules($prods);

        if (!$this->stateExists($prods)) {
            return $prods;
        } else {
            return array_search($prods, $this->states);
        }
    }

    private function formatGoto($production, $state) {
        $prod = explode('@', $production['rhs']);
        $char = $prod[1][0];
        return array(
            'char'  => $char,
            'state' => $state,
        );
    }

    private function stateExists($state) {
        if (in_array($state, $this->states)) {
            return true;
        }
        return false;
    }

    private function addClosureRules($state) {
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
            foreach ($this->augGrammar as $prod) {
                if ($lhsNts[$i] == $prod['lhs']) {
                    if (strlen($prod['rhs']) == 1 && ctype_upper($prod['rhs']) && !in_array($prod['rhs'], $lhsNts)) {
                        $lhsNts[] = $prod['rhs'];
                    }
                    $prod = array(
                        'lhs'  => $prod['lhs'],
                        'rhs'  => '@' . $prod['rhs'],
                    );
                    if (!in_array($state, $prod)) {
                        $state[] = $prod;
                    }
                }
            }
        }
        return $state;
    }

    public function displayGrammar() {
        $out = '';
        foreach ($this->grammar as $g) {
            $out .= $g['lhs'] . '->' . $g['rhs'] . "\n";
        }
        return $out;
    }

    public function displayAugmentedGrammar() {
        $out = '';
        foreach ($this->augGrammar as $g) {
            $out .= $g['lhs'] . '->' . $g['rhs'] . "\n";
        }
        return $out;
    }

    public function displayStateList() {
        $out = '';
        $i   = 0;
        foreach ($this->states as $s) {
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

    public function writeToConsole() {
        echo "Augmented Grammar\n-----------------\n";
        echo $this->displayAugmentedGrammar();
        echo "\nSets of LR(0) Items\n-------------------\n";
        echo $this->displayStateList();
    }

    public function writeToFile() {
        
    }
}

exit;