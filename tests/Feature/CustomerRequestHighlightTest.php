<?php

namespace Tests\Feature;

use App\Support\CustomerRequestHighlighter;
use App\Support\TextDiffHighlighter;
use Tests\TestCase;

class CustomerRequestHighlightTest extends TestCase
{
    public function test_a_free_text_customer_request_is_shown_without_a_manufactured_diff(): void
    {
        $html = CustomerRequestHighlighter::panel("Please correct the year to 2019.\n<script>alert(1)</script> മലയാളം");

        $this->assertStringContainsString('Please correct the year to 2019.', $html);
        $this->assertStringContainsString('മലയാളം', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('No replacement text was supplied', $html);
        $this->assertStringNotContainsString('jk-diff-del', $html);
        $this->assertSame('—', CustomerRequestHighlighter::panel('   '));
    }

    public function test_maintenance_diff_still_marks_added_and_removed_words(): void
    {
        $diff = TextDiffHighlighter::diff('The old civic sentence.', 'The new civic sentence.');

        $this->assertStringContainsString('jk-diff-del', $diff['old_html']);
        $this->assertStringContainsString('old', $diff['old_html']);
        $this->assertStringContainsString('jk-diff-ins', $diff['new_html']);
        $this->assertStringContainsString('new', $diff['new_html']);

        $unsafe = TextDiffHighlighter::diff('<script>alert(1)</script>', 'മലയാളം safe');
        $this->assertStringNotContainsString('<script>', $unsafe['old_html']);
        $this->assertStringContainsString('മലയാളം', $unsafe['new_html']);
    }
}
