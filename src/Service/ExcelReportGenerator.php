<?php

namespace App\Service;

class ExcelReportGenerator
{
    /**
     * Génère un fichier Excel (.xls) au format le plus simple possible :
     * Un simple tableau brut (en-têtes et lignes de données), sans styles complexes,
     * sans couleurs de fond ni fioritures visuelles.
     *
     * @param string $sheetTitle Titre de la feuille
     * @param array<string, string> $columns Clé => Libellé de colonne
     * @param array<int, array<string, mixed>> $rows Lignes de données
     * @param array<string, string> $columnTypes Clé => Type ('string', 'number', 'currency', 'date', 'badge')
     */
    public function generateExcelXml(string $sheetTitle, array $columns, array $rows, array $columnTypes = []): string
    {
        return $this->generateMultiSheetExcelXml([
            [
                'title'       => $sheetTitle,
                'columns'     => $columns,
                'rows'        => $rows,
                'columnTypes' => $columnTypes,
            ]
        ]);
    }

    /**
     * Génère un classeur SpreadsheetML épuré : un simple tableau avec colonnes et données.
     *
     * @param array<int, array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, columnTypes?: array<string, string>}> $sheets
     */
    public function generateMultiSheetExcelXml(array $sheets, string $mainTitle = ''): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
        $xml .= ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
        $xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
        $xml .= ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";

        // Styles avec bordures simples et nettes (sans fioritures visuelles)
        $xml .= " <Styles>\n";
        $xml .= '  <Style ss:ID="Default" ss:Name="Normal">' . "\n";
        $xml .= '   <Font ss:FontName="Calibri" ss:Size="11"/>' . "\n";
        $xml .= '   <Alignment ss:Vertical="Center"/>' . "\n";
        $xml .= '   <Borders>' . "\n";
        $xml .= '    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '   </Borders>' . "\n";
        $xml .= "  </Style>\n";
        $xml .= '  <Style ss:ID="HeaderStyle">' . "\n";
        $xml .= '   <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1"/>' . "\n";
        $xml .= '   <Alignment ss:Vertical="Center"/>' . "\n";
        $xml .= '   <Borders>' . "\n";
        $xml .= '    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '   </Borders>' . "\n";
        $xml .= "  </Style>\n";
        $xml .= '  <Style ss:ID="DataCell">' . "\n";
        $xml .= '   <Font ss:FontName="Calibri" ss:Size="11"/>' . "\n";
        $xml .= '   <Alignment ss:Vertical="Center"/>' . "\n";
        $xml .= '   <Borders>' . "\n";
        $xml .= '    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '    <Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#000000"/>' . "\n";
        $xml .= '   </Borders>' . "\n";
        $xml .= "  </Style>\n";
        $xml .= " </Styles>\n";

        foreach ($sheets as $sheetData) {
            $sheetTitle = $sheetData['title'] ?? 'Rapport';
            $columns    = $sheetData['columns'] ?? [];
            $rows       = $sheetData['rows'] ?? [];

            if (empty($columns)) {
                continue;
            }

            $safeSheetName = substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $sheetTitle), 0, 31);

            $xml .= ' <Worksheet ss:Name="' . $this->xmlEscape($safeSheetName) . '">' . "\n";
            $xml .= "  <Table>\n";

            // Largeur automatique pour chaque colonne
            foreach ($columns as $label) {
                $xml .= '   <Column ss:AutoFitWidth="1"/>' . "\n";
            }

            // Ligne 1 : Les en-têtes du tableau (simple, direct avec bordures)
            $xml .= "   <Row>\n";
            foreach ($columns as $label) {
                $xml .= '    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">' . $this->xmlEscape((string) $label) . '</Data></Cell>' . "\n";
            }
            $xml .= "   </Row>\n";

            // Lignes de données : valeurs brutes avec bordures
            foreach ($rows as $row) {
                $xml .= "   <Row>\n";
                foreach ($columns as $key => $label) {
                    $val = $row[$key] ?? '';
                    if ($val === null || $val === '') {
                        $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">-</Data></Cell>' . "\n";
                    } elseif (is_numeric($val)) {
                        $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="Number">' . (float) $val . '</Data></Cell>' . "\n";
                    } else {
                        $xml .= '    <Cell ss:StyleID="DataCell"><Data ss:Type="String">' . $this->xmlEscape((string) $val) . '</Data></Cell>' . "\n";
                    }
                }
                $xml .= "   </Row>\n";
            }

            $xml .= "  </Table>\n";
            $xml .= " </Worksheet>\n";
        }

        $xml .= "</Workbook>\n";

        return $xml;
    }

    /**
     * Génère un CSV simple avec UTF-8 BOM.
     *
     * @param array<string, string> $columns
     * @param array<int, array<string, mixed>> $rows
     */
    public function generateCsv(array $columns, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_values($columns), ';', '"', "\\");

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $key => $label) {
                $line[] = $row[$key] ?? '';
            }
            fputcsv($handle, $line, ';', '"', "\\");
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * Génère un CSV multi-sections brut.
     *
     * @param array<int, array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>}> $sections
     */
    public function generateMultiSectionCsv(array $sections): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($sections as $sec) {
            $columns = $sec['columns'] ?? [];
            $rows    = $sec['rows'] ?? [];

            if (empty($columns)) {
                continue;
            }

            fputcsv($handle, array_values($columns), ';', '"', "\\");

            foreach ($rows as $row) {
                $line = [];
                foreach ($columns as $key => $label) {
                    $line[] = $row[$key] ?? '';
                }
                fputcsv($handle, $line, ';', '"', "\\");
            }

            fputcsv($handle, [], ';', '"', "\\");
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
