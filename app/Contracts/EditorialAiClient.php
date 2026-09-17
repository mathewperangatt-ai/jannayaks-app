<?php

namespace App\Contracts;

interface EditorialAiClient
{
    /**
     * @param  array{
     *   system: string,
     *   user: string,
     *   purpose: string
     * }  $request
     * @return array{
     *   title: string,
     *   summary: string,
     *   body: string,
     *   claims: list<array{excerpt: string, question_id: ?string}>
     * }
     */
    public function generateEditorial(array $request): array;

    public function providerName(): string;

    public function modelName(): string;
}
