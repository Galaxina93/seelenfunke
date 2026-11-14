<?php

namespace App\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

class SafeHtmlAndSvgSanitizer
{
    private const ALLOWED_HTML_ELEMENTS = [
        'p', 'br', 'hr',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'mark', 'small', 'sub', 'sup',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'blockquote', 'pre', 'code', 'kbd', 'samp',
        'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
        'div', 'span', 'section', 'article', 'header', 'footer', 'aside', 'details', 'summary',
    ];

    private const DROP_CONTENT_HTML_ELEMENTS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
        'svg', 'math', 'form', 'input', 'button', 'select', 'textarea',
        'base', 'meta', 'link', 'head', 'title', 'noscript', 'template',
    ];

    private const ALLOWED_HTML_GLOBAL_ATTRS = [
        'class', 'id', 'title', 'lang', 'dir', 'role', 'colspan', 'rowspan', 'align', 'width', 'height', 'style',
    ];

    private const ALLOWED_HTML_TAG_ATTRS = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'loading', 'decoding'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'ol' => ['start', 'type'],
        'details' => ['open'],
    ];

    private const ALLOWED_SVG_ELEMENTS = [
        'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'defs', 'lineargradient', 'radialgradient', 'stop',
        'clippath', 'mask', 'pattern', 'symbol', 'title', 'desc',
    ];

    private const ALLOWED_SVG_ATTRS = [
        'id', 'class', 'xmlns', 'viewbox', 'preserveaspectratio',
        'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2',
        'cx', 'cy', 'r', 'rx', 'ry', 'dx', 'dy',
        'd', 'points', 'transform', 'fill', 'fill-opacity', 'fill-rule',
        'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity',
        'opacity', 'clip-path', 'clip-rule', 'mask',
        'gradientunits', 'gradienttransform', 'spreadmethod', 'offset', 'stop-color', 'stop-opacity',
        'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'dominant-baseline', 'letter-spacing',
        'display', 'visibility', 'color', 'vector-effect', 'shape-rendering', 'style',
    ];

    /**
     * Bereinigt Rich-HTML per striktem DOMDocument-Allowlisting gegen Stored XSS, mXSS und Event-Handler.
     */
    public static function sanitizeHtml(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $html = str_replace("\0", '', $html);

        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="__sf_sanitize_root__">'
            . $html
            . '</div></body></html>';

        $loaded = @$dom->loadHTML($wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $root = $dom->getElementById('__sf_sanitize_root__');
        if (!$root) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        self::cleanHtmlNodeChildren($dom, $root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }

    /**
     * Bereinigt SVG-Markup per striktem DOMDocument-Allowlisting ohne Entity-Expansion (kein LIBXML_NOENT).
     * Gibt bei invalidem XML oder fehlendem <svg>-Root einen leeren String zurück.
     */
    public static function sanitizeSvg(?string $svg): string
    {
        $svg = trim((string) $svg);
        if ($svg === '') {
            return '';
        }

        $svg = str_replace("\0", '', $svg);

        // Entferne XML-Deklaration sowie DOCTYPE/ENTITY-Blöcke vollständig vor dem Parser (Schutz gegen XXE / Billion Laughs)
        $svg = preg_replace('/<\?xml\b[^>]*\?>/is', '', $svg) ?? $svg;
        if (preg_match('/<!\s*(DOCTYPE|ENTITY)\b/i', $svg)) {
            $svg = preg_replace('/<!DOCTYPE\b[^>\[]*(?:\[[\s\S]*?\])?\s*>/i', '', $svg) ?? '';
            $svg = preg_replace('/<!ENTITY\b[^>]*>/i', '', $svg) ?? '';
            if (preg_match('/<!\s*(DOCTYPE|ENTITY)\b/i', $svg)) {
                return '';
            }
        }

        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        // Wichtig: NIEMALS LIBXML_NOENT übergeben!
        $loaded = @$dom->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        if (!$loaded) {
            // Fallback für fehlende xmlns:xlink-Deklaration oder unquotierte Attribute (z.B. <svg onload=alert(1)>)
            $repairedSvg = $svg;
            if (stripos($repairedSvg, 'xlink:') !== false && stripos($repairedSvg, 'xmlns:xlink') === false) {
                $repairedSvg = preg_replace('/<svg\b/i', '<svg xmlns:xlink="http://www.w3.org/1999/xlink"', $repairedSvg, 1) ?? $repairedSvg;
            }
            $repairedSvg = preg_replace_callback('/<([a-zA-Z0-9_:\-]+)([^>]*)>/', function (array $m): string {
                $attrs = preg_replace('/(\s+[a-zA-Z_:][a-zA-Z0-9_.\-:]*)=([^\s"\'=<>`\/]+)/', '$1="$2"', $m[2]) ?? $m[2];
                return '<' . $m[1] . $attrs . '>';
            }, $repairedSvg) ?? $repairedSvg;

            $dom = new DOMDocument('1.0', 'UTF-8');
            $loaded = @$dom->loadXML($repairedSvg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded || $dom->doctype !== null || !$dom->documentElement) {
            return '';
        }

        if (strtolower($dom->documentElement->localName ?? $dom->documentElement->nodeName) !== 'svg') {
            return '';
        }

        self::cleanSvgElement($dom->documentElement);

        $cleanXml = $dom->saveXML($dom->documentElement);
        return is_string($cleanXml) ? trim($cleanXml) : '';
    }

    private static function cleanHtmlNodeChildren(DOMDocument $dom, DOMNode $parent): void
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $node) {
            if ($node->nodeType === XML_COMMENT_NODE || $node->nodeType === XML_PI_NODE) {
                $parent->removeChild($node);
                continue;
            }

            if ($node instanceof DOMElement) {
                $tag = strtolower($node->localName ?: $node->nodeName);

                if (in_array($tag, self::DROP_CONTENT_HTML_ELEMENTS, true) || str_contains($node->nodeName, ':')) {
                    $parent->removeChild($node);
                    continue;
                }

                if (!in_array($tag, self::ALLOWED_HTML_ELEMENTS, true)) {
                    // Unbekanntes Tag entpacken: Kinder bereinigen und an Elternstelle übernehmen
                    self::cleanHtmlNodeChildren($dom, $node);
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                    continue;
                }

                self::cleanHtmlAttributes($node, $tag);
                self::cleanHtmlNodeChildren($dom, $node);
            }
        }
    }

    private static function cleanHtmlAttributes(DOMElement $el, string $tag): void
    {
        $allowedForTag = array_merge(
            self::ALLOWED_HTML_GLOBAL_ATTRS,
            self::ALLOWED_HTML_TAG_ATTRS[$tag] ?? []
        );

        $toRemove = [];
        $hasBlankTarget = false;

        /** @var DOMAttr $attr */
        foreach ($el->attributes as $attr) {
            $name = strtolower($attr->nodeName);
            $localName = strtolower($attr->localName ?: $attr->nodeName);

            if (
                str_starts_with($name, 'on')
                || str_starts_with($localName, 'on')
                || str_contains($name, ':')
                || (!in_array($name, $allowedForTag, true) && !str_starts_with($name, 'aria-'))
            ) {
                $toRemove[] = $attr->nodeName;
                continue;
            }

            $val = $attr->nodeValue ?? '';

            if ($name === 'href' || $name === 'src') {
                $allowDataImg = ($tag === 'img' && $name === 'src');
                if (!self::isSafeUrlValue($val, $allowDataImg)) {
                    $toRemove[] = $attr->nodeName;
                    continue;
                }
            } elseif ($name === 'style') {
                $cleanStyle = self::sanitizeInlineCss($val, false);
                if ($cleanStyle === '') {
                    $toRemove[] = $attr->nodeName;
                } else {
                    $el->setAttribute('style', $cleanStyle);
                }
            } elseif ($name === 'target') {
                if (!in_array(strtolower(trim($val)), ['_blank', '_self'], true)) {
                    $toRemove[] = $attr->nodeName;
                } elseif (strtolower(trim($val)) === '_blank') {
                    $hasBlankTarget = true;
                }
            }
        }

        foreach ($toRemove as $attrName) {
            $el->removeAttribute($attrName);
        }

        if ($tag === 'a' && $hasBlankTarget) {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function cleanSvgElement(DOMElement $el): void
    {
        $attrsToRemove = [];
        /** @var DOMAttr $attr */
        foreach ($el->attributes as $attr) {
            $name = strtolower($attr->nodeName);
            $localName = strtolower($attr->localName ?: $attr->nodeName);
            $val = $attr->nodeValue ?? '';

            if (
                str_starts_with($name, 'on')
                || str_starts_with($localName, 'on')
                || ($name !== 'xmlns' && str_contains($name, ':'))
                || !in_array($name, self::ALLOWED_SVG_ATTRS, true)
            ) {
                $attrsToRemove[] = $attr->nodeName;
                continue;
            }

            if ($name === 'style') {
                $cleanStyle = self::sanitizeInlineCss($val, true);
                if ($cleanStyle === '') {
                    $attrsToRemove[] = $attr->nodeName;
                } else {
                    $el->setAttribute('style', $cleanStyle);
                }
            } elseif (preg_match('/(javascript\s*:|data\s*:|vbscript\s*:|url\s*\(\s*[\'"]?\s*(?:https?:|data:|javascript:))/i', html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                // Erlaubt lokale Fragment-Referenzen wie fill="url(#grad1)", blockiert aber externe/skriptbasierte URLs
                $attrsToRemove[] = $attr->nodeName;
            }
        }

        foreach ($attrsToRemove as $attrName) {
            $el->removeAttribute($attrName);
        }

        $children = [];
        foreach ($el->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $el->removeChild($child);
                continue;
            }

            if ($child instanceof DOMElement) {
                $tag = strtolower($child->localName ?: $child->nodeName);
                // Bei <a>-Wrappern in SVGs den Link selbst verwerfen, aber sichere Geometrie-Kinder (z.B. <path>) erhalten
                if ($tag === 'a' && !str_contains($child->nodeName, ':')) {
                    self::cleanSvgElement($child);
                    while ($child->firstChild) {
                        $el->insertBefore($child->firstChild, $child);
                    }
                    $el->removeChild($child);
                    continue;
                }
                // Blockiere fremde XML-Namespaces (z.B. xhtml:script) und nicht erlaubte SVG-Elemente wie <script>, <foreignObject>, <use>, <image>, <animate>, <set>
                if (str_contains($child->nodeName, ':') || !in_array($tag, self::ALLOWED_SVG_ELEMENTS, true)) {
                    $el->removeChild($child);
                    continue;
                }

                self::cleanSvgElement($child);
            }
        }
    }

    private static function isSafeUrlValue(string $rawUrl, bool $allowSafeRasterDataUri = false): bool
    {
        $decoded = html_entity_decode($rawUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/[\x00-\x20\x7F]+/', '', $decoded) ?? '';
        if ($normalized === '') {
            return false;
        }

        $lower = strtolower($normalized);

        if ($allowSafeRasterDataUri && preg_match('#^data:image/(png|jpe?g|webp|gif);base64,[a-z0-9+/=]+$#i', $normalized)) {
            return true;
        }

        if (
            str_starts_with($lower, 'http://')
            || str_starts_with($lower, 'https://')
            || str_starts_with($lower, 'mailto:')
            || str_starts_with($lower, 'tel:')
            || (str_starts_with($lower, '/') && !str_starts_with($lower, '//'))
            || str_starts_with($lower, '#')
        ) {
            return true;
        }

        return false;
    }

    private static function sanitizeInlineCss(string $css, bool $allowFragmentUrl = false): string
    {
        $decoded = html_entity_decode($css, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = preg_replace('/\/\*.*?\*\//s', '', $decoded) ?? '';
        $clean = preg_replace('/[\x00-\x1F\x7F]/', '', $clean) ?? '';

        if (preg_match('/(expression\s*\(|javascript\s*:|vbscript\s*:|@import|-moz-binding|behavior\s*:)/i', $clean)) {
            return '';
        }

        if ($allowFragmentUrl) {
            if (preg_match('/url\s*\(\s*[\'"]?\s*(?!#[A-Za-z0-9_-]+\s*[\'"]?\))/i', $clean)) {
                return '';
            }
        } elseif (preg_match('/url\s*\(/i', $clean)) {
            return '';
        }

        return trim($clean);
    }
}
