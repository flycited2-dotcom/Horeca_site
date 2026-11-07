<?php

namespace App\View;

use Illuminate\Support\HtmlString;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Тексты из админки — статические страницы и SEO-блоки — хранятся в Markdown (ТЗ §5.5).
 * HTML внутри текста вырезается, небезопасные ссылки (javascript:) не проходят: страница
 * показывает только то, что можно написать разметкой Markdown. Простой текст без разметки
 * становится абзацами.
 */
final class RichText
{
    public static function html(?string $markdown, ?array $articleUrls = null): HtmlString
    {
        $markdown = trim((string) $markdown);

        if ($markdown === '') {
            return new HtmlString('');
        }

        $converter = new GithubFlavoredMarkdownConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        $converter->getEnvironment()->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use ($articleUrls): void {
            $unpublished = [];
            $walker = $event->getDocument()->walker();

            while ($step = $walker->next()) {
                if (! $step->isEntering()) {
                    continue;
                }

                $node = $step->getNode();
                if ($node instanceof Heading && $node->getLevel() === 1) {
                    $node->setLevel(2);
                }

                if ($articleUrls !== null && $node instanceof Link && str_starts_with($node->getUrl(), 'article:')) {
                    $slug = substr($node->getUrl(), 8);
                    if (isset($articleUrls[$slug])) {
                        $node->setUrl($articleUrls[$slug]);
                    } else {
                        $unpublished[] = $node;
                    }
                }
            }

            foreach ($unpublished as $link) {
                foreach ($link->children() as $child) {
                    $link->insertBefore($child);
                }
                $link->detach();
            }
        });

        return new HtmlString((string) $converter->convert($markdown));
    }
}
