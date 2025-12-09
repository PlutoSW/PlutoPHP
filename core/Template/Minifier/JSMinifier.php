<?php

namespace Pluto\Template\Minifier;

class JSMinifier
{
    private $options = [];
    private $reservedWords = [
        'abstract',
        'arguments',
        'await',
        'boolean',
        'break',
        'byte',
        'case',
        'catch',
        'char',
        'class',
        'const',
        'continue',
        'debugger',
        'default',
        'delete',
        'do',
        'double',
        'else',
        'enum',
        'eval',
        'export',
        'extends',
        'false',
        'final',
        'finally',
        'float',
        'for',
        'function',
        'goto',
        'if',
        'implements',
        'import',
        'in',
        'instanceof',
        'int',
        'interface',
        'let',
        'long',
        'native',
        'new',
        'null',
        'package',
        'private',
        'protected',
        'public',
        'return',
        'short',
        'static',
        'super',
        'switch',
        'synchronized',
        'this',
        'throw',
        'throws',
        'transient',
        'true',
        'try',
        'typeof',
        'var',
        'void',
        'volatile',
        'while',
        'with',
        'yield',
        'async',
        'of',
        'from',
        'as'
    ];

    private $mangledNames = [];
    private $mangledCounter = 0;

    /**
     * Constructor
     * 
     * @param array $options Minification options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'compress' => true,
            'keep_fnames' => false,
            'keep_classnames' => false,
            'comments' => false,
            'toplevel' => false,
            'sequences' => true,
            'dead_code' => false,
            'conditionals' => false,
            'evaluate' => false,
            'safe_evaluate' => false,
            'booleans' => false,
            'loops' => true,
            'unused' => true,
            'if_return' => false,
            'join_vars' => false,
            'collapse_vars' => false,
            'reserved' => [],
            'ecma' => 5
        ], $options);

        $this->options['reserved'] = array_merge(
            $this->reservedWords,
            (array)$this->options['reserved']
        );
    }

    /**
     * Main minify method
     * 
     * @param string $code JavaScript code to minify
     * @return string Minified JavaScript code
     */
    public function minify(string $code): string
    {
        if (empty(trim($code))) {
            return '';
        }

        $shebang = '';
        if (preg_match('/^#![^\r\n]*/', $code, $matches)) {
            $shebang = $matches[0] . "\n";
            $code = substr($code, strlen($matches[0]));
        }

        if (!$this->options['comments']) {
            $code = $this->removeComments($code);
        }

        if ($this->options['compress']) {
            $code = $this->compress($code);
        }

        $code = $this->finalCleanup($code);

        return $shebang . $code;
    }

    /**
     * Remove comments from JavaScript code
     */
    private function removeComments(string $code): string
    {
        $result = '';
        $len = strlen($code);
        $i = 0;
        $inString = false;
        $stringChar = null;
        $inRegex = false;

        while ($i < $len) {
            $char = $code[$i];
            $nextChar = $i + 1 < $len ? $code[$i + 1] : '';

            if (!$inRegex && ($char === '"' || $char === "'" || $char === '`')) {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar && ($i === 0 || $code[$i - 1] !== '\\')) {
                    $inString = false;
                    $stringChar = null;
                }
                $result .= $char;
                $i++;
                continue;
            }

            if ($inString) {
                $result .= $char;
                $i++;
                continue;
            }

            if ($char === '/' && $nextChar === '/') {
                $i += 2;
                while ($i < $len && $code[$i] !== "\n" && $code[$i] !== "\r") {
                    $i++;
                }
                if ($i < $len) {
                    $result .= "\n";
                }
                continue;
            }

            if ($char === '/' && $nextChar === '*') {
                $i += 2;
                $commentContent = '';
                while ($i < $len - 1) {
                    if ($code[$i] === '*' && $code[$i + 1] === '/') {
                        $i += 2;
                        break;
                    }
                    $commentContent .= $code[$i];
                    $i++;
                }

                if ($this->options['comments'] === 'some' || $this->options['comments'] === true) {
                    if (preg_match('/@license|@preserve|@copyright|^!/', trim($commentContent))) {
                        $result .= '/*' . $commentContent . '*/';
                    }
                }
                continue;
            }

            $result .= $char;
            $i++;
        }

