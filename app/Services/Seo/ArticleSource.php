<?php

namespace App\Services\Seo;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

final class ArticleSource
{
    /**
     * The editorial standard uses top-level YAML fields, double-quoted strings,
     * flow lists of quoted strings, an ISO date and an integer reading time.
     */
    public static function parse(string $source, string $filename): array
    {
        $source = preg_replace('/^\xEF\xBB\xBF/', '', str_replace(["\r\n", "\r"], "\n", $source));
        if (! preg_match('/\A---[ \t]*\n(.*?)\n---[ \t]*\n(.*)\z/s', $source, $parts)) {
            throw new InvalidArgumentException('Требуется YAML-шапка между двумя строками «---».');
        }

        $data = [];
        foreach (explode("\n", $parts[1]) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (! preg_match('/^([a-z][a-z0-9_]*):[ \t]+(.+)$/', $line, $field) || array_key_exists($field[1], $data)) {
                throw new InvalidArgumentException('Некорректное или повторное поле YAML: '.$line);
            }

            $value = trim($field[2]);
            try {
                $data[$field[1]] = match (true) {
                    str_starts_with($value, '"'), str_starts_with($value, '[') => json_decode($value, true, flags: JSON_THROW_ON_ERROR),
                    ctype_digit($value) => (int) $value,
                    preg_match('/^[a-z0-9.-]+$/D', $value) === 1 => $value,
                    default => throw new InvalidArgumentException('Строковые значения должны быть в двойных кавычках.'),
                };
            } catch (JsonException) {
                throw new InvalidArgumentException('Проверьте кавычки или формат списка в поле '.$field[1].'.');
            }
        }

        foreach (['site', 'slug', 'title', 'description', 'h1', 'date', 'category', 'main_query', 'cta'] as $key) {
            if (! is_string($data[$key] ?? null) || trim($data[$key]) === '') {
                throw new InvalidArgumentException('Требуется строковое поле '.$key.'.');
            }
            $data[$key] = trim($data[$key]);
        }

        if ($data['site'] !== 'gastrosnab.ru' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $data['slug']) || $filename !== $data['slug'].'.md') {
            throw new InvalidArgumentException('Проверьте домен gastrosnab.ru, slug и имя файла.');
        }

        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data['date'])
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $data['date'])
            : false;
        if (! $date || $date->format('Y-m-d') !== $data['date']) {
            throw new InvalidArgumentException('Укажите действительную дату в формате ГГГГ-ММ-ДД.');
        }

        if (! is_int($data['read_time'] ?? null) || $data['read_time'] < 1) {
            throw new InvalidArgumentException('Время чтения должно быть целым положительным числом.');
        }

        foreach (['queries', 'links'] as $key) {
            if (! is_array($data[$key] ?? null) || ! array_is_list($data[$key]) || $data[$key] === []) {
                throw new InvalidArgumentException('Требуется список строк в поле '.$key.'.');
            }
            foreach ($data[$key] as $value) {
                if (! is_string($value) || trim($value) === '') {
                    throw new InvalidArgumentException('Требуется непустая строка в поле '.$key.'.');
                }
            }
        }

        foreach ($data['links'] as $link) {
            if (! preg_match('~^/(?:catalog|brands)(?:/[a-z0-9-]+)?/?$|^/product/[a-z0-9-]+/?$~D', $link)) {
                throw new InvalidArgumentException('Используйте локальные пути каталога, товаров или брендов в links.');
            }
        }

        $data['body'] = trim((string) preg_replace('/\[\[ФОТО:[^\]\r\n]*\]\]/u', '', $parts[2]));
        if ($data['body'] === '' || ! mb_check_encoding($source, 'UTF-8')) {
            throw new InvalidArgumentException('Требуется текст статьи в UTF-8.');
        }

        return $data;
    }
}
