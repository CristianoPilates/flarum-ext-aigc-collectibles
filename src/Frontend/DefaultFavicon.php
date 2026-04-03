<?php

namespace Donk\AigcCollectibles\Frontend;

use Flarum\Frontend\Document;
use Psr\Http\Message\ServerRequestInterface;

class DefaultFavicon
{
    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="16" fill="#111827"/><path d="M16 24l8-10h16l8 10-16 26Z" fill="#f59e0b"/><path d="M24 14l8 10 8-10" fill="none" stroke="#fde68a" stroke-linecap="round" stroke-linejoin="round" stroke-width="4"/></svg>';

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $faviconUrl = $document->getForumApiDocument()['data']['attributes']['faviconUrl'] ?? null;

        if (is_string($faviconUrl) && $faviconUrl !== '') {
            return;
        }

        $href = 'data:image/svg+xml,' . rawurlencode(self::SVG);
        $escapedHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');

        $document->head['favicon'] = '<link rel="icon" href="' . $escapedHref . '" type="image/svg+xml">';
        $document->head['shortcut-icon'] = '<link rel="shortcut icon" href="' . $escapedHref . '" type="image/svg+xml">';
    }
}
