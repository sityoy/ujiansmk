<?php

namespace App\Services\Exams;

use DOMDocument;
use DOMElement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class QuestionPdfImages
{
    public function extract(UploadedFile $pdf, string $headingPattern, array $questionNumbers): array
    {
        $path = $pdf->getRealPath();
        if ($path === false) {
            return $this->unavailable();
        }

        $directory = sys_get_temp_dir().'/question-pdf-'.Str::random(24);
        File::makeDirectory($directory, 0700);

        try {
            try {
                $listing = Process::timeout(3)->run(['pdfimages', '-l', '80', '-list', $path]);
                if ($listing->failed()) {
                    return $this->unavailable();
                }

                preg_match_all('/^\h*\d+\h+\d+\h+\w+\h+(\d+)\h+(\d+)\h+/m', $listing->output(), $sizes, PREG_SET_ORDER);
                if ($sizes === []) {
                    return ['images' => [], 'warnings' => []];
                }
                // Bound rendered image data before Poppler writes the extracted files.
                $pixels = array_sum(array_map(fn (array $size) => (int) $size[1] * (int) $size[2], $sizes));
                if (count($sizes) > 200 || $pixels > 40_000_000) {
                    return ['images' => [], 'warnings' => ['Gambar terlalu banyak atau terlalu besar untuk dibaca sekaligus. Kecilkan resolusi gambar atau bagi naskah menjadi beberapa PDF.']];
                }

                $result = Process::path($directory)->timeout(6)->run([
                    'pdftohtml', '-xml', '-noroundcoord', '-zoom', '1', '-enc', 'UTF-8',
                    '-fmt', 'png', '-q', '-l', '80', $path, 'layout.xml',
                ]);
            } catch (\RuntimeException $exception) {
                report($exception);

                return $this->unavailable();
            }

            $layout = $directory.'/layout.xml';
            if ($result->failed() || ! is_file($layout) || filesize($layout) > 2_000_000) {
                return $this->unavailable();
            }

            $document = new DOMDocument;
            if (! @$document->load($layout, LIBXML_NONET) || $document->documentElement?->tagName !== 'pdf2xml') {
                return $this->unavailable();
            }

            if ($document->getElementsByTagName('image')->length === 0) {
                return ['images' => [], 'warnings' => ['Gambar di PDF tidak dapat diambil. Sisipkan gambar JPG atau PNG di naskah sebelum menyimpan sebagai PDF.']];
            }

            return $this->matchImages($document, $directory, $headingPattern, $questionNumbers);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function matchImages(DOMDocument $document, string $directory, string $headingPattern, array $questionNumbers): array
    {
        $images = [];
        $warnings = [];
        $headingsInPdf = [];
        $unmatched = 0;
        $bytes = 0;

        foreach ($document->getElementsByTagName('page') as $page) {
            $headings = [];
            foreach ($page->getElementsByTagName('text') as $line) {
                if (preg_match($headingPattern, trim($line->textContent), $match, PREG_UNMATCHED_AS_NULL)) {
                    $headings[] = [
                        'number' => (int) ($match[1] ?? $match[3]),
                        'top' => (float) $line->getAttribute('top'),
                        'bottom' => (float) $line->getAttribute('top') + (float) $line->getAttribute('height'),
                    ];
                }
            }
            usort($headings, fn (array $a, array $b) => $a['top'] <=> $b['top']);
            array_push($headingsInPdf, ...array_column($headings, 'number'));

            $byQuestion = [];
            foreach ($page->getElementsByTagName('image') as $image) {
                $top = (float) $image->getAttribute('top');
                $bottom = $top + (float) $image->getAttribute('height');
                for ($index = count($headings) - 1; $index >= 0; $index--) {
                    if ($top >= $headings[$index]['bottom']
                        && (! isset($headings[$index + 1]) || $bottom <= $headings[$index + 1]['top'])) {
                        $byQuestion[$headings[$index]['number']][] = $image;

                        continue 2;
                    }
                }
                $unmatched++;
            }

            foreach ($byQuestion as $number => $candidates) {
                if (count($candidates) !== 1) {
                    $warnings[] = 'Soal '.$number.' memiliki beberapa gambar. Gabungkan menjadi satu gambar atau unggah melalui Edit Soal.';
                } elseif (($image = $this->readImage($candidates[0], $directory)) === null) {
                    $warnings[] = 'Gambar soal '.$number.' tidak terbaca atau melebihi 5 MB / 4.000 piksel per sisi.';
                } elseif ($bytes + strlen($image['contents']) > 8 * 1024 * 1024) {
                    $warnings[] = 'Gambar soal '.$number.' melewati batas total 8 MB gambar per impor.';
                } else {
                    $images[$number] = $image;
                    $bytes += strlen($image['contents']);
                }
            }
        }

        if ($headingsInPdf !== $questionNumbers) {
            return ['images' => [], 'warnings' => ['Posisi gambar belum dapat dicocokkan dengan nomor soal. Gunakan PDF satu kolom dengan judul soal pada baris tersendiri.']];
        }

        if ($unmatched > 0) {
            $warnings[] = $unmatched.' gambar berada di luar bagian soal. Letakkan gambar setelah judul soal pada halaman yang sama.';
        }

        return compact('images', 'warnings');
    }

    private function readImage(DOMElement $image, string $directory): ?array
    {
        $filename = $image->getAttribute('src');
        if (! preg_match('/\Alayout-\d+_\d+\.(?:png|jpe?g)\z/i', $filename)) {
            return null;
        }

        $path = $directory.'/'.$filename;
        if (! is_file($path) || filesize($path) > 5 * 1024 * 1024) {
            return null;
        }

        $contents = file_get_contents($path);
        $size = $contents === false ? false : @getimagesizefromstring($contents);
        $extension = match ($size[2] ?? null) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            default => null,
        };

        if ($extension === null || $size[0] > 4000 || $size[1] > 4000) {
            return null;
        }

        return ['contents' => $contents, 'extension' => $extension, 'mime' => $size['mime']];
    }

    private function unavailable(): array
    {
        return ['images' => [], 'warnings' => ['Pembaca gambar PDF tidak dapat dijalankan. Teks tetap bisa diimpor. Jika PDF berisi gambar, coba ulangi atau hubungi pengelola aplikasi.']];
    }
}
