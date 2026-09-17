<?php

namespace App\Support;

/**
 * Normalises AI editorial fields to plain text.
 *
 * Contract: AI returns JSON with plain-text title/summary/body (no HTML).
 * Storage is plain text. Filament TextEntry / future Blade {{ }} escape on render.
 * Do not store or render raw HTML from the model.
 */
final class EditorialPlainTextSanitizer
{
    public function sanitizeTitle(string $value): string
    {
        return mb_substr($this->toPlainText($value), 0, 500);
    }

    public function sanitizeSummary(string $value): string
    {
        return mb_substr($this->toPlainText($value), 0, 2000);
    }

    public function sanitizeBody(string $value): string
    {
        return $this->toPlainText($value);
    }

    public function sanitizeClaimExcerpt(string $value): string
    {
        return mb_substr($this->toPlainText($value), 0, 1000);
    }

    private function toPlainText(string $value): string
    {
        // Remove null bytes and normalise to text-only while keeping paragraph breaks.
        $value = str_replace("\0", '', $value);
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        // Collapse runs of spaces/tabs but keep newlines (editorial paragraph structure).
        $value = preg_replace('/[^\S\n]+/u', ' ', $value) ?? $value;
        $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

        return trim($value);
    }
}
