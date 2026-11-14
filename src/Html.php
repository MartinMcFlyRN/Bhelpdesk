<?php
declare(strict_types=1);

namespace Bhelpdesk;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Whitelist HTML sanitizer for content written with the WYSIWYG editor
 * (operator replies, internal notes, signatures, canned responses).
 *
 * Everything not explicitly allowed is removed: scripts, styles, event
 * handlers, iframes, forms, javascript:/data: URLs, foreign images.
 * Disallowed elements are unwrapped (their text is kept), dangerous ones
 * (script, style, iframe, object...) are dropped with their content.
 */
final class Html
{
    /** tag => allowed attributes */
    private const TAGS = [
        'p' => ['class'], 'br' => [], 'div' => ['class'],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [],
        'span' => ['class'],
        'h1' => ['class'], 'h2' => ['class'], 'h3' => ['class'],
        'ul' => ['class'], 'ol' => ['class'], 'li' => ['class'],
        'blockquote' => ['class'], 'pre' => ['class'], 'code' => [],
        'a' => ['href'],
        'img' => ['src', 'alt', 'width', 'height'],
        'hr' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'td' => ['colspan', 'rowspan'], 'th' => ['colspan', 'rowspan'],
    ];

    /** dropped together with everything inside */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea',
        'svg', 'math', 'link', 'meta', 'base', 'frame', 'frameset', 'noscript', 'template', 'audio', 'video', 'canvas', 'head', 'title'];

    /** only Quill formatting classes survive */
    private const CLASS_RE = '/^ql-(align-(center|right|justify)|indent-[1-8]|size-(small|large|huge)|direction-rtl|code-block|syntax)$/';

    /** inline images must come from our own media endpoint */
    public const MEDIA_RE = '#^(?:https?://[^/\s]+)?/customer/media/[a-f0-9]{32}\.(?:png|jpe?g|gif|webp)$#i';

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        // the XML declaration forces UTF-8; LIBXML_NONET avoids any network access
        $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="__root">' . $html . '</div></body></html>', LIBXML_NONET | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__root');
        if (!$root) {
            return '';
        }
        self::clean($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private static function clean(DOMNode $node): void
    {
        // iterate on a copy: the list changes while we remove/unwrap
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue; // text
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            self::clean($child);
            if (!isset(self::TAGS[$tag])) {
                // unknown element: keep its (already cleaned) content
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            self::cleanAttributes($child, $tag);
            if ($tag === 'img' && !$child->hasAttribute('src')) {
                $node->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = self::TAGS[$tag];
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim($attr->value);
            $keep = in_array($name, $allowed, true);
            if ($keep && $name === 'class') {
                $classes = array_filter(preg_split('/\s+/', $value) ?: [], static fn ($c) => preg_match(self::CLASS_RE, $c));
                $value = implode(' ', $classes);
                $keep = $value !== '';
            } elseif ($keep && $name === 'href') {
                $keep = (bool) preg_match('#^(https?://|mailto:|tel:)#i', $value) && !preg_match('/[\x00-\x20]/', $value);
            } elseif ($keep && $name === 'src') {
                $keep = (bool) preg_match(self::MEDIA_RE, $value);
            } elseif ($keep && in_array($name, ['width', 'height', 'colspan', 'rowspan'], true)) {
                $keep = ctype_digit($value) && (int) $value > 0 && (int) $value <= 2000;
            } elseif ($keep && $name === 'alt') {
                $value = mb_substr($value, 0, 200);
            }
            $el->removeAttribute($attr->name);
            if ($keep) {
                $el->setAttribute($name, $value);
            }
        }
        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    /** Plain text version (emptiness checks, previews, future text emails) */
    public static function toText(string $html): string
    {
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|div|li|h[1-6]|blockquote|pre|tr)>#i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /** True when the HTML has no text and no image */
    public static function isEmpty(string $html): bool
    {
        return trim(str_replace("\u{00A0}", ' ', self::toText($html))) === '' && stripos($html, '<img') === false;
    }

    /** Plain text -> HTML paragraphs (to show old text content inside the editor) */
    public static function fromText(string $text): string
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        if ($text === '') {
            return '';
        }
        $paras = preg_split("/\n{2,}/", $text) ?: [];
        return implode('', array_map(static fn ($p) => '<p>' . nl2br(e($p), false) . '</p>', $paras));
    }

    /** Message body ready for output, whatever its format */
    public static function body(array $m): string
    {
        return ($m['body_format'] ?? 'text') === 'html'
            ? '<div class="rich">' . self::sanitize((string) $m['body']) . '</div>'
            : nl2br_e((string) $m['body']);
    }
}
