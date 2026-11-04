<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * SAFIR v2.5.0 R72A: pure, provider-free, database-free import policy.
 * This class is NOT referenced by routes or workers until later reviewed steps.
 * It does not persist campaign data and must not be confused with a durable store.
 */
final class BaleSafirDurableCampaignPolicy
{
    public const MAX_CHUNK_ROWS = 500;

    /** @return array{code:string,snapshot_json:string,sha256:string,phone_column:string,mappings:array} */
    public static function freezeApprovedTemplate(array $template): array
    {
        $code = $template['code'] ?? null;
        $body = $template['body'] ?? null;
        $phoneColumn = $template['phone_column'] ?? null;
        $mappings = $template['mappings'] ?? null;
        $botId = (string) ($template['bot_id'] ?? '');
        $templateId = (string) ($template['template_id'] ?? '');

        if (!is_string($code) || !preg_match('/^[a-z][a-z0-9._-]{1,63}$/D', $code)
            || !is_string($body) || trim($body) === '' || mb_strlen($body, 'UTF-8') > 4000
            || !is_string($phoneColumn) || trim($phoneColumn) === ''
            || !is_array($mappings)
            || !preg_match('/^[1-9][0-9]{5,19}$/D', $botId)
            || !preg_match('/^[A-Za-z0-9_-]{2,64}$/D', $templateId)
            || ($template['active'] ?? false) !== true
            || ($template['provider_approved'] ?? false) !== true) {
            throw new InvalidArgumentException('safir_durable_template_invalid');
        }

        preg_match_all('/\{\{([^{}]*)\}\}/u', $body, $raw);
        preg_match_all('/\{\{([\p{L}_][\p{L}\p{N}_]*)\}\}/u', $body, $valid);
        if (count($raw[0]) !== count($valid[0])
            || preg_match('/\{\{|\}\}/u', str_replace($valid[0], '', $body))) {
            throw new InvalidArgumentException('safir_durable_placeholders_invalid');
        }
        $placeholders = array_values(array_unique($valid[1]));
        if (count($placeholders) > 100 || count($mappings) !== count($placeholders)) {
            throw new InvalidArgumentException('safir_durable_mapping_mismatch');
        }
        foreach ($mappings as $key => $columns) {
            if (!is_string($key) || !in_array($key, $placeholders, true)
                || !is_array($columns) || count($columns) < 1 || count($columns) > 8) {
                throw new InvalidArgumentException('safir_durable_mapping_mismatch');
            }
            foreach ($columns as $column) {
                if (!is_string($column) || trim($column) === '' || mb_strlen($column, 'UTF-8') > 120) {
                    throw new InvalidArgumentException('safir_durable_column_invalid');
                }
            }
        }

        // Preserve exactly the saved provider-approved template, including
        // configurable bot/template references. Never hardcode variable names.
        $snapshot = json_encode($template, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return [
            'code' => $code,
            'snapshot_json' => $snapshot,
            'sha256' => hash('sha256', $snapshot),
            'phone_column' => $phoneColumn,
            'mappings' => $mappings,
        ];
    }

    public static function normalizePhone(string $input): ?string
    {
        // Same canonical formats as the existing SAFIR service; accept
        // Persian and Arabic digits and Excel numbers without the leading 0.
        $text = strtr($input, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5',
            '۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1',
            '٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7',
            '٨'=>'8','٩'=>'9',
        ]);
        $text = preg_replace('/[\s()+-]/u', '', trim($text)) ?? '';
        if (preg_match('/^09[0-9]{9}$/D', $text)) {
            return '98' . substr($text, 1);
        }
        if (preg_match('/^989[0-9]{9}$/D', $text)) {
            return $text;
        }
        if (preg_match('/^9[0-9]{9}$/D', $text)) {
            return '98' . $text;
        }
        if (preg_match('/^00989[0-9]{9}$/D', $text)) {
            return substr($text, 2);
        }
        return null;
    }

    /**
     * @param array $frozen output of freezeApprovedTemplate
     * @param array $rows bounded records: [['row'=>positive-int,'columns'=>array<string,string>],...]
     * @return array{ready:array,skipped:array,ready_count:int,skipped_count:int}
     *
     * No in-memory global deduplication: cross-chunk duplicate rejection and
     * suppression must be checked by the durable DB unique key/transaction.
     */
    public static function planChunk(array $frozen, array $rows): array
    {
        if ($rows === [] || count($rows) > self::MAX_CHUNK_ROWS
            || !isset($frozen['phone_column'], $frozen['mappings'], $frozen['sha256'])
            || !is_string($frozen['phone_column']) || !is_array($frozen['mappings'])
            || !is_string($frozen['sha256'])) {
            throw new InvalidArgumentException('safir_durable_chunk_invalid');
        }
        $ready = [];
        $skipped = [];
        foreach ($rows as $item) {
            if (!is_array($item) || !is_int($item['row'] ?? null)
                || $item['row'] <= 0 || !is_array($item['columns'] ?? null)) {
                throw new InvalidArgumentException('safir_durable_row_shape_invalid');
            }
            $columns = $item['columns'];
            $phone = self::normalizePhone((string) ($columns[$frozen['phone_column']] ?? ''));
            if ($phone === null) {
                $skipped[] = ['row' => $item['row'], 'reason' => 'invalid_phone'];
                continue;
            }
            $fields = [];
            $invalid = false;
            foreach ($frozen['mappings'] as $name => $mappedColumns) {
                $parts = [];
                foreach ($mappedColumns as $columnName) {
                    $raw = $columns[$columnName] ?? '';
                    if (!is_scalar($raw)) {
                        $invalid = true;
                        break;
                    }
                    $value = trim(strtr((string) $raw, [
                        'ي' => 'ی', 'ك' => 'ک', "\u{200C}" => ' ', "\u{FEFF}" => '',
                    ]));
                    if ($value === '') {
                        $invalid = true;
                        break;
                    }
                    $parts[] = $value;
                }
                if ($invalid) {
                    break;
                }
                $value = implode(' ', $parts);
                if (mb_strlen($value, 'UTF-8') > 500) {
                    $invalid = true;
                    break;
                }
                $fields[$name] = $value;
            }
            if ($invalid) {
                $skipped[] = ['row' => $item['row'], 'reason' => 'invalid_fields'];
                continue;
            }
            $ready[] = [
                'source_row_number' => $item['row'],
                'phone_normalized' => $phone,
                'text_fields_json' => json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ];
        }
        return ['ready' => $ready, 'skipped' => $skipped,
            'ready_count' => count($ready), 'skipped_count' => count($skipped)];
    }
}
