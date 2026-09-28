<?php
namespace Core;

class SimpleXLSXWriter {
    /**
     * Generates a valid OpenXML .xlsx binary file content from headers and data rows.
     */
    public static function create(array $headers, array $rows, string $sheetName = 'Invoices'): string {
        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip = new \ZipArchive();
        if ($zip->open($tempFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Cannot create temporary zip archive for XLSX export");
        }

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
        $zip->addFromString('_rels/.rels', $rootRels);

        // 3. xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // 4. xl/workbook.xml
        $cleanSheetName = htmlspecialchars(substr($sheetName, 0, 31), ENT_XML1);
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets>' .
            '<sheet name="' . $cleanSheetName . '" sheetId="1" r:id="rId1"/>' .
            '</sheets>' .
            '</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // 5. xl/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<fonts count="2">' .
            '<font><sz val="11"/><name val="Calibri"/></font>' .
            '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' .
            '</fonts>' .
            '<fills count="3">' .
            '<fill><patternFill patternType="none"/></fill>' .
            '<fill><patternFill patternType="gray125"/></fill>' .
            '<fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/></patternFill></fill>' .
            '</fills>' .
            '<borders count="1">' .
            '<border><left/><right/><top/><bottom/><diagonal/></border>' .
            '</borders>' .
            '<cellStyleXfs count="1">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' .
            '</cellStyleXfs>' .
            '<cellXfs count="2">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
            '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' .
            '</cellXfs>' .
            '</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // 6. xl/worksheets/sheet1.xml
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .
            '<sheetData>';

        $rowNum = 1;
        if (!empty($headers)) {
            $xml .= '<row r="' . $rowNum . '">';
            $colNum = 1;
            foreach ($headers as $h) {
                $ref = self::colLetter($colNum) . $rowNum;
                $val = htmlspecialchars((string)$h, ENT_XML1);
                $xml .= '<c r="' . $ref . '" s="1" t="inlineStr"><is><t>' . $val . '</t></is></c>';
                $colNum++;
            }
            $xml .= '</row>';
            $rowNum++;
        }

        foreach ($rows as $row) {
            $xml .= '<row r="' . $rowNum . '">';
            $colNum = 1;
            foreach ($row as $cell) {
                $ref = self::colLetter($colNum) . $rowNum;
                if ($cell === null || $cell === '') {
                    // Empty cell
                } elseif (is_numeric($cell) && !preg_match('/^0[0-9]+/', (string)$cell)) {
                    $xml .= '<c r="' . $ref . '"><v>' . $cell . '</v></c>';
                } else {
                    $val = htmlspecialchars((string)$cell, ENT_XML1);
                    $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . $val . '</t></is></c>';
                }
                $colNum++;
            }
            $xml .= '</row>';
            $rowNum++;
        }

        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        $content = file_get_contents($tempFile);
        @unlink($tempFile);
        return $content;
    }

    public static function colLetter(int $colIndex): string {
        $letter = '';
        while ($colIndex > 0) {
            $remainder = ($colIndex - 1) % 26;
            $letter = chr(65 + $remainder) . $letter;
            $colIndex = intval(($colIndex - $remainder) / 26);
        }
        return $letter;
    }
}
