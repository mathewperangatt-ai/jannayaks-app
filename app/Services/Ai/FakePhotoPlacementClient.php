<?php

namespace App\Services\Ai;

use App\Contracts\PhotoPlacementAiClient;
use InvalidArgumentException;

/**
 * Deterministic test double for photograph-placement recommendations.
 * It always places the first supplied photograph inline in the biography,
 * the second in the gallery, and honours the provider/test seams used by
 * the editorial AI client.
 */
class FakePhotoPlacementClient implements PhotoPlacementAiClient
{
    /** @var list<array{photos: int, narrative_contains: string}> */
    public array $requests = [];

    public bool $failNext = false;

    public string $failMessage = 'AI provider failed.';

    /** When true, the fake returns a recommendation referencing an unknown media id (invalid-response path). */
    public bool $includeUnknownMediaId = false;

    public function recommendPlacements(array $photos, string $narrative): array
    {
        $this->requests[] = [
            'photos' => count($photos),
            'narrative_contains' => str_contains($narrative, 'ENGLISH BODY FIRST PARAGRAPH.') ? 'approved-narrative' : 'narrative',
        ];

        if ($this->failNext) {
            $this->failNext = false;
            throw new InvalidArgumentException($this->failMessage);
        }

        if ($photos === []) {
            throw new InvalidArgumentException('No photographs were supplied for placement.');
        }

        $recommendations = [];
        $slots = [
            ['placement_type' => 'hero', 'section_anchor' => null],
            ['placement_type' => 'inline', 'section_anchor' => 'biography'],
            ['placement_type' => 'gallery', 'section_anchor' => 'gallery'],
        ];

        foreach (array_values($photos) as $index => $photo) {
            $slot = $slots[$index % count($slots)];
            $recommendations[] = [
                'media_id' => $photo['media_id'],
                'placement_type' => $slot['placement_type'],
                'section_anchor' => $slot['section_anchor'],
                'display_order' => $index + 1,
                'caption_en' => null,
                'caption_ml' => null,
                'rationale' => 'Fake recommendation.',
            ];
        }

        if ($this->includeUnknownMediaId) {
            $this->includeUnknownMediaId = false;
            $recommendations[] = [
                'media_id' => 999999,
                'placement_type' => 'inline',
                'section_anchor' => 'biography',
                'display_order' => 4,
                'caption_en' => null,
                'caption_ml' => null,
                'rationale' => 'Invalid media reference.',
            ];
        }

        return $recommendations;
    }

    public function providerName(): string
    {
        return 'fake';
    }

    public function modelName(): string
    {
        return 'fake-placement-v1';
    }
}
