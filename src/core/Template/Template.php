<?php

namespace Pluto\Core\Template;

use Pluto\Core\System;

class Template extends System
{

    static $blocks = array();
    static $cache_path =  __DIR__ . '/../../../storage/cache/';
    static $template_path =  __DIR__ . '/../../../frontend/templates/';
    static $cache_enabled = FALSE;
    static $file;
    static public $styles = array();
    static public $scripts = array();
    static $functions = [
        'asset' => 'self::asset',
        'url' => 'self::url',
        '_' => 'self::_'
    ];
    static $filters = [
        'upper' => 'strtoupper',
        'lower' => 'strtolower',
        'capitalize' => 'ucwords',
        'e' => 'htmlspecialchars',
        'escape' => 'htmlspecialchars',
        'number_format' => 'number_format',
        'date' => 'date',
        'raw' => 'self::rawFilter',
        'length' => 'self::getLength',
        'striptags' => 'strip_tags',
        'trim' => 'trim',
        'ltrim' => 'ltrim',
        'rtrim' => 'rtrim',
        'json_encode' => 'json_encode',
        'json_decode' => 'json_decode',
        'base64_encode' => 'base64_encode',
        'base64_decode' => 'base64_decode',
        'replace' => 'str_replace',
        'urlencode' => 'urlencode',
        'urldecode' => 'urldecode',
        'nl2br' => 'nl2br',
        'implode' => 'implode',
        'first' => 'reset',
        'last' => 'end',
        'keys' => 'array_keys',
        'values' => 'array_values',
        'join' => 'implode',
        'default' => 'self::applyDefaultFilter',
        'get' => 'self::getProperty',
    ];

    static function view($file, $data = array())
    {
        $file = self::$template_path  . $file . ".html";
        self::$file = $file;
        $cached_file = self::cache($file);
        \extract((array)$data, EXTR_SKIP);
        \extract((array)System::$global, \EXTR_SKIP);

        $styles = self::$styles;
        $scripts = self::$scripts;

        ob_start();
        require $cached_file;
        if (getenv("MINIFY") !== "false") {
            $output = (new Minifier())->minify(ob_get_contents());
        } else {
            $output = ob_get_contents();
        }
        ob_end_clean();
        return $output;
    }

    static function cache($file)
    {
        if (!file_exists(self::$cache_path)) {
            mkdir(self::$cache_path, 0744, true);
        }
        $cached_file = self::$cache_path . md5($file) . ".php";
        if (!self::$cache_enabled || !file_exists($cached_file) || filemtime($cached_file) < filemtime($file)) {
            $code = self::includeFiles($file, self::$file);
            $code = self::compileCode($code);
            file_put_contents($cached_file, '<?php class_exists(\'' . __CLASS__ . '\') or exit; ?>' . PHP_EOL . $code);
        }
        return $cached_file;
    }

    static function clearCache()
    {
        foreach (glob(self::$cache_path . '*') as $file) {
            unlink($file);
        }
    }

    static function compileCode($code)
    {
        $code = self::compileComments($code);
        $code = self::compileBlock($code);
        $code = self::compileYield($code);
        $code = self::compileEscapedEchos($code);
        $code = self::compileEchos($code);
        $code = self::compileEach($code);
        $code = self::compileIfs($code);
        $code = self::compilePHP($code);
        return $code;
    }

    static function includeFiles($file, $mainFile)
    {
        $callerFilePath = pathinfo($mainFile, PATHINFO_DIRNAME);
        if ($file != $mainFile) {
            $file = $callerFilePath . '/' . $file;
        }
        $code = file_get_contents($file);
        preg_match_all('/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            $code = str_replace($value[0], self::includeFiles($value[2], $file), $code);
        }
        $code = preg_replace('/{% ?(extends|include) ?\'?(.*?)\'? ?%}/i', '', $code);
        return $code;
    }

    static function compilePHP($code)
    {
        return preg_replace('~\{%\s*(.+?)\s*\%}~is', '<?php $1 ?>', $code);
    }

    static function compileComments($code)
    {
        return preg_replace('~\{#.*?#\}~is', '', $code);
    }

