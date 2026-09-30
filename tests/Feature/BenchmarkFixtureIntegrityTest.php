<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

/**
 * Benchmark fixture integrity (Master Editorial Specification §§25, 41).
 *
 * The 12 living benchmark profiles and 2 In Memoriam demos are manually
 * authored acceptance/reference fixtures extracted VERBATIM from the
 * specification. They must never be regenerated, must never contain the
 * confirmed chat-artifact contamination, and must never contain the
 * prohibited Malayalam word.
 */
class BenchmarkFixtureIntegrityTest extends TestCase
{
    private const FIXTURE_DIR = 'Fixtures/EditorialBenchmarks';

    /** @var array<string, string> */
    private const EXPECTED_FILES = [
        'recognised_s_madhavan.md',
        'recognised_dr_meera_nair.md',
        'recognised_r_abdul_rahman.md',
        'recognised_kavitha_menon.md',
        'acclaimed_anil_varma.md',
        'acclaimed_dr_leela_thomas.md',
        'acclaimed_prakash_dev.md',
        'acclaimed_farzana_basheer.md',
        'distinguished_k_rajeev.md',
        'distinguished_prof_anitha_krishnan.md',
        'distinguished_joseph_mathew.md',
        'distinguished_nandini_varma.md',
        'in_memoriam_p_narayanan.md',
        'in_memoriam_dr_amina_rahman.md',
    ];

    /** @var list<string> markers of the confirmed paste contamination zones */
    private const CONTAMINATION_MARKERS = [
        '😄',
        'We now have the English living benchmark set',
        'ZCode-ready',
        'I would not send these to ZCode',
        'convince the machine',
        'That is now my job',
    ];

    public function test_all_fourteen_benchmark_fixtures_exist(): void
    {
        foreach (self::EXPECTED_FILES as $file) {
            $this->assertFileExists($this->path($file), "Missing benchmark fixture: {$file}");
        }
    }

    public function test_fixtures_are_verbatim_specification_slices(): void
    {
        $manifest = json_decode((string) file_get_contents($this->path('manifest.json')), true);
        $this->assertIsArray($manifest);
        $this->assertCount(14, $manifest['fixtures'] ?? []);
        $this->assertSame(
            'Jannayaks Master Editorial + API Specification (sections 26-40)',
            $manifest['source'] ?? null,
        );

        $listed = array_column($manifest['fixtures'], 'file');
        $this->assertSame([], array_diff(self::EXPECTED_FILES, $listed));
        $this->assertSame([], array_diff($listed, self::EXPECTED_FILES));
    }

    public function test_no_fixture_contains_chat_artifact_contamination(): void
    {
        foreach (self::EXPECTED_FILES as $file) {
            $content = (string) file_get_contents($this->path($file));
            foreach (self::CONTAMINATION_MARKERS as $marker) {
                $this->assertStringNotContainsString(
                    $marker,
                    $content,
                    "Contamination marker '{$marker}' found in {$file}",
                );
            }
        }
    }

    public function test_every_fixture_carries_english_and_malayalam(): void
    {
        foreach (self::EXPECTED_FILES as $file) {
            $content = (string) file_get_contents($this->path($file));
            $this->assertMatchesRegularExpression('/[a-z]{3,}\s+[a-z]{3,}/', $content, "{$file} has no English benchmark text");
            // Malayalam vowel-sign codepoints appear in any substantial Malayalam text.
            $this->assertMatchesRegularExpression('/[\x{0D3F}-\x{0D4D}]/u', $content, "{$file} has no Malayalam benchmark text");
        }
    }

    public function test_prohibited_malayalam_word_appears_in_no_fixture(): void
    {
        foreach (self::EXPECTED_FILES as $file) {
            $this->assertStringNotContainsString(
                'തറവാട്',
                (string) file_get_contents($this->path($file)),
                "Prohibited word found in {$file}",
            );
        }
    }

    public function test_living_benchmarks_use_continuous_prose_not_formulaic_headings(): void
    {
        $formulaic = ['Early Life', 'Education', 'Career', 'Achievements', 'Family', 'Future Plans'];

        foreach (self::EXPECTED_FILES as $file) {
            if (str_starts_with($file, 'in_memoriam')) {
                continue;
            }

            $lines = explode("\n", (string) file_get_contents($this->path($file)));
            foreach ($lines as $line) {
                $trimmed = ltrim($line, "#* \t");
                foreach ($formulaic as $heading) {
                    $this->assertNotSame(
                        $heading,
                        $trimmed,
                        "Formulaic article heading '{$heading}' found in {$file} — benchmarks are continuous prose (§4).",
                    );
                }
            }
        }
    }

    public function test_in_memoriam_fixtures_are_marked_fictional_demonstrations(): void
    {
        foreach (['in_memoriam_p_narayanan.md', 'in_memoriam_dr_amina_rahman.md'] as $file) {
            $content = (string) file_get_contents($this->path($file));
            $this->assertStringContainsString('Fictional demonstration', $content, "{$file} must be marked as a fictional demonstration");
        }
    }

    public function test_fixture_directory_is_not_referenced_by_runtime_code(): void
    {
        $files = (new Filesystem)->allFiles(app_path());
        foreach ($files as $file) {
            $content = (string) $file->getContents();
            $this->assertStringNotContainsString(
                'EditorialBenchmarks',
                $content,
                "{$file->getRelativePathname()} references benchmark fixtures — fixtures are test/acceptance-only (§25: never prompts).",
            );
        }
    }

    private function path(string $file): string
    {
        return dirname(__DIR__).'/'.self::FIXTURE_DIR.'/'.$file;
    }
}
