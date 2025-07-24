<?php

namespace Pluto\Core;

class Language
{
    private $language;
    private $data;
    private $file;
    public function __construct($language = "en")
    {
        $this->language = $language;
        $this->file = __DIR__ . "/../../storage/languages/$this->language.json";
        $this->data = $this->get();
    }

    public function asJson()
    {
        if (file_exists($this->file)) {
            $lang = file_get_contents($this->file);
        } else {
            $lang = file_get_contents(__DIR__ . "/../../storage/languages/en.json");
        }
        return $lang;
    }

    public function getLanguage()
    {
        return $this->language;
    }
    static function detect()
    {
        return (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : (isset(self::$language) ? self::$language : "en");
    }
    public function setLanguage($language)
    {
        $this->language = $language;
    }
    public function get()
    {
        if (file_exists($this->file)) {
            $lang = json_decode(file_get_contents($this->file));
        } else {
            $lang = json_decode(file_get_contents(__DIR__ . "/../../storage/languages/en.json"));
        }
        return $lang;
    }
    private function replace($params, $s)
    {
        preg_match_all("/\{\}/", $s, $matches);
        foreach ($matches[0] as $i => $match) {
            $s = str_replace($match, $params[$i], $s);
        }
        return $s;
    }

    /**
     * Get the translation for the given key.
     *
     * @param  string  $key
     * @param  array  $params replace {0}, {1}, ...
     * @return string
     */
    public function _($key, ...$params)
    {
        if (isset($this->data->$key)) {
            if (count($params) > 0) {
                $this->data->$key = $this->replace($params, $this->data->$key);
            }
            return $this->data->$key;
        } else {
            return $key;
        }
    }

    public function e($key, ...$params)
    {
        echo $this->_($key, ...$params);
    }
}
