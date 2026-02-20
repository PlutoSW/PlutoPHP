<?php

namespace Pluto\Template;

use Pluto\Template\Minifier;

class Template
{
    protected string $viewsPath;
    protected string $cachePath;
    protected array $sections = [];
    protected ?string $layout = null;
    protected array $stacks = [];
    protected array $pushStack = [];
    protected array $sharedData = [];
    protected array $dependencies = [];
    protected array $allowed_filters = [
        'htmlspecialchars',
        'strtoupper',
        'strtolower',
        'ucfirst',
        'lcfirst',
        'ucwords',
        'trim',
        'date',
        'strtotime',
        'number_format',
        'str_replace',
        'json_encode',
        'count',
        'nl2br',
        'urlencode',
        'rawurlencode',
        'global',
        'array_map',
        'toPrice',
        'print_r',
        'var_dump',
        'default'
    ];

    public function __construct()
    {
        $this->viewsPath = BASE_PATH . '/app/Views/';
        $this->cachePath = BASE_PATH . '/storage/cache/';

        if (!file_exists($this->viewsPath)) {
            mkdir($this->viewsPath, 0755, true);
        }

        if (!file_exists($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function render(string $view, array $data = []): string
    {
        $viewToPath = str_replace('.', '/', $view);
        $viewFile = $this->viewsPath . $viewToPath . '.phtml';
        $cachedFile = $this->cachePath . md5($view) . '.php';

        if (!file_exists($viewFile)) {
            if (\str_contains($viewFile, '/Views/errors/')) {
                $getFileName = str_replace('Views/errors/', '', $viewToPath) . '.php';

                $errorPage = BASE_PATH . '/core/Template/' . $getFileName;

                if (\file_exists($errorPage)) {
                    ob_start();
                    require_once $errorPage;
                    $content = ob_get_clean();
                    $response = new \Pluto\Response();

                    $response->setStatusCode(404);
                    return $response->setContent($content);
                }
            } else {
                throw new \Exception(__('errors.view_not_found', ['view' => $viewFile]));
            }
        }

        $isCacheValid = file_exists($cachedFile);
        if ($isCacheValid) {
            $cacheTime = filemtime($cachedFile);
            if (filemtime($viewFile) > $cacheTime) {
                $isCacheValid = false;
            } else {
                $dependencies = $this->getCacheDependencies($cachedFile);
                foreach ($dependencies as $dependency) {
                    if (!file_exists($dependency) || filemtime($dependency) > $cacheTime) {
                        $isCacheValid = false;
                        break;
                    }
                }
            }
        }

        if (!$isCacheValid) {
            $this->dependencies = [$viewFile];
            $content = file_get_contents($viewFile);
            $compiledContent = $this->compile($content);
            $dependenciesComment = '<?php /* Dependencies: ' . implode(',', $this->dependencies) . ' */ ?>' . "\n";
            if ($this->layout) {
                $layoutDependencies = $this->getDependenciesForView($this->layout);
                $this->dependencies = array_unique(array_merge($this->dependencies, $layoutDependencies));
                $dependenciesComment = '<?php /* Dependencies: ' . implode(',', $this->dependencies) . ' */ ?>' . "\n";
            }
            file_put_contents($cachedFile, $dependenciesComment . $compiledContent);
        }

        $content = $this->renderFile($cachedFile, $data);

        if ($this->layout) {
            $layoutName = $this->layout;
            $this->layout = null;
            $content =  $this->render($layoutName, $data);

            if (getenv('USE_PLUTO_JS') === 'true') {
                $plutoCssLink = '<link id="pluto-css" rel="stylesheet" href="' . getenv('HOST') . '/core/styles">';
                $plutoJsScript = '<script>window.languageData = ' . \Pluto\Lang::getInstance() . ';</script><script src="' . getenv('HOST') . '/core/scripts"></script>';
                if (\str_contains($content, '<link')) {
                    $content = preg_replace('/(<link\b[^>]*>)/i', $plutoCssLink . "\n    $1", $content, 1);
                } else {
                    $content = preg_replace('/<\/head>/i', "$plutoCssLink\n</head>", $content,);
                }

                $content = preg_replace('/(<link id\="pluto-css" \b[^>]*>)/i', "$1\n    " . $plutoJsScript, $content, 1);
            }
            if (getenv('MINIFY') === 'true') {
                $content = Minifier::minifyHtml($content);
            }
            return $content;
        }
        if (getenv('MINIFY') === 'true') {
            $content = Minifier::minifyHtml($content);
        }
        return $content;
    }

    protected function renderFile(string $cachedFile, array $data): string
    {
        extract(array_merge($this->sharedData, $data), EXTR_SKIP);

        ob_start();
        include $cachedFile;
        return ob_get_clean();
    }

    protected function compile(string $content): string
    {
        $content = preg_replace('/@style\s*\(\s*\'(.*?)\'\s*\)/', '<?php echo \'/style/$1\'; ?>', $content);
        $content = preg_replace('/@script\s*\(\s*\'(.*?)\'\s*\)/', '<?php echo \'/script/$1\'; ?>', $content);
        $content = preg_replace('/@corestyle\s*\(\s*\'(.*?)\'\s*\)/', '<?php echo \'/core/style/$1\'; ?>', $content);
        $content = preg_replace('/@corescript\s*\(\s*\'(.*?)\'\s*\)/', '<?php echo \'/core/script/$1\'; ?>', $content);
        $content = preg_replace('/@asset\s*\(\s*\'(.*?)\'\s*\)/', '<?php echo \'/app/assets/$1\'; ?>', $content);
        $content = preg_replace('/@global\s*\(\s*\'(.*?)\'\s*\)/', '<?php echo $GLOBALS[\'$1\']; ?>', $content);
        $content = preg_replace('/@coreasset\s*\(\s*\'(.*?)\'\s*\)/', '<?php echo rtrim(getenv(\'HOST\') ?? \'\', \'/\') . \'/core/assets/$1\'; ?>', $content);
        $content = preg_replace('/@url\s*\(\s*\'(.*?)\'\s*\)/', '<?php echo rtrim(getenv(\'HOST\') ?? \'\', \'/\') . \'$1\'; ?>', $content);
        $content = preg_replace('/@__\s*\((.*?)\)/', '<?php echo __($1); ?>', $content);
        $content = preg_replace('/@extends\s*\(\s*\'(.*?)\'\s*\)/', '<?php $this->layout = \'$1\'; ?>', $content);
        $content = preg_replace_callback('/@section\s*\(\s*\'(.*?)\'\s*(?:,\s*(.*?))?\s*\)/', function ($matches) {
            $name = $matches[1];
            if (isset($matches[2])) {
                return '<?php $this->sections[\'' . $name . '\'] = ' . $matches[2] . '; ?>';
            }
            return '<?php $this->startSection(\'' . $name . '\'); ?>';
        }, $content);
        $content = preg_replace('/@endsection/', '<?php $this->endSection(); ?>', $content);
        $content = preg_replace('/@yield\s*\(\s*\'(.*?)\'\s*(?:,\s*(.*?))?\s*\)/', '<?php echo $this->yieldSection(\'$1\', $2); ?>', $content);
        $content = preg_replace('/@push\s*\(\s*\'(.*?)\'\s*\)/', '<?php $this->startPush(\'$1\'); ?>', $content);
        $content = preg_replace('/@endpush/', '<?php $this->endPush(); ?>', $content);
        $content = preg_replace('/@stack\s*\(\s*\'(.*?)\'\s*\)/', '<?php echo $this->yieldStack(\'$1\'); ?>', $content);
        $content = preg_replace_callback('/@include\s*\(\s*\'(.*?)\'\s*(,\s*(.*?))?\s*\)/', function ($matches) {
            $view = $matches[1];
            $viewPath = $this->viewsPath . str_replace('.', '/', $view) . '.phtml';

            if (!file_exists($viewPath)) {
                $this->dependencies[] = $viewPath;
                return "<!-- " . __('errors.view_not_found', ['view' => $view]) . " -->";
            }

            $includedContent = file_get_contents($viewPath);
            $compiledIncludedContent = $this->compile($includedContent);

            $this->dependencies[] = $viewPath;
            if (isset($matches[3])) {
                return '<?php extract(array_merge(get_defined_vars(), ' . $matches[3] . ')); ?>' . $compiledIncludedContent;
            }

            return $compiledIncludedContent;
        }, $content);

        $content = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function ($matches) {
            $expressionWithFilters = $matches[1];
            $parts = explode('|', $expressionWithFilters);
            $variableExpression = trim(array_shift($parts));

            if (empty($parts)) {
                return '<?php echo htmlspecialchars(' . $this->compileExpression($variableExpression) . ' ?? \'\'); ?>';
            }

            $output = $this->compileExpression($variableExpression);
            foreach ($parts as $filter) {
                $filterParts = explode(':', trim($filter));
                $filterName = trim($filterParts[0]);
                $filterArgs = isset($filterParts[1]) ? ', ' . $filterParts[1] : '';

                if (!in_array($filterName, $this->allowed_filters)) {
                    continue;
                }
                if ($filterName === 'array_map' || $filterName === 'str_replace') {
                    $output = sprintf('%s(%s, %s)', $filterName, trim(ltrim($filterArgs, ', ')), $output);
                } else if ($filterName === 'default') {
                    $defaultValue = trim(ltrim($filterArgs, ', '));
                    $output = "({$output}!==null) ? {$output} : {$defaultValue}";
                } elseif ($filterName === 'toPrice') {
                    $output = \sprintf('$GLOBALS["template"]::toPrice(%s, "%s", "%s")', $output, $filterParts[1], $filterParts[2]);
                } elseif ($filterName === 'json_encode') {
                    $output = sprintf('json_encode(%s, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)', $output);
                } else {
                    $output = sprintf('%s(%s%s)', $filterName, $output, $filterArgs);
                }
            }

            return '<?php echo ' . $output . '; ?>';
        }, $content);
        $content = preg_replace_callback('/@if\((.*)\)/', function ($matches) {
            return '<?php if(' . $this->compileExpression($matches[1]) . '): ?>';
        }, $content);
        $content = preg_replace_callback('/@elseif\((.*)\)/', function ($matches) {
            return '<?php elseif(' . $this->compileExpression($matches[1]) . '): ?>';
        }, $content);
        $content = preg_replace('/@else/', '<?php else: ?>', $content);
        $content = preg_replace('/@endif/', '<?php endif; ?>', $content);
        $content = preg_replace_callback('/@foreach\((.*)\)/', function ($matches) {
            $expression = $matches[1];
            $parts = preg_split('/\s+as\s+/', $expression);
            $iterable = $this->compileExpression(trim($parts[0]));
            $loopVars = isset($parts[1]) ? ' as ' . $this->compileExpression(trim($parts[1])) : '';
            return '<?php foreach(' . $iterable . $loopVars . '): ?>';
        }, $content);
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);
        $content = preg_replace_callback('/@for\((.*)\)/', function ($matches) {
            return '<?php for(' . $this->compileExpression($matches[1]) . '): ?>';
        }, $content);
        $content = preg_replace('/@endfor/', '<?php endfor; ?>', $content);
        $content = preg_replace('/@php/', '<?php', $content);
        $content = preg_replace('/@endphp/', '?>', $content);

        $content = preg_replace_callback('/@set\((.*)\)/', function ($matches) {
            return '<?php ' . $this->compileSetDirective($matches[1]) . '; ?>';
        }, $content);

        return $content;
    }

    protected function compileSetDirective(string $expression): string
    {
        $parts = explode(',', $expression, 3);
        if (count($parts) < 2) {
            return "echo '<!-- Invalid @set directive -->';";
        }

        $variableName = trim(trim($parts[0]), " '\"");
        $valueExpression = $this->compileExpression(trim($parts[1]));
        $isGlobal = isset($parts[2]) && trim($parts[2]) === 'true';

        return $isGlobal
            ? '$this->sharedData[\'' . $variableName . '\'] = ' . $valueExpression
            : '$' . $variableName . ' = ' . $valueExpression;
    }

    protected function compileExpression(string $expression): string
    {
        // This regex finds variable-like words that are not function calls and prepends a '$' to them.
        // It avoids words followed by '(', words already starting with '$', and numeric/string literals.
        // This function no longer automatically prepends '$' to variables.
        // Standard PHP syntax with '$' is now required in templates.
        return $expression;
    }

    protected function startSection(string $name): void
    {
        ob_start();
        $this->sections[$name] = '';
    }

    protected function endSection(): void
    {
        $content = ob_get_clean();
        $lastKey = array_key_last($this->sections);
        if ($lastKey !== null) {
            $this->sections[$lastKey] = $content;
        }
    }

    protected function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    protected function startPush(string $name): void
    {
        ob_start();
        $this->pushStack[] = $name;
    }

    protected function endPush(): void
    {
        $content = ob_get_clean();
        $name = array_pop($this->pushStack);
        if ($name !== null) {
            if (!isset($this->stacks[$name])) {
                $this->stacks[$name] = [];
            }
            $this->stacks[$name][] = $content;
        }
    }

    protected function yieldStack(string $name): string
    {
        return isset($this->stacks[$name]) ? implode("\n", $this->stacks[$name]) : '';
    }

    private function getCacheDependencies(string $cachedFile): array
    {
        $handle = fopen($cachedFile, 'r');
        if (!$handle) {
            return [];
        }

        $firstLine = fgets($handle);
        fclose($handle);

        if (preg_match('/Dependencies: (.*?)\s*\*\//', $firstLine, $matches)) {
            return explode(',', $matches[1]);
        }
        return [];
    }

    private function getDependenciesForView(string $view): array
    {
        $viewFile = $this->viewsPath . str_replace('.', '/', $view) . '.phtml';
        if (!file_exists($viewFile)) {
            return [];
        }

        $tempDependencies = [$viewFile];
        $content = file_get_contents($viewFile);
        preg_match_all('/@include\s*\(\s*\'(.*?)\'\s*\)/', $content, $matches);

        return array_merge($tempDependencies, array_map(fn($v) => $this->viewsPath . str_replace('.', '/', $v) . '.phtml', $matches[1]));
    }

    public static function toPrice($price, $locale, $currency)
    {
        $formatter = new \NumberFormatter($locale,  \NumberFormatter::CURRENCY);
        return $formatter->formatCurrency($price, $currency);
    }
}
