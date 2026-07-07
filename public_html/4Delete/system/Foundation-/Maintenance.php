<?php

namespace IPKF\Foundation;

class Maintenance
{
    public function handle($request): bool
    {
        if (file_exists(BASE_PATH.'/storage/framework/down')) {

            http_response_code(503);

            echo "System Under Maintenance";

            return false;
        }

        return true;
    }
}