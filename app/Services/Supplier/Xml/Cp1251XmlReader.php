<?php

namespace App\Services\Supplier\Xml;

use App\Services\Supplier\Exceptions\FeedReadException;
use DOMDocument;
use DOMElement;
use Generator;
use XMLReader;

/**
 * Streams elements of a large XML feed without loading the whole file.
 *
 * libxml converts the encoding declared in the prolog (windows-1251 for the supplier feeds)
 * into UTF-8. External entities and DTDs are never loaded (TZ §15.12). Elements are matched
 * by local name, so namespaces do not matter.
 */
final class Cp1251XmlReader
{
    /**
     * A fresh DOM document is used after this many elements to keep memory flat.
     */
    private const int ELEMENTS_PER_DOCUMENT = 1000;

    /**
     * @param  list<string>  $localNames
     * @return Generator<int, DOMElement>
     *
     * @throws FeedReadException
     */
    public function elements(string $path, array $localNames): Generator
    {
        $previousErrorMode = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $reader = @XMLReader::open($path, null, LIBXML_NONET | LIBXML_COMPACT);

        if (! $reader instanceof XMLReader) {
            libxml_use_internal_errors($previousErrorMode);

            throw new FeedReadException(__('import.errors.cannot_open'));
        }

        $document = new DOMDocument;
        $expanded = 0;

        try {
            // Warnings of a broken file are turned into our own error below: a manager
            // must see why the feed was refused, not a PHP notice.
            $moved = @$reader->read();

            while ($moved) {
                if ($reader->nodeType === XMLReader::ELEMENT && in_array($reader->localName, $localNames, true)) {
                    if (++$expanded % self::ELEMENTS_PER_DOCUMENT === 0) {
                        $document = new DOMDocument;
                    }

                    $node = @$reader->expand($document);

                    if (! $node instanceof DOMElement) {
                        $this->throwParseError();

                        throw new FeedReadException(__('import.errors.unreadable_element'));
                    }

                    yield $node;

                    $moved = @$reader->next();

                    continue;
                }

                $moved = @$reader->read();
            }

            $this->throwParseError();
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    /**
     * Trimmed text of the direct child elements: local name => text. Empty elements give "".
     *
     * @return array<string, string>
     */
    public static function childTexts(DOMElement $element): array
    {
        $texts = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && ! array_key_exists($child->localName, $texts)) {
                $texts[$child->localName] = trim($child->textContent);
            }
        }

        return $texts;
    }

    /**
     * All descendant elements with the given local name.
     *
     * @return list<DOMElement>
     */
    public static function descendants(DOMElement $element, string $localName): array
    {
        $found = [];

        foreach ($element->getElementsByTagNameNS('*', $localName) as $node) {
            $found[] = $node;
        }

        return $found;
    }

    /**
     * Fails when UTF-8 text was decoded as a single-byte encoding ("Ð¥Ð¾Ð»" instead of "Хол").
     *
     * @throws FeedReadException
     */
    public static function assertReadable(string $text): void
    {
        if (preg_match('/[ÐÑ][\x{0080}-\x{00BF}]/u', $text, $match) === 1) {
            throw new FeedReadException(__('import.errors.mojibake', ['sample' => mb_substr($text, 0, 40)]));
        }
    }

    /**
     * @throws FeedReadException
     */
    private function throwParseError(): void
    {
        foreach (libxml_get_errors() as $error) {
            if ($error->level >= LIBXML_ERR_ERROR) {
                throw new FeedReadException(__('import.errors.malformed', [
                    'message' => trim($error->message),
                    'line' => $error->line,
                ]));
            }
        }
    }
}
