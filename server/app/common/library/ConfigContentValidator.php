<?php

namespace app\common\library;

use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
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
            $statements = $parser->parse($content);
            if (count($statements) !== 1 || !$statements[0] instanceof Stmt\Return_ || !$statements[0]->expr) {
                throw new InvalidArgumentException('PHP 配置只能包含一个 return 字面量表达式');
            }
            $this->assertLiteral($statements[0]->expr);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new InvalidArgumentException('PHP 配置语法无效');
        }
    }

    private function assertLiteral(Node $node): void
    {
        if ($node instanceof Scalar\String_ || $node instanceof Scalar\LNumber || $node instanceof Scalar\DNumber) {
            return;
        }
        if ($node instanceof Expr\ConstFetch && in_array(strtolower($node->name->toString()), ['true', 'false', 'null'], true)) {
            return;
        }
        if ($node instanceof Expr\UnaryMinus || $node instanceof Expr\UnaryPlus) {
            $this->assertLiteral($node->expr);
            return;
        }
        if ($node instanceof Expr\Array_) {
            foreach ($node->items as $item) {
                if (!$item instanceof Expr\ArrayItem || !$item->value) {
                    throw new InvalidArgumentException('PHP 配置数组格式无效');
                }
                if ($item->key) {
                    $this->assertLiteral($item->key);
                }
                $this->assertLiteral($item->value);
            }
            return;
        }
        throw new InvalidArgumentException('PHP 配置仅允许静态字面量，不能调用函数或引用变量');
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
