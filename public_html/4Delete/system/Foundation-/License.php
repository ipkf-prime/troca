<?php

namespace IPKF\Foundation;

class License
{
    public function handle($request): bool
    {
        $licenseFile = BASE_PATH.'/storage/license.key';

        if (!file_exists($licenseFile)) {
            return true; // dev mode
        }

        $key = trim(file_get_contents($licenseFile));

        if ($key !== 'IPKF-VALID') {

            http_response_code(403);

            echo "Invalid License";

            return false;
        }

        return true;
    }
}