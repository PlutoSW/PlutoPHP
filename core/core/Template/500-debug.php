<?php


function getCodeSnippet(string $file, int $line, int $contextLines = 8): ?array
{
    if (!file_exists($file) || !is_readable($file)) {
        return null;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES);
    $totalLines = count($lines);

    $start = max(1, $line - $contextLines);
    $end = min($totalLines, $line + $contextLines);

    $snippetLines = [];
    for ($i = $start - 1; $i < $end; $i++) {
        $snippetLines[] = $lines[$i];
    }

    $minIndent = null;
    foreach ($snippetLines as $lineContent) {
        if (trim($lineContent) === '') continue;

        preg_match('/^\s*/', $lineContent, $matches);
        $indent = strlen($matches[0]);

        if ($minIndent === null || $indent < $minIndent) {
            $minIndent = $indent;
        }
    }

    $snippet = '';
    foreach ($snippetLines as $i => $lineContent) {
        $currentLineNum = $start + $i;
        $isErrorLine = ($currentLineNum === $line);

        $trimmedLine = ($minIndent > 0) ? substr($lineContent, $minIndent) : $lineContent;
        $escapedLine = htmlspecialchars($trimmedLine, ENT_QUOTES, 'UTF-8');

        $snippet .= '<span class="line' . ($isErrorLine ? ' highlight' : '') . '">';
        $snippet .= '<span class="line-number">' . $currentLineNum . '</span>';
        $snippet .= '<code>' . $escapedLine . '</code>';
        $snippet .= '</span>';
    }

    return ['code' => $snippet, 'startLine' => $start];
}


function renderVariable($var, $depth = 0): string
{
    $maxDepth = 10;
    $maxStringLength = 1024;
    $maxArrayElements = 256;

    if ($depth >= $maxDepth) {
        if (is_object($var)) {
            return '<span class="var-type">object</span>(' . get_class($var) . ') <i>*Max depth reached*</i>';
        }
        if (is_array($var)) {
            return '<span class="var-type">array</span>(' . count($var) . ') <i>*Max depth reached*</i>';
        }
        return '<span class="var-string">"..."</span>';
    }

    if (is_null($var)) {
        return '<span class="var-null">null</span>';
    }
    if (is_bool($var)) {
        return '<span class="var-bool">' . ($var ? 'true' : 'false') . '</span>';
    }
    if (is_string($var)) {
        $len = strlen($var);
        if ($len > $maxStringLength) {
            return '<span class="var-string" title="' . htmlspecialchars($var) . '">"' . htmlspecialchars(substr($var, 0, $maxStringLength)) . '..."</span> <span class="var-type">string(' . $len . ')</span>';
        }
        return '<span class="var-string">"' . htmlspecialchars($var) . '"</span>';
    }
    if (is_int($var) || is_float($var)) {
        return '<span class="var-numeric">' . $var . '</span>';
    }
    if (is_array($var)) {
        $count = count($var);
        $output = '<div class="var-array-wrapper"><span class="var-type">array</span> (' . $count . ') <span class="collapser"></span><ul>';
        $i = 0;
        foreach ($var as $key => $value) {
            if ($i++ >= $maxArrayElements) {
                $output .= '<li>... (' . ($count - $maxArrayElements) . ' more)</li>';
                break;
            }
            $output .= '<li><span class="array-key">' . htmlspecialchars($key) . '</span> => ' . renderVariable($value, $depth + 1) . '</li>';
        }
        $output .= '</ul></div>';
        return $output;
    }
    if (is_object($var)) {
        $class = get_class($var);
        $output = '<div class="var-object-wrapper"><span class="var-type">object</span> (' . $class . ') <span class="collapser"></span><ul>';
        foreach ((array)$var as $key => $value) {
            $key = trim(str_replace($class, '', $key));
            $output .= '<li><span class="object-key">' . htmlspecialchars($key) . '</span>: ' . renderVariable($value, $depth + 1) . '</li>';
        }
        $output .= '</ul></div>';
        return $output;
    }
    return '<span>' . htmlspecialchars(print_r($var, true)) . '</span>';
}

