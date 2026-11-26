<?php

namespace App\Services\Exams;

use App\Enums\ExamQuestionType;
use App\Models\ExamQuestion;
use App\Models\QuestionContext;
use App\Models\TrialQuestion;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuestionAttachments
{
    private const OPTIONS = ['A', 'B', 'C', 'D', 'E'];

    private array $uploaded = [];

    public function save(Closure $operation): mixed
    {
        try {
            return DB::transaction($operation);
        } catch (Throwable $exception) {
            rescue(fn () => $this->deleteUnused($this->uploaded));
            throw $exception;
        } finally {
            $this->uploaded = [];
        }
    }

    public static function rules(string $field, ?string $condition = null): array
    {
        $conditions = $condition ? [$condition] : [];

        return [
            $field => [...$conditions, 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            $field.'_caption' => [...$conditions, 'nullable', 'string', 'max:500'],
            'remove_'.$field => [...$conditions, 'nullable', 'boolean'],
        ];
    }

    public static function questionRules(): array
    {
        $rules = self::rules('question_image');
        foreach (self::OPTIONS as $option) {
            $rules += self::rules('option_file_'.strtolower($option), 'exclude_unless:question_type,multiple_choice');
        }

        return $rules;
    }

    public function resolve(
        array $input, string $field, string $directory, ?string $current = null, ?string $caption = null,
    ): array {
        if ($file = $input[$field] ?? null) {
            $current = $file->store($directory, 'public');
            if (! $current) {
                throw ValidationException::withMessages([$field => 'Lampiran gagal disimpan. Pilih kembali file lalu coba simpan.']);
            }
            $this->uploaded[] = $current;
        } elseif ($input['remove_'.$field] ?? false) {
            return [null, null];
        }

        if (array_key_exists($field.'_caption', $input)) {
            $caption = $input[$field.'_caption'];
        }

        return [$current, $current ? $caption : null];
    }

    public function questionAttributes(array $input, string $prefix, ExamQuestion|TrialQuestion|null $question = null): array
    {
        [$path, $caption] = $this->resolve(
            $input, 'question_image', $prefix.'-questions', $question?->question_image_path, $question?->question_image_caption,
        );
        $optionPaths = $optionCaptions = [];
        if ($input['question_type'] === ExamQuestionType::MultipleChoice->value) {
            foreach (self::OPTIONS as $letter) {
                [$optionPath, $optionCaption] = $this->resolve(
                    $input, 'option_file_'.strtolower($letter), $prefix.'-options',
                    $question?->option_file_paths[$letter] ?? null, $question?->option_file_captions[$letter] ?? null,
                );
                if ($optionPath !== null) {
                    $optionPaths[$letter] = $optionPath;
                }
                if ($optionCaption !== null) {
                    $optionCaptions[$letter] = $optionCaption;
                }
            }
        }

        return [
            'question_image_path' => $path,
            'question_image_caption' => $caption,
            'option_file_paths' => $optionPaths ?: null,
            'option_file_captions' => $optionCaptions ?: null,
        ];
    }

    public function cleanAfterCommit(array $paths, array $retained = []): void
    {
        $obsolete = array_diff(array_unique(array_filter($paths)), $retained);
        if ($obsolete !== []) {
            DB::afterCommit(fn () => rescue(fn () => $this->deleteUnused($obsolete)));
        }
    }

    private function deleteUnused(array $paths): void
    {
        foreach ($paths as $path) {
            if (! $this->usedBy(ExamQuestion::class, $path) && ! $this->usedBy(TrialQuestion::class, $path)
                && ! QuestionContext::where('image_path', $path)->exists()) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    private function usedBy(string $model, string $path): bool
    {
        $query = $model::query()->where('question_image_path', $path);
        foreach (self::OPTIONS as $letter) {
            $query->orWhere('option_file_paths->'.$letter, $path);
        }

        return $query->exists();
    }
}
