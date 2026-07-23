<?php

namespace app\common\library;

use InvalidArgumentException;
use PhpParser\ParserFactory;
use Symfony\Component\Yaml\Yaml;

class ConfigContentValidator
{
    private const FORMATS = ['php', 'json', 'yaml', 'yml', 'ini', 'txt'];

    public function validate(string $format, string $content): void
    {
        if (!in_array($format, self::FORMATS, true)) {
            throw new InvalidArgumentException('不支持的配置格式');
        }

        if (strlen($content) > (int) config('config-center.max_content_bytes')) {
            throw new InvalidArgumentException('配置内容超过大小限制');
        }

        match ($format) {
            'php' => $this->validatePhp($content),
            'json' => $this->validateJson($content),
            'yaml', 'yml' => $this->validateYaml($content),
            'ini' => $this->validateIni($content),
            default => null,
        };
    }

    private function validatePhp(string $content): void
    {
        try {
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            $parser->parse($content);
        } catch (\Throwable) {
            throw new InvalidArgumentException('PHP 配置语法无效');
        }
    }

    private function validateJson(string $content): void
    {
        try {
            json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new InvalidArgumentException('JSON 配置格式无效');
        }
    }

    private function validateYaml(string $content): void
    {
        try {
            Yaml::parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (\Throwable) {
            throw new InvalidArgumentException('YAML 配置格式无效');
        }
    }

    private function validateIni(string $content): void
    {
        if (parse_ini_string($content, true, INI_SCANNER_RAW) === false) {
            throw new InvalidArgumentException('INI 配置格式无效');
        }
    }
}