function getUndefinedVariable($message)
{
    if (preg_match('/Undefined variable? \$?(\w+)/', $message, $matches)) {
        return '$' . $matches[1];
    }
    return null;
}

function getAccesstoUndeclaredStaticProperty($message)
{
    if (preg_match('/Access to undeclared static property (.*)/', $message, $matches)) {
        return $matches[1];
    }
    return null;
}

function getSuggestion(Throwable $e): ?string
{
    $message = $e->getMessage();
    return match (true) {
        str_contains($message, 'Undefined variable') => "You are using an undefined variable. Check the <b>" . getUndefinedVariable($message) . "</b> variable for typos.",
        str_contains($message, 'Class') && str_contains($message, 'not found') => "The class definition could not be found. Check the class name, file path, or your autoload configuration.",
        str_contains($message, 'Call to undefined function') => "An undefined function was called. Check the function name or ensure it is correctly defined or included.",
        str_contains($message, 'Access to undeclared static property') => "An attempt was made to access an undeclared static property. Check the <b>" . getAccesstoUndeclaredStaticProperty($message) . "</b> property name or ensure it is correctly defined or included.",
        default => null
    };
}

function formatTraceData(Throwable $e): array
{
    $traces = [];
    $currentException = $e;
    while ($currentException) {
        $trace_data = $currentException->getTrace();
        array_unshift($trace_data, [
            'file' => $currentException->getFile(),
            'line' => $currentException->getLine(),
            'function' => '{main}',
            'args' => []
        ]);

        $traces[] = [
            'message' => $currentException->getMessage(),
            'class' => get_class($currentException),
            'code' => $currentException->getCode(),
            'trace' => $trace_data
        ];

        $currentException = $currentException->getPrevious();
    }

    return $traces;
}

function errorDefineFromSeverityCode($severity)
{
    $errorDefines = [
        1 => "Error",
        2 => "Warning",
        4 => "Parse Error",
        8 => "Notice",
        16 => "PHP Core Error",
        32 => "PHP Core Warning",
        64 => "Compile Error",
        128 => "Compile Warning",
        256 => "Error",
        512 => "Warning",
        1024 => "Notice",
    ];
    return isset($errorDefines[$severity]) ? $errorDefines[$severity] : "Error";
}

function getRealPath($file)
{
    return str_replace(BASE_PATH, "", $file);
}

function openFileLink($file, $line)
{

    $f = getRealPath($file);
    if (!getenv('DEV_LOCAL_PATH')) {
        return null;
    }
    $localPath = getenv('DEV_LOCAL_PATH');
    if (str_ends_with(getenv('DEV_LOCAL_PATH'), "/") || str_ends_with(getenv('DEV_LOCAL_PATH'), "\\")) {
        $localPath = substr($localPath, 0, strlen($localPath) - 1);
    }
    $file =  $localPath . $f;
    $ide = getenv('DEV_IDE') ?? 'vscode';
    $opener = "://file/$file:$line";
    switch ($ide) {
        case 'sublime':
        case 'emacs':
        case 'vim':
        case 'nvim':
        case 'textmate':
            $opener = "://open?url=file://$file&line=$line";
            break;

        case 'atom':
            $opener = "://core/open/file?filename=$file&line=$line";
            break;

        case 'phpstorm':
        case 'webstorm':
        case 'idea':
        case 'pycharm':
        case 'android-studio':
        case 'xcode':
        case 'eclipse':
        case 'netbeans':
        case 'devenv':
            $opener = "://open?file=$file&line=$line";
            break;
    }
    return $ide . $opener;
}

$exceptionChain = formatTraceData($e);
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('debug.error'); ?>: <?php echo htmlspecialchars($e->getMessage()); ?></title>
    <link rel="stylesheet" href="<?php echo getenv('HOST'); ?>/core/assets/css/debug.css" />
</head>

<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><?php echo __(errorDefineFromSeverityCode($e->getCode())); ?></h1>
                <p><?php echo $e->getMessage(); ?></p>
                <?php if (isset($errorRandomID)): ?>
                    <p class="logInfo">
                        <b><?php echo __('debug.log_id'); ?>:</b>
                        <span class="log-data"><?php echo $errorRandomID; ?></span>
                    </p>
                    <p class="logInfo">
                        <b><?php echo __('debug.log_file'); ?>:</b>
                        <span class="log-data"><?php echo $log["file"]; ?></span>
                    </p>
                <?php endif; ?>
            </div>
            <div class="theme-switcher" id="theme-toggle">🌗</div>
        </div>

        <div class="content-wrapper">
            <div class="tabs">
                <button class="tab active" data-tab="exception"><?php echo __('debug.tab_exception'); ?></button>
                <button class="tab" data-tab="request"><?php echo __('debug.tab_request'); ?></button>
                <button class="tab" data-tab="app"><?php echo __('debug.tab_app'); ?></button>
            </div>

            <div id="exception" class="tab-content active">
                <?php $suggestion = getSuggestion($e);
                if ($suggestion): ?>
                    <div class="suggestion">
                        <h4><?php echo __('debug.suggestion'); ?></h4>
                        <p><?php echo $suggestion ?></p>
                    </div>
                <?php endif; ?>
                <div class="stack-trace">
                    <?php foreach ($exceptionChain as $chainIndex => $chainItem): ?>
                        <?php if ($chainIndex > 0): ?>
                            <h3 style="margin-top: 2rem; color: var(--text-muted); font-weight: normal;"><?php echo __('debug.previous_exception'); ?>: <?php echo $chainItem['class']; ?></h3>
                        <?php endif; ?>
                        <?php foreach ($chainItem['trace'] as $index => $trace): ?>
                            <div class="frame <?php echo ($chainIndex === 0 && $index === 0) ? 'active' : ''; ?>">
                                <div class="frame-header">
                                    <div>
                                        <h3>
                                            <span class="collapser-icon"></span>
                                            <?php echo $trace['class'] ?? ''; ?><?php echo $trace['type'] ?? '';; ?><?php echo $trace['function']; ?>
                                        </h3>
                                        <?php if (isset($trace['file'])): ?>
                                            <div class="file-path">
                                                <a href="<?php echo openFileLink($trace['file'], $trace['line']); ?>" title="Open in Editor"><?php echo getRealPath($trace['file']); ?>:<?php echo $trace['line']; ?></a>
                                                <button class="clipboard-btn">Copy</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <span>#<?php echo $index; ?></span>
                                </div>
                                <div class="frame-content">
                                    <?php if (!empty($trace['args'])): ?>
                                        <div class="arguments">
                                            <h4><?php echo __('debug.arguments'); ?></h4>
                                            <?php echo renderVariable($trace['args']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php
                                    $snippetData = isset($trace['file']) ? getCodeSnippet($trace['file'], $trace['line']) : null;
                                    if ($snippetData):
                                    ?>
                                        <pre class="code-viewer"><div data-start-line="<?php echo $snippetData['startLine']; ?>"><?php echo $snippetData['code']; ?></div></pre>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="request" class="tab-content">
                <div class="filter-box">
                    <input type="text" class="filter-input" data-table-id="request-tables" placeholder="Search request data...">
                </div>
                <div id="request-tables">
                    <h3>GET</h3>
                    <?php if (!empty($_GET)): ?>
                        <table><?php foreach ($_GET as $k => $v) echo "<tr><td>" . $k . "</td><td>" . renderVariable($v) . "</td></tr>"; ?></table>
                    <?php else: ?><p><?php echo __('debug.no_data'); ?></p><?php endif; ?>
                    <h3>POST</h3>
                    <?php if (!empty($_POST)): ?>
                        <table><?php foreach ($_POST as $k => $v) echo "<tr><td>" . $k . "</td><td>" . renderVariable($v) . "</td></tr>"; ?></table>
                    <?php else: ?><p><?php echo __('debug.no_data'); ?></p><?php endif; ?>
                    <h3><?php echo __('debug.headers'); ?></h3>
                    <?php $headers = getallheaders();
                    if (!empty($headers)): ?>
                        <table><?php foreach ($headers as $k => $v) echo "<tr><td>" . $k . "</td><td>" . renderVariable($v) . "</td></tr>"; ?></table>
                    <?php else: ?><p><?php echo __('debug.no_data'); ?></p><?php endif; ?>
                </div>
            </div>

            <div id="app" class="tab-content">
                <div class="filter-box">
                    <input type="text" class="filter-input" data-table-id="app-tables" placeholder="Search environment data...">
                </div>
                <div id="app-tables">
                    <h3><?php echo __('debug.session'); ?></h3>
                    <?php if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION)): ?>
                        <table><?php foreach ($_SESSION as $k => $v) echo "<tr><td>" . $k . "</td><td>" . renderVariable($v) . "</td></tr>"; ?></table>
                    <?php else: ?><p><?php echo __('debug.no_data'); ?></p><?php endif; ?>
                    <h3><?php echo __('debug.cookies'); ?></h3>
                    <?php if (!empty($_COOKIE)): ?>
                        <table><?php foreach ($_COOKIE as $k => $v) echo "<tr><td>" . $k . "</td><td>" . renderVariable($v) . "</td></tr>"; ?></table>
                    <?php else: ?><p><?php echo __('debug.no_data'); ?></p><?php endif; ?>
                    <h3><?php echo __('debug.server'); ?></h3>
                    <table><?php foreach ($_SERVER as $k => $v) echo "<tr><td>" . $k . "</td><td>" . renderVariable($v) . "</td></tr>"; ?></table>
                    <h3><?php echo __('debug.php_info'); ?></h3>
                    <table>
                        <tr>
                            <td>PHP Version</td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td>Memory Limit</td>
                            <td><?php echo ini_get('memory_limit'); ?></td>
                        </tr>
                        <tr>
                            <td>Max Execution Time</td>
                            <td><?php echo ini_get('max_execution_time'); ?>s</td>
                        </tr>
                        <tr>
                            <td>Defined Constants</td>
                            <td><?php echo count(get_defined_constants()); ?> constants</td>
                        </tr>
                        <tr>
                            <td>Loaded Modules</td>
                            <td><?php echo implode(', ', get_loaded_extensions()); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    tabContents.forEach(c => c.classList.remove('active'));
                    document.getElementById(tab.dataset.tab).classList.add('active');
                });
            });

            const frameHeaders = document.querySelectorAll('.frame-header');
            frameHeaders.forEach(header => {
                header.addEventListener('click', (e) => {
                    if (e.target.nodeName === "A") {
                        return;
                    }
                    header.parentElement.classList.toggle('active');
                });
            });

            document.body.addEventListener('click', e => {
                if (e.target.classList.contains('collapser')) {
                    e.target.parentElement.classList.toggle('expanded');
                }
            });

            const filterInputs = document.querySelectorAll('.filter-input');
            filterInputs.forEach(input => {
                input.addEventListener('keyup', () => {
                    const searchTerm = input.value.toLowerCase();
                    const tableContainer = document.getElementById(input.dataset.tableId);
                    const rows = tableContainer.querySelectorAll('table tr');
                    rows.forEach(row => {
                        row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
                    });
                });
            });

            const themeToggle = document.getElementById('theme-toggle');
            const currentTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', currentTheme);

            themeToggle.addEventListener('click', () => {
                let newTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
            });

            document.querySelectorAll('.clipboard-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();

                    const frame = btn.closest('.frame');
                    const functionName = frame.querySelector('.frame-header h3').textContent.trim();
                    const filePathElement = frame.querySelector('.file-path a');
                    const fileDetails = filePathElement ? filePathElement.textContent : 'File details not available.';
                    const mainErrorMessage = document.querySelector('.header p').textContent.trim();

                    const highlightedLine = frame.querySelector('.line.highlight code');
                    const codeSnippet = highlightedLine ? highlightedLine.textContent.trim() : 'Code snippet not available.';

                    const textToCopy = `Error:\nMessage: ${mainErrorMessage}\nFunction: ${functionName}\nFile: ${fileDetails}\n\nCode Snippet:\n\`\`\`php\n${codeSnippet}\n\`\`\``;

                    navigator.clipboard.writeText(textToCopy).then(() => {
                        const originalText = btn.textContent;
                        btn.textContent = 'Copied!';
                        setTimeout(() => {
                            btn.textContent = originalText;
                        }, 2000);
                    }).catch(err => {
                        console.error('Copy failed: ', err);
                    });
                });
            });
        });
    </script>
</body>

</html>