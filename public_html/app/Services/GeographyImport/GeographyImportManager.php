<?php

namespace App\Services\GeographyImport;

use InvalidArgumentException;

class GeographyImportManager
{
    public function validate(string $source, string $filename, string $mode): array
    {
        if ($mode !== 'validate') {
            throw new InvalidArgumentException('Unsupported import mode.');
        }

        return $this->adapter($source)->validateFile($filename);
    }

    private function adapter(string $source): GeographyImportAdapterInterface
    {
        return match ($source) {
            MinistryGeographyImporter::SOURCE_CODE => new MinistryGeographyImporter(),
            StatisticalCenterGeographyImporter::SOURCE_CODE => new StatisticalCenterGeographyImporter(),
            default => throw new InvalidArgumentException('Unsupported data source.'),
        };
    }
}
