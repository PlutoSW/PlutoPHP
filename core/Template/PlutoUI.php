<?php

namespace Pluto\Template;

class PlutoUI
{
    private \Pluto\Response $response;

    public function __construct($request, $response)
    {
        $this->response = $response;
    }

    public function core_styles($style = null)
    {
        if ($style) {
            if (str_contains($style, 'layout/')) {
                $content = file_get_contents(BASE_PATH . '/core/assets/css/' . $style);
            } else {
                $content = file_get_contents(BASE_PATH . '/core/assets/css/components/' . $style);
            }
        } else {
            $content = file_get_contents(BASE_PATH . '/core/assets/css/Pluto.css');
            $content .= file_get_contents(BASE_PATH . '/core/assets/css/layout/select.css');
            $content .= file_get_contents(BASE_PATH . '/core/assets/css/layout/button.css');
            $content .= file_get_contents(BASE_PATH . '/core/assets/css/layout/layout.css');
        }
        if (\getenv('MINIFY') === 'true') {
            $content = preg_replace(['/\/\*(.|\s)*?\*\//', '/\s*([{}|:;,])\s*/', '/\s\s+/', '/`/'], ['', '$1', ' ', '\\`'], $content);
        }
        return $this->response->css($content);
    }

    public function core_scripts()
    {
        $links = glob(BASE_PATH . '/core/assets/js/components/*.js');
        $content = file_get_contents(BASE_PATH . '/core/assets/js/Pluto.min.js');
        $content .= file_get_contents(BASE_PATH . '/core/assets/js/prototype.min.js');

        $cachedCss = [];

        foreach ($links as $link) {
            $jsContent = file_get_contents($link);

            $jsContent = preg_replace_callback(
                '/styles\(\)\s*\{\s*return\s*\[\s*"([^"]+)"\s*\]\s*;\s*\}/s',
                function ($matches) use (&$cachedCss) {
                    if (str_contains($matches[1], '/layout/')) {
                        $cssFileName = basename($matches[1]);
                        $cssFilePath = BASE_PATH . '/core/assets/css/layout/' . $cssFileName;
                    } else {
                        $cssFileName = basename($matches[1]);
                        $cssFilePath = BASE_PATH . '/core/assets/css/components/' . $cssFileName;
                    }

                    if (!isset($cachedCss[$cssFilePath])) {
                        if (file_exists($cssFilePath)) {
                            $cssContent = file_get_contents($cssFilePath);
                            $cachedCss[$cssFilePath] = preg_replace(['/\/\*(.|\s)*?\*\//', '/\s*([{}|:;,])\s*/', '/\s\s+/', '/`/'], ['', '$1', ' ', '\\`'], $cssContent);
                        }
                    }
                    return "styles() { return css`" . trim($cachedCss[$cssFilePath]) . "`; }";
                },
                $jsContent
            );
            $content .= $jsContent;
        }
        if (\getenv('MINIFY') === 'true') {
            $content = Minifier\JSMinifier::minifyCode($content);
        }
        return $this->response->script("document.addEventListener(\"DOMContentLoaded\",()=>{{$content}});");
    }

    public function styles($style)
    {
        $content = file_get_contents(BASE_PATH . '/app/assets/css/' . $style);
        if (\getenv('MINIFY') === 'true') {
            $content = preg_replace('/\/\*(.|\s)*?\*\//', '', $content);
            $content = preg_replace('/\s*([{}|:;,])\s*/', '$1', $content);
            $content = preg_replace('/\s\s+/', ' ', $content);
        }
        return $this->response->css($content);
    }

    public function scripts($script)
    {
        $content = file_get_contents(BASE_PATH . '/app/assets/js/' . $script);
        if (\getenv('MINIFY') === 'true') {
            $content = Minifier\JSMinifier::minifyCode($content);
        }
        return $this->response->script($content);
    }
}
