<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Services\Students\StudentSpreadsheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SubjectSpreadsheetController extends Controller
{
    public function template(StudentSpreadsheetService $spreadsheet): BinaryFileResponse
    {
        return $this->download(
            $spreadsheet->createWorkbook(
                ['Kode Mapel', 'Nama Mapel', 'Status'],
                [['MTK', 'Matematika', 'aktif']],
                'Template Mapel',
            ),
            'template-import-mapel.xlsx',
        );
    }

    public function export(StudentSpreadsheetService $spreadsheet): BinaryFileResponse
    {
        $rows = Subject::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $subject) => [
                $subject->code,
                $subject->name,
                $subject->is_active ? 'aktif' : 'nonaktif',
            ])
            ->all();

        return $this->download(
            $spreadsheet->createWorkbook(['Kode Mapel', 'Nama Mapel', 'Status'], $rows, 'Data Mapel'),
            'data-mapel-'.now()->format('Y-m-d-His').'.xlsx',
        );
    }

    public function import(Request $request, StudentSpreadsheetService $spreadsheet): RedirectResponse
    {
        $request->validate([
            'subject_spreadsheet' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('subject_spreadsheet');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            return back()->withErrors(['subject_spreadsheet' => 'File mapel harus berformat .xlsx atau .csv.']);
        }

        try {
            $rows = $spreadsheet->readTable($file);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['subject_spreadsheet' => $exception->getMessage()]);
        }

        if ($rows === []) {
            return back()->withErrors(['subject_spreadsheet' => 'File tidak memiliki data mata pelajaran.']);
        }

        $headers = array_keys($rows[0]);
        if (! in_array('kode_mapel', $headers, true) || ! in_array('nama_mapel', $headers, true)) {
            return back()->withErrors(['subject_spreadsheet' => 'Kolom Kode Mapel dan Nama Mapel wajib tersedia. Gunakan template dari sistem.']);
        }

        if (count($rows) > 1000) {
            return back()->withErrors(['subject_spreadsheet' => 'Maksimal 1.000 mata pelajaran dalam sekali impor.']);
        }

        $preparedRows = [];
        $errors = [];
        $seenCodes = [];

        foreach ($rows as $row) {
            $line = (int) $row['_row'];
            $data = [
                'code' => Str::upper(trim((string) ($row['kode_mapel'] ?? ''))),
                'name' => trim((string) ($row['nama_mapel'] ?? '')),
                'status' => Str::lower(trim((string) ($row['status'] ?? 'aktif'))),
            ];
            $validator = Validator::make($data, [
                'code' => ['required', 'string', 'max:30'],
                'name' => ['required', 'string', 'max:255'],
                'status' => ['required', 'in:aktif,nonaktif,active,inactive,1,0'],
            ]);

            if ($validator->fails()) {
                $errors[] = 'Baris '.$line.': '.$validator->errors()->first();
                continue;
            }

            if (isset($seenCodes[$data['code']])) {
                $errors[] = 'Baris '.$line.': kode mapel duplikat di dalam file.';
            }

            $seenCodes[$data['code']] = true;
            $preparedRows[] = $data;
        }

        if ($errors !== []) {
            return back()->withErrors([
                'subject_spreadsheet' => implode(' ', array_slice(array_unique($errors), 0, 10)),
            ]);
        }

        DB::transaction(function () use ($preparedRows): void {
            foreach ($preparedRows as $data) {
                Subject::updateOrCreate(
                    ['code' => $data['code']],
                    [
                        'name' => $data['name'],
                        'is_active' => $this->isActive($data['status']),
                    ],
                );
            }
        });

        return back()->with('status', count($preparedRows).' mata pelajaran berhasil diimpor atau diperbarui.');
    }

    private function download(string $path, string $filename): BinaryFileResponse
    {
        return response()
            ->download(
                $path,
                $filename,
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )
            ->deleteFileAfterSend(true);
    }

    private function isActive(string $status): bool
    {
        return in_array($status, ['aktif', 'active', '1'], true);
    }
}
