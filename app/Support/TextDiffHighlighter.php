<?php

namespace App\Support;

/**
 * Presentation-only OLD → NEW word diff for editorial comparison.
 *
 * Compares two immutable editorial texts and returns HTML with changed
 * portions highlighted (removed = <del>, added = <ins>). Pure view helper —
 * no storage, no mutation of the underlying versions. Word-level LCS with a
 * hard size cap; oversized inputs fall back to side-by-side full text.
 */
final class TextDiffHighlighter
{
    private const MAX_WORDS = 1200;

    /**
     * @return array{old_html: string, new_html: string}
     */
    public static function diff(string $old, string $new): array
    {
        $old = trim((string) preg_replace('/\r\n/', "\n", $old));
        $new = trim((string) preg_replace('/\r\n/', "\n", $new));

        if ($old === $new) {
            return ['old_html' => e($old), 'new_html' => e($new)];
        }

        $oldWords = self::tokenize($old);
        $newWords = self::tokenize($new);

        if (count($oldWords) > self::MAX_WORDS || count($newWords) > self::MAX_WORDS) {
            return ['old_html' => e($old), 'new_html' => e($new)];
        }

        $ops = self::operations($oldWords, $newWords);

        $oldHtml = '';
        $newHtml = '';
        foreach ($ops as [$op, $word]) {
            $safe = e($word);
            if ($op === 'same') {
                $oldHtml .= $safe;
                $newHtml .= $safe;
            } elseif ($op === 'removed') {
                $oldHtml .= '<del class="jk-diff-del">'.$safe.'</del>';
            } else {
                $newHtml .= '<ins class="jk-diff-ins">'.$safe.'</ins>';
            }
        }

        return ['old_html' => $oldHtml, 'new_html' => $newHtml];
    }

    /**
     * Tokenize into words while keeping whitespace attached so the joined
     * output reads as the original text.
     *
     * @return list<string>
     */
    private static function tokenize(string $text): array
    {
        $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        return array_values(array_filter($parts, fn (string $p): bool => $p !== ''));
    }

    /**
     * Classic LCS backtrace producing ['same'|'removed'|'added', word] ops.
     *
     * @param  list<string>  $oldWords
     * @param  list<string>  $newWords
     * @return list<array{string, string}>
     */
    private static function operations(array $oldWords, array $newWords): array
    {
        $n = count($oldWords);
        $m = count($newWords);

        // LCS length table (ints; sizes are capped).
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $oldWords[$i] === $newWords[$j]
                    ? $lcs[$i + 1][$j + 1] + 1
                    : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $ops = [];
        $i = 0;
        $j = 0;
        while ($i < $n && $j < $m) {
            if ($oldWords[$i] === $newWords[$j]) {
                $ops[] = ['same', $oldWords[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $ops[] = ['removed', $oldWords[$i]];
                $i++;
            } else {
                $ops[] = ['added', $newWords[$j]];
                $j++;
            }
        }
        while ($i < $n) {
            $ops[] = ['removed', $oldWords[$i]];
            $i++;
        }
        while ($j < $m) {
            $ops[] = ['added', $newWords[$j]];
            $j++;
        }

        return $ops;
    }
}
