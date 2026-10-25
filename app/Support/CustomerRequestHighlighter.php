<?php

namespace App\Support;

/**
 * Staff-facing presentation of a customer's free-text request.
 *
 * A revision request stores request_text, not a field-by-field replacement.
 * This panel shows that request. It does not invent a word-level diff.
 */
class CustomerRequestHighlighter
{
    public static function panel(?string $requestText): string
    {
        $text = trim((string) $requestText);
        if ($text === '') {
            return '—';
        }

        return '<div class="jk-customer-request" style="background:#fff3bf;border:1px solid #e6c200;padding:10px 12px;border-radius:8px">'
            .'<strong>Customer request</strong>'
            .'<p style="margin:8px 0 0;white-space:pre-wrap">'.e($text).'</p>'
            .'<p style="margin:8px 0 0;color:#5c5340">No replacement text was supplied, so individual words are not marked as customer edits. Staff or AI changes appear in the separate red and green comparison.</p>'
            .'</div>';
    }
}
