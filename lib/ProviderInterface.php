<?php

namespace TypechoPlugin\MediaShelf\Lib;

interface ProviderInterface
{
    public function getName(): string;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, string $category): array;

    /**
     * @return array<string, mixed>
     */
    public function getDetails(string $id, string $category): array;
}
