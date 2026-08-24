<?php

namespace App\Services\Students;

use App\Models\Student;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

class StudentSpreadsheetService
{
    private const IMPORT_HEADERS = ['NIS', 'NISN', 'Nama Lengkap', 'Email', 'Password', 'Status'];

    /** @return array<int, array<string, string|null>> */
    public function read(UploadedFile $file): array
    {
        $rows = match (strtolower($file->getClientOriginalExtension())) {
            'csv' => $this->readCsv($file->getRealPath()),
            'xlsx' => $this->readXlsx($file->getRealPath()),
            default => throw new RuntimeException('Format file harus .xlsx atau .csv.'),
        };

        if ($rows === []) {
            return [];
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), array_shift($rows));
        $required = ['nis', 'nama_lengkap'];

        foreach ($required as $header) {
            if (! in_array($header, $headers, true)) {
                throw new RuntimeException('Kolom wajib NIS dan Nama Lengkap tidak ditemukan. Gunakan template dari sistem.');
            }
        }

        $result = [];

        foreach ($rows as $offset => $row) {
            $mapped = [];

            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $mapped[$header] = isset($row[$index]) ? trim((string) $row[$index]) : null;
                }
            }

            if (collect($mapped)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty()) {
                continue;
            }

            $mapped['_row'] = (string) ($offset + 2);
            $result[] = $mapped;
        }

        return $result;
    }

    public function createTemplate(): string
    {
        return $this->createXlsx(self::IMPORT_HEADERS, [[
            '2026001',
            '0123456789',
            'Contoh Siswa',
            'siswa@example.sch.id',
            'Siswa1234',
            'aktif',
        ]], 'Template Siswa');
    }

    /** @param iterable<int, Student> $students */
    public function createExport(iterable $students): string
    {
        $headers = ['NIS', 'NISN', 'Nama Lengkap', 'Kelas', 'Tahun Ajaran', 'Email', 'Username', 'Status'];
        $rows = [];

        foreach ($students as $student) {
            $rows[] = [
                $student->student_number,
                $student->nisn,
                $student->full_name,
                $student->schoolClass->name,
                $student->schoolClass->academicYear->name,
                $student->user?->email,
                $student->user?->username,
                $student->is_active ? 'aktif' : 'nonaktif',
            ];
        }

        return $this->createXlsx($headers, $rows, 'Data Siswa');
    }

    /** @return array<int, array<int, string>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        $rows = [];

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if ($rows === [] && isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            }

            $rows[] = array_map(fn ($value) => (string) $value, $row);
        }

        fclose($handle);

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    private function readXlsx(string $path): array
    {
        $this->ensureZipAvailable();

        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Excel tidak dapat dibuka.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedXml !== false) {
            $xml = simplexml_load_string($sharedXml);

            foreach ($xml->xpath('/*[local-name()="sst"]/*[local-name()="si"]') ?: [] as $item) {
                $texts = $item->xpath('.//*[local-name()="t"]') ?: [];
                $sharedStrings[] = implode('', array_map(fn ($text) => (string) $text, $texts));
            }
        }

        $sheetPath = $this->firstWorksheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Worksheet pertama tidak ditemukan.');
        }

        $sheet = simplexml_load_string($sheetXml);
        $rows = [];

        foreach ($sheet->xpath('/*[local-name()="worksheet"]/*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $values = [];

            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnIndex($reference);
                $type = (string) $cell['t'];
                $rawValues = $cell->xpath('./*[local-name()="v"]') ?: [];
                $rawValue = (string) ($rawValues[0] ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $rawValue] ?? '';
                } elseif ($type === 'inlineStr') {
                    $texts = $cell->xpath('.//*[local-name()="t"]') ?: [];
                    $value = implode('', array_map(fn ($text) => (string) $text, $texts));
                } else {
                    $value = $rawValue;
                }

                $values[$columnIndex] = $value;
            }

            if ($values !== []) {
                $lastColumn = max(array_keys($values));
                $rows[] = array_map(
                    fn ($index) => $values[$index] ?? '',
                    range(0, $lastColumn),
                );
            }
        }

        return $rows;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relationshipsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = simplexml_load_string($workbookXml);
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheetNodes = $workbook->xpath('/*[local-name()="workbook"]/*[local-name()="sheets"]/*[local-name()="sheet"]') ?: [];
        $firstSheet = $sheetNodes[0] ?? null;
        $relationshipId = $firstSheet?->attributes('r', true)['id'] ?? null;
        $relationships = simplexml_load_string($relationshipsXml);

        foreach ($relationships->xpath('/*[local-name()="Relationships"]/*[local-name()="Relationship"]') ?: [] as $relationship) {
            if ((string) $relationship['Id'] === (string) $relationshipId) {
                $target = ltrim((string) $relationship['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^[A-Z]+/i', $reference, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = str_replace([' ', '-'], '_', $header);

        return match ($header) {
            'nomor_induk', 'nomor_siswa', 'student_number' => 'nis',
            'nama', 'nama_siswa', 'full_name' => 'nama_lengkap',
            default => $header,
        };
    }

    /** @param array<int, string> $headers @param array<int, array<int, mixed>> $rows */
    private function createXlsx(array $headers, array $rows, string $sheetName): string
    {
        $this->ensureZipAvailable();

        $path = tempnam(sys_get_temp_dir(), 'students_');
        $zip = new ZipArchive();

        if ($path === false || $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File Excel sementara tidak dapat dibuat.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($headers, $rows));
        $zip->close();

        return $path;
    }

    /** @param array<int, string> $headers @param array<int, array<int, mixed>> $rows */
    private function worksheetXml(array $headers, array $rows): string
    {
        $allRows = [$headers, ...$rows];
        $rowXml = '';

        foreach ($allRows as $rowIndex => $row) {
            $cells = '';

            foreach ($row as $columnIndex => $value) {
                $reference = $this->columnLetters($columnIndex + 1).($rowIndex + 1);
                $style = $rowIndex === 0 ? ' s="1"' : '';
                $escaped = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$escaped.'</t></is></c>';
            }

            $rowXml .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }

        $lastColumn = $this->columnLetters(count($headers));
        $lastRow = count($allRows);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$this->columnWidthsXml(count($headers)).'</cols>'
            .'<sheetData>'.$rowXml.'</sheetData>'
            .'<autoFilter ref="A1:'.$lastColumn.$lastRow.'"/>'
            .'</worksheet>';
    }

    private function columnWidthsXml(int $count): string
    {
        $widths = [16, 16, 32, 28, 18, 14, 18, 14];
        $xml = '';

        for ($index = 1; $index <= $count; $index++) {
            $width = $widths[$index - 1] ?? 18;
            $xml .= '<col min="'.$index.'" max="'.$index.'" width="'.$width.'" customWidth="1"/>';
        }

        return $xml;
    }

    private function columnLetters(int $index): string
    {
        $letters = '';

        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(string $sheetName): string
    {
        $name = htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Aptos"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Aptos"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0891B2"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="49" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyNumberFormat="1"><alignment vertical="center"/></xf></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function ensureZipAvailable(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP ZIP belum aktif. Aktifkan extension=zip pada php.ini lalu restart Apache/terminal.');
        }

        if (! function_exists('simplexml_load_string')) {
            throw new RuntimeException('Ekstensi PHP SimpleXML belum aktif.');
        }
    }
}
