<?php

namespace App\Services\GeographyImport;

interface GeographyImportAdapterInterface
{
    public function sourceCode(): string;

    public function validateFile(string $filename): array;
}