        return $result;
    }

    /**
     * Compress JavaScript code
     */
    private function compress(string $code): string
    {

        $code = $this->removeWhitespace($code);

        if ($this->options['booleans']) {
            $code = $this->optimizeBooleans($code);
        }

        if ($this->options['conditionals']) {
            $code = $this->optimizeConditionals($code);
        }

        if ($this->options['join_vars']) {
            $code = $this->joinVarStatements($code);
        }

        if ($this->options['sequences']) {
            $code = $this->optimizeSequences($code);
        }

        if ($this->options['evaluate'] && isset($this->options['safe_evaluate']) && $this->options['safe_evaluate']) {
            $code = $this->evaluateExpressions($code);
        }

        return $code;
    }

    /**
     * Remove unnecessary whitespace
     */
    private function removeWhitespace(string $code): string
    {
        $result = '';
        $len = strlen($code);
        $i = 0;
        $inString = false;
        $stringChar = null;
        $lastChar = '';

        while ($i < $len) {
            $char = $code[$i];
            $nextChar = ($i + 1 < $len) ? $code[$i + 1] : '';


            if (!$inString && ($char === '"' || $char === "'" || $char === '`')) {
                $inString = true;
                $stringChar = $char;
                $result .= $char;
                $lastChar = $char;
                $i++;
                continue;
            }

            if ($inString) {

                if ($char === $stringChar && $code[$i - 1] !== '\\') {
                    $inString = true;
                    $inString = false;
                    $stringChar = null;
                    $result .= $char;
                    $lastChar = $char;
                    $i++;
                    continue;
                }


                if ($stringChar === '`') {
                    if ($char === '$' && $nextChar === '{') {

                        $result .= '${';
                        $lastChar = '{';
                        $i += 2;
                        continue;
                    }
                    if (preg_match('/\s/', $char)) {

                        if ($lastChar !== ' ') {
                            $result .= ' ';
                            $lastChar = ' ';
                        }
                        $i++;
                        continue;
                    }
                }


                $result .= $char;
                $lastChar = $char;
                $i++;
                continue;
            }


            if (preg_match('/\s/', $char)) {

                $nextNonSpace = $this->getNextNonSpace($code, $i + 1);

                if ($this->needsWhitespace($lastChar, $nextNonSpace)) {
                    $result .= ' ';
                    $lastChar = ' ';
                }

                $i++;
                continue;
            }

            $result .= $char;
            $lastChar = $char;
            $i++;
        }

        return $result;
    }

    /**
     * Check if whitespace is needed between two characters
     */
    private function needsWhitespace(string $prev, string $next): bool
    {
        if (empty($prev) || empty($next)) {
            return false;
        }


        $isAlnum = function ($char) {
            return preg_match('/[a-zA-Z0-9_$]/', $char);
        };


        if ($isAlnum($prev) && $isAlnum($next)) {
            return true;
        }


        if (($prev === '+' && $next === '+') ||
            ($prev === '-' && $next === '-') ||
            ($prev === '/' && $next === '/') ||
            ($prev === '/' && $next === '*') ||
            ($prev === '<' && $next === '!') ||
            ($prev === '>' && $next === '>')
        ) {
            return true;
        }


        if ($prev === ']' && $next === '[') {
            return false;
        }

        return false;
    }

    /**
     * Get next non-whitespace character
     */
    private function getNextNonSpace(string $str, int $start): string
    {
        $len = strlen($str);
        for ($i = $start; $i < $len; $i++) {
            if (!preg_match('/\s/', $str[$i])) {
                return $str[$i];
            }
        }
        return '';
    }

    /**
     * Optimize boolean expressions
     */
    private function optimizeBooleans(string $code): string
    {

        $code = preg_replace('/\btrue\b/', '!0', $code);


        $code = preg_replace('/\bfalse\b/', '!1', $code);


        $code = preg_replace('/!!([a-zA-Z_$][a-zA-Z0-9_$]*)/', '$1', $code);

        return $code;
    }

    /**
     * Optimize conditional expressions
     */
    private function optimizeConditionals(string $code): string
    {

        $code = preg_replace(
            '/if\s*\(([^)]+)\)\s*return\s+([^;]+);?\s*return\s+([^;]+);?/',
            'return $1?$2:$3;',
            $code
        );


        $code = preg_replace(
            '/if\s*\(([^)]+)\)\s*\{?\s*([^;}]+);?\s*\}?\s*else\s*\{?\s*([^;}]+);?\s*\}?/',
            '$1?$2:$3',
            $code
        );

        return $code;
    }

    /**
     * Join consecutive var statements
     */
    private function joinVarStatements(string $code): string
    {



        return $code;
    }

    /**
     * Optimize sequences
     */
    private function optimizeSequences(string $code): string
    {


        return $code;
    }

    /**
     * Evaluate constant expressions
     */
    private function evaluateExpressions(string $code): string
    {


        $code = preg_replace_callback('/(?<!\[)(\d+)\s*([+\-*\/])\s*(\d+)(?!\])/', function ($matches) {

            $a = intval($matches[1]);
            $b = intval($matches[3]);
            $op = $matches[2];

            switch ($op) {
                case '+':
                    return $a + $b;
                case '-':
                    return $a - $b;
                case '*':
                    return $a * $b;
                case '/':
                    return $b != 0 ? intval($a / $b) : $matches[0];
                default:
                    return $matches[0];
            }
        }, $code);



        $code = preg_replace_callback('/"([^"]*)"\s*\+\s*"([^"]*)"/', function ($matches) {
            return '"' . $matches[1] . $matches[2] . '"';
        }, $code);

        $code = preg_replace_callback("/'([^']*)'\s*\+\s*'([^']*)'/", function ($matches) {
            return "'" . $matches[1] . $matches[2] . "'";
        }, $code);

        return $code;
    }

    /**
     * Final cleanup
     */
    private function finalCleanup(string $code): string
    {

        $code = preg_replace('/;+/', ';', $code);
        $code = preg_replace('/;\}/', '}', $code);
        $code = preg_replace('/\{;/', '{', $code);





        $code = preg_replace('/ +/', ' ', $code);

        return trim($code);
    }

    /**
     * Static helper method for quick minification
     * 
     * @param string $code JavaScript code
     * @param array $options Options
     * @return string Minified code
     */
    public static function minifyCode(string $code, array $options = []): string
    {
        $minifier = new self($options);
        return $minifier->minify($code);
    }

    /**
     * Minify a JavaScript file
     * 
     * @param string $inputFile Input file path
     * @param string $outputFile Output file path
     * @param array $options Options
     * @return bool Success status
     */
    public static function minifyFile(string $inputFile, string $outputFile, array $options = []): bool
    {
        if (!file_exists($inputFile)) {
            throw new \Exception("Input file not found: $inputFile");
        }

        $code = file_get_contents($inputFile);
        $minified = self::minifyCode($code, $options);

        return file_put_contents($outputFile, $minified) !== false;
    }

    /**
     * Get version information
     */
    public static function version(): string
    {
        return '1.0.0';
    }
}
