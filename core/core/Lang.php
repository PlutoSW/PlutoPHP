<?php

namespace Pluto;

class Lang
{
    protected static ?self $instance = null;

    protected string $locale;

    protected string $fallbackLocale;

    protected array $loaded = [];

    public function __construct(string $locale, string $fallbackLocale)
    {
        $this->locale = $locale;
        $this->fallbackLocale = $fallbackLocale;
        $this->load($locale);
    }

    /**
     * Get the translation for the given key.
     *
     * @param string $key
     * @param array $replace
     * @param string|null $locale
     * @return string|array
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string|array
    {
        $locale = $locale ?? $this->locale;

        $line = $this->getLine($key, $locale);

        if (!isset($line)) {
            $line = $this->getLine($key, $this->fallbackLocale);
        }

        if (isset($line)) {
            return $this->makeReplacements($line, $replace);
        }
        return $key;
    }

    public function __toString()
    {
        return \json_encode($this->loaded[$this->locale]);
    }

    public function getLocale(): string
    {
        return $this->locale;
    }


    protected function getLine(string $key, string $locale): ?string
    {
        $this->load($locale);
        return $this->loaded[$locale][$key] ?? null;
    }

    protected function load(string $locale): void
    {
        if (isset($this->loaded[$locale])) {
            return;
        }

        $langPath = BASE_PATH . "/core/languages/{$locale}.php";
        if (file_exists($langPath)) {
            $this->loaded[$locale] = require $langPath;
        } else {
            $this->loaded[$locale] = [];
        }
        $appLangPath = BASE_PATH . "/app/languages/{$locale}.php";
        if (file_exists($appLangPath)) {
            $this->loaded[$locale] = array_merge($this->loaded[$locale], require $appLangPath);
        }
    }

    protected function makeReplacements(string $line, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $line = str_replace(':' . $key, $value, $line);
        }
        return $line;
    }

    public static function setInstance(self $instance): void { self::$instance = $instance; }
    public static function getInstance(): ?self { return self::$instance; }
}