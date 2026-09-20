<?php

namespace Tests\Unit;

use App\Services\PersonalSlugGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PersonalSlugGeneratorTest extends TestCase
{
    private PersonalSlugGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PersonalSlugGenerator;
    }

    public function test_generates_name_derived_combinations_for_three_part_name(): void
    {
        $suggestions = $this->generator->generateCombinations('Arun Kumar Nair');

        foreach (['arun.kumar', 'arun.kumar.nair', 'arunkumar', 'arunkumarnair', 'arun.k.nair', 'arun.nair'] as $expected) {
            $this->assertContains($expected, $suggestions);
        }

        $this->assertNotContains('dr.arun.kumar', $suggestions);
        $this->assertNotContains('superstar', $suggestions);
    }

    public function test_does_not_treat_database_ids_as_identity_numbers(): void
    {
        $suffixes = $this->generator->collisionSuffixes(12345);

        $this->assertContains(24, $suffixes);
        $this->assertContains(10, $suffixes);
        $this->assertNotContains(12345, $suffixes);
        $this->assertSame('arun.kumar24', $this->generator->collisionCandidate('arun.kumar', 24));
    }

    #[DataProvider('derivedSlugs')]
    public function test_accepts_recognisable_name_derived_slugs(string $slug): void
    {
        $this->assertTrue($this->generator->isNameDerived($slug, 'Arun Kumar Nair'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function derivedSlugs(): array
    {
        return [
            'dotted_two' => ['arun.kumar'],
            'dotted_three' => ['arun.kumar.nair'],
            'concat_two' => ['arunkumar'],
            'concat_three' => ['arunkumarnair'],
            'initial' => ['arun.k.nair'],
            'skip_middle' => ['arun.nair'],
            'hyphen' => ['arun-kumar'],
            'title_and_suffix' => ['dr.arun.kumar24'],
        ];
    }

    #[DataProvider('vanitySlugs')]
    public function test_rejects_arbitrary_vanity_usernames(string $slug): void
    {
        $this->assertFalse($this->generator->isNameDerived($slug, 'Arun Kumar Nair'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function vanitySlugs(): array
    {
        return [
            'superstar' => ['superstar'],
            'best_doctor' => ['bestdoctor'],
            'leader' => ['leader'],
            'the_great_one' => ['thegreatone'],
            'reversed' => ['nair.arun'],
            'unrelated_title_word' => ['star.arun.kumar'],
        ];
    }

    public function test_optional_title_is_consistent_with_profession_when_present(): void
    {
        $this->assertTrue($this->generator->titleIsConsistent('dr', 'Physician and surgeon'));
        $this->assertTrue($this->generator->titleIsConsistent('adv', 'Advocate, High Court'));
        $this->assertTrue($this->generator->titleIsConsistent('prof', ''));
        $this->assertFalse($this->generator->titleIsConsistent('dr', 'School teacher'));
        $this->assertFalse($this->generator->titleIsConsistent('star', 'Actor'));
    }
}
