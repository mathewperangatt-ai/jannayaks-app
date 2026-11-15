<?php

namespace App\Contracts;

/**
 * Vision-capable client that receives the ACTUAL photograph content
 * (base64 JPEG) plus the profile narrative, and returns structured
 * placement recommendations. It never invents, generates, replaces or
 * modifies photographs, and never rewrites biography text.
 */
interface PhotoPlacementAiClient
{
    /**
     * @param  array<int, array{media_id: int, base64: string, mime: string}>  $photos
     * @param  string  $narrative  The approved profile narrative (English master body + summary).
     * @return array<int, array{media_id: int, placement_type: string, section_anchor: ?string, display_order: int, caption_en: ?string, caption_ml: ?string, rationale: ?string}>
     */
    public function recommendPlacements(array $photos, string $narrative): array;

    public function providerName(): string;

    public function modelName(): string;
}