    static function compileEchos($code)
    {
        return preg_replace_callback('~\{{\s*(.+?)\s*\}}~is', function ($matches) {
            $content = $matches[1];
            $parts = explode('|', $content);
            $expression = trim(array_shift($parts));
            $filters = $parts;

            // Convert dot notation to arrow notation for objects
            $expression = preg_replace('/(\$\w+)\.(\w+)/', '$1->$2', $expression);

            $output = $expression;

            if (preg_match('/^(\w+)\s*\((.*)\)\s*$/s', $expression, $func_matches)) {
                $function_name = $func_matches[1];
                $function_args = $func_matches[2];

                if (isset(self::$functions[$function_name])) {
                    $php_function = self::$functions[$function_name];
                    $output = "{$php_function}({$function_args})";
                }
            }

            // Apply filters
            foreach ($filters as $filter) {
                $filter_parts = explode(':', $filter, 2);
                $filter_name = trim($filter_parts[0]);
                $filter_args = isset($filter_parts[1]) ? ',' . $filter_parts[1] : '';

                if (isset(self::$filters[$filter_name])) {
                    $php_function = self::$filters[$filter_name];
                    $output = "{$php_function}({$output}{$filter_args})";
                }
            }

            return "<?php echo {$output}; ?>";
        }, $code);
    }

    static function compileEscapedEchos($code)
    {
        return preg_replace('~\{{{\s*(.+?)\s*\}}}~is', '<?php echo htmlentities($1, ENT_QUOTES, \'UTF-8\'); ?>', $code);
    }

    static function compileBlock($code)
    {
        preg_match_all('/{% ?block ?(.*?) ?%}(.*?){% ?endblock ?%}/is', $code, $matches, PREG_SET_ORDER);
        foreach ($matches as $value) {
            if (!array_key_exists($value[1], self::$blocks)) self::$blocks[$value[1]] = '';
            if (strpos($value[2], '@parent') === false) {
                self::$blocks[$value[1]] = $value[2];
            } else {
                self::$blocks[$value[1]] = str_replace('@parent', self::$blocks[$value[1]], $value[2]);
            }
            $code = str_replace($value[0], '', $code);
        }
        return $code;
    }

    static function compileYield($code)
    {
        foreach (self::$blocks as $block => $value) {
            $code = preg_replace('/{% ?@block ?' . $block . ' ?%}/', $value, $code);
        }
        $code = preg_replace('/{% ?@block ?(.*?) ?%}/i', '', $code);
        return $code;
    }

    static function compileEach($code)
    {
        $code = preg_replace('~\{%\s*each\s(.+?)\s+as\s+(.+?)\s*\%}~is', '<?php foreach($1 as $2): ?>', $code);
        $code = preg_replace('~\{%\s*endeach\s*\%}~is', '<?php endforeach; ?>', $code);

        // Add standard for loop support
        $code = preg_replace('~\{%\s*for\s+(.+?)\s*=\s*(.+?)\s*to\s*(.+?)\s*\%}~is', '<?php for($1 = $2; $1 <= $3; $1++): ?>', $code);
        $code = preg_replace('~\{%\s*endfor\s*\%}~is', '<?php endfor; ?>', $code);

        return $code;
    }

    static function compileIfs($code)
    {
        $code = preg_replace('/\{%\s*if\s+(.+?)\s*\%}/is', '<?php if($1): ?>', $code);
        $code = preg_replace('/\{%\s*elseif\s+(.+?)\s*\%}/is', '<?php elseif($1): ?>', $code);
        $code = preg_replace('/\{%\s*else\s*\%}/is', '<?php else: ?>', $code);
        $code = preg_replace('/\{%\s*endif\s*\%}/is', '<?php endif; ?>', $code);
        return $code;
    }

    static function rawFilter($value)
    {
        return $value;
    }

    static function getLength($value)
    {
        if (is_array($value) || $value instanceof \Countable) {
            return count($value);
        }
        if (is_string($value)) {
            return mb_strlen($value, 'UTF-8');
        }
        return 0;
    }

    static function applyDefaultFilter($value, $default = '')
    {
        if (empty($value) && $value !== 0 && $value !== '0') {
            return $default;
        }
        return $value;
    }

    static function asset($path)
    {
        $host = rtrim(getenv('HOST'), '/');
        $path = ltrim(trim($path), '/');
        return "{$host}/frontend/assets/{$path}";
    }

    static function url($path)
    {
        $host = rtrim(getenv('HOST'), '/');
        $path = ltrim(trim($path), '/');
        return "{$host}/{$path}";
    }

    static function _($key, ...$params)
    {
        return e($key, ...$params);
    }

    static function getProperty($data, $property)
    {
        if (is_object($data) && $data?->{$property}) {
            return $data->{$property};
        }
        if (is_array($data) && isset($data[$property])) {
            return $data[$property];
        }
        return null;
    }
}
