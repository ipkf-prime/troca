<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use ZipArchive;

/**
 * Generate a blank, per-template Excel workbook.
 *
 * Column names come exclusively from the saved Safir catalog mappings.
 * The result uses the same header layout consumed by
 * BaleSafirCampaignService::parseWorkbook(): sheet1, row 1.
 *
 * No application database, session, provider API, or recipient data is read.
 */
final class BaleSafirExcelSkeletonService
{
    public function headers(array $template): array
    {
        $phone = $template['phone_column'] ?? null;
        $mappings = $template['mappings'] ?? null;
        if (!is_string($phone) || !is_array($mappings)) {
            throw new RuntimeException('safir_template_mapping_invalid');
        }

        $headers = [];
        $seen = [];
        $add = static function (string $name) use (&$headers, &$seen): void {
            // Mirror the Excel importer/catalog header normalization.
            $name = str_replace(["ي", "ك", "\u{FEFF}"], ['ی', 'ک', ''], $name);
            $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
            if ($name === '' || mb_strlen($name, 'UTF-8') > 120
                || preg_match('/[\x00-\x1F\x7F]/', $name)) {
                throw new RuntimeException('safir_template_mapping_invalid');
            }
            if (!isset($seen[$name])) {
                $seen[$name] = true;
                $headers[] = $name;
            }
        };

        $add($phone);
        foreach ($mappings as $columns) {
            if (!is_array($columns) || $columns === [] || count($columns) > 8) {
                throw new RuntimeException('safir_template_mapping_invalid');
            }
            foreach ($columns as $column) {
                if (!is_string($column)) {
                    throw new RuntimeException('safir_template_mapping_invalid');
                }
                $add($column);
            }
        }

        if (count($headers) > 256) {
            throw new RuntimeException('safir_xlsx_too_many_columns');
        }

        return $headers;
    }

    public function build(array $template): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('safir_xlsx_extension_missing');
        }
        $headers = $this->headers($template);
        $lastColumn = self::columnName(count($headers));
        $cells = [];
        foreach ($headers as $index => $name) {
            $reference = self::columnName($index + 1) . '1';
            $cells[] = '<c r="' . $reference . '" s="2" t="inlineStr"><is><t>'
                . htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                . '</t></is></c>';
        }

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView rightToLeft="1" workbookViewId="0">'
            . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="24"/>'
            . '<cols><col min="1" max="' . count($headers)
            . '" width="26" customWidth="1" style="1"/></cols>'
            . '<sheetData><row r="1" ht="32" customHeight="1">'
            . implode('', $cells)
            . '</row></sheetData>'
            . '<autoFilter ref="A1:' . $lastColumn . '1"/>'
            . '</worksheet>';

        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF247A56"/>'
            . '<bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            // Text format ensures leading zeroes in manually entered mobile numbers.
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0"'
            . ' applyFont="1" applyFill="1" applyNumberFormat="1">'
            . '<alignment horizontal="center" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="مخاطبان" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => $styles,
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        $path = tempnam(sys_get_temp_dir(), 'safir_excel_');
        if (!is_string($path)) {
            throw new RuntimeException('safir_xlsx_generation_failed');
        }
        try {
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('safir_xlsx_generation_failed');
            }
            try {
                foreach ($files as $name => $xml) {
                    if (!$zip->addFromString($name, $xml)) {
                        throw new RuntimeException('safir_xlsx_generation_failed');
                    }
                }
            } finally {
                if (!$zip->close()) {
                    throw new RuntimeException('safir_xlsx_generation_failed');
                }
            }
            $binary = file_get_contents($path);
            if (!is_string($binary) || $binary === '') {
                throw new RuntimeException('safir_xlsx_generation_failed');
            }
            return $binary;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private static function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }
        return $name;
    }
}
