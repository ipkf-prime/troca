<?php

namespace App\Services;

class NotificationTemplateRendererService extends BaseService
{
    public function render(
        string $template,
        array $data
    ): string {
        $replace = [];

        foreach ($data as $key => $value) {
            if (
                !is_string($key)
                || !is_scalar($value)
                && $value !== null
            ) {
                continue;
            }

            $replace['{{' . $key . '}}'] =
                (string) ($value ?? '');
        }

        return strtr($template, $replace);
    }
}
