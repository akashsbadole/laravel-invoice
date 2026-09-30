<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Minimal dependency-free .xlsx writer (one sheet, inline strings, bold header,
 * 2-decimal money format). Needs only PHP's zip extension.
 */
class XlsxWriter
{
    /**
     * @param  array<int,array{key:string,label:string,type:string}>  $columns
     * @param  array<int,array<string,mixed>>  $rows
     * @param  array<string,mixed>|null  $totals
     */
    public static function write(string $path, string $sheetTitle, array $columns, array $rows, ?array $totals = null): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the spreadsheet file.');
        }

        $title = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $sheetTitle) ?: 'Report', 0, 31);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.self::esc($title).'" sheetId="1" r:id="rId1"/></sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        // cellXfs: 0 normal, 1 bold, 2 money, 3 bold money
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="4" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols><col min="1" max="'.max(count($columns), 1).'" width="20" customWidth="1"/></cols><sheetData>';

        $rowNumber = 1;
        $xml .= '<row r="'.$rowNumber.'">';
        foreach ($columns as $i => $column) {
            $xml .= self::cell($i, $rowNumber, $column['label'], 'text', 1);
        }
        $xml .= '</row>';

        foreach ($rows as $row) {
            $rowNumber++;
            $xml .= '<row r="'.$rowNumber.'">';
            foreach ($columns as $i => $column) {
                $xml .= self::cell($i, $rowNumber, $row[$column['key']] ?? null, $column['type'], 0);
            }
            $xml .= '</row>';
        }

        if ($totals) {
            $rowNumber++;
            $xml .= '<row r="'.$rowNumber.'">';
            foreach ($columns as $i => $column) {
                $xml .= self::cell($i, $rowNumber, $totals[$column['key']] ?? null, $column['type'], 1);
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
    }

    protected static function cell(int $colIndex, int $rowNumber, mixed $value, string $type, int $baseStyle): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $ref = self::colLetter($colIndex).$rowNumber;
        $isNumeric = in_array($type, ['money', 'number'], true) && is_numeric($value);

        if ($isNumeric) {
            $style = $type === 'money' ? ($baseStyle === 1 ? 3 : 2) : $baseStyle;

            return '<c r="'.$ref.'" s="'.$style.'"><v>'.(0 + $value).'</v></c>';
        }

        return '<c r="'.$ref.'" s="'.$baseStyle.'" t="inlineStr"><is><t xml:space="preserve">'.self::esc((string) $value).'</t></is></c>';
    }

    protected static function colLetter(int $index): string
    {
        $letters = '';
        $n = $index + 1;
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $letters = chr(65 + $m).$letters;
            $n = intdiv($n - 1, 26);
        }

        return $letters;
    }

    protected static function esc(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
