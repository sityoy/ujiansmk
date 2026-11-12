<?php

namespace App\Services\Exams;

use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class QuestionText
{
    private readonly HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)->withMaxInputLength(80000);
        foreach (['p', 'div', 'br', 'span', 'strong', 'b', 'em', 'i', 'u', 's', 'sup', 'sub', 'ol', 'ul', 'li', 'blockquote'] as $tag) {
            $config = $config->allowElement($tag);
        }
        foreach (['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template'] as $tag) {
            $config = $config->dropElement($tag);
        }
        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function normalize(array $data): array
    {
        $format = $data['text_format'] ?? 'plain';
        $limits = ['question_text' => 5000, 'statement' => 5000];
        foreach (['a', 'b', 'c', 'd', 'e'] as $option) {
            $limits['option_'.$option] = 2000;
        }
        $errors = [];
        $multipleChoice = ($data['question_type'] ?? 'multiple_choice') === 'multiple_choice';
        foreach ($limits as $field => $limit) {
            if (! isset($data[$field])) {
                continue;
            }
            $value = $format === 'html' ? $this->sanitizer->sanitize($data[$field]) : $data[$field];
            $plain = $this->plainText($value, $format);
            if (mb_strlen($plain) > $limit) {
                $errors[$field] = 'Teks maksimal '.$limit.' karakter.';
            }
            if (trim($plain) === '') {
                $required = $field === 'question_text' || ($multipleChoice && str_starts_with($field, 'option_'));
                $value = $required ? '' : null;
                if ($required) {
                    $errors[$field] = 'Teks pertanyaan atau pilihan wajib diisi.';
                }
            }
            $data[$field] = $value;
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $data['text_format'] = $format;

        return $data;
    }

    public function render(?string $value, string $format = 'plain'): HtmlString
    {
        return new HtmlString($format === 'html'
            ? $this->sanitizer->sanitize($value ?? '')
            : htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    public function plainText(?string $value, string $format = 'plain'): string
    {
        if ($format !== 'html') {
            return $value ?? '';
        }

        $html = $this->sanitizer->sanitize($value ?? '');
        $html = preg_replace('~<br\s*/?>|</(?:p|div|li|blockquote)>~i', "\n", $html);

        return trim(str_replace("\u{00a0}", ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
