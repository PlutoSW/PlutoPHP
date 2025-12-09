<?php

namespace Pluto\Template;

use Pluto\Template\Minifier\JSMinifier;

class Minifier
{
    public static function minifyHtml(string $html): string
    {
        $html = preg_replace_callback('/<script(?![^>]*src=)([^>]*)>(.*?)<\/script>/is', function ($matches) {
            $attributes = $matches[1];
            $js = $matches[2];
            if ($js) {
                $js = JSMinifier::minifyCode($js);
            }
            return '<script' . $attributes . '>' . $js . '</script>';
        }, $html);
        $html = preg_replace('/<!--(.|\s)*?-->/', '', $html);

        $html = preg_replace('/(?<=>)\s+|\s+(?=<)/', '', $html);
        $html = preg_replace('/\s\s+/', ' ', $html);
        $html = str_replace(["\n", "\r", "\t"], '', $html);

        $html = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/is', function ($matches) {
            $css = $matches[1];
            $css = preg_replace('/\/\*(.|\s)*?\*\//', '', $css);
            $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);
            $css = preg_replace('/\s\s+/', ' ', $css);
            return '<style>' . trim($css) . '</style>';
        }, $html);

        return trim($html);
    }
}
