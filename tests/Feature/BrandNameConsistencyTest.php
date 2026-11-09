<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * Brand rule: the brand name always appears as the English spelling
 * "Jannayaks", including inside Malayalam-language content. No Malayalam-script
 * transliteration (e.g. ജന്നായക്സ് / ജന്നയക്സ്) may appear anywhere.
 */
class BrandNameConsistencyTest extends TestCase
{
    private const FORBIDDEN_TRANSLITERATIONS = ['ജന്നായ', 'ജന്നയ'];

    public function test_customer_pages_do_not_contain_the_transliteration(): void
    {
        foreach ([
            route('home'),
            route('faq-charges'),
            route('register'),
            route('login'),
            route('apply'),
        ] as $url) {
            $content = $this->get($url)->assertOk()->getContent();

            foreach (self::FORBIDDEN_TRANSLITERATIONS as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $content,
                    "The brand-name transliteration {$forbidden} appears on {$url}.",
                );
            }

            $this->assertStringContainsString('Jannayaks', $content);
        }
    }

    public function test_application_and_website_sources_do_not_contain_the_transliteration(): void
    {
        $roots = [
            base_path('app'),
            base_path('resources'),
            base_path('config'),
            base_path('routes'),
            base_path('database'),
        ];

        $violations = [];

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php' && $file->getExtension() !== 'blade.php') {
                    if (! str_ends_with($file->getFilename(), '.blade.php')) {
                        continue;
                    }
                }

                $contents = file_get_contents($file->getPathname());

                foreach (self::FORBIDDEN_TRANSLITERATIONS as $forbidden) {
                    if (str_contains($contents, $forbidden)) {
                        $violations[] = $file->getPathname().' contains '.$forbidden;
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Brand-name transliterations remain:\n".implode("\n", $violations));
    }
}
