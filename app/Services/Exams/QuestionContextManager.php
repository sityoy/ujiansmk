<?php

namespace App\Services\Exams;

use App\Models\ExamQuestion;
use App\Models\QuestionContext;
use App\Models\TrialQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class QuestionContextManager
{
    public function __construct(private readonly QuestionText $text) {}

    public function rules(): array
    {
        return [
            'question_context' => ['nullable', 'string', 'regex:/^(?:new|edit|[1-9][0-9]{0,17})$/D'],
            'context_statement' => ['exclude_unless:question_context,new,edit', 'nullable', 'string', 'max:20000'],
            ...QuestionAttachments::rules('context_image', 'exclude_unless:question_context,new,edit'),
        ];
    }

    public function available(HasMany $questions): Collection
    {
        return QuestionContext::query()
            ->whereIn('id', $questions->reorder()->select('question_context_id'))
            ->orderByDesc('id')
            ->get();
    }

    public function resolve(array $data, Builder $scope, QuestionAttachments $attachments, ?int $currentId = null): ?int
    {
        if (! array_key_exists('question_context', $data)) {
            return $currentId;
        }

        $selection = $data['question_context'];
        if ($selection === null || $selection === '') {
            return null;
        }
        if (! in_array($selection, ['new', 'edit'], true)) {
            $id = (int) $selection;
            if (! (clone $scope)->where('question_context_id', $id)->exists()) {
                $this->invalid('question_context', 'Pilih pernyataan bersama dari bank soal ini.');
            }

            return $id;
        }

        $current = $selection === 'edit' && $currentId !== null
            ? QuestionContext::query()->lockForUpdate()->find($currentId)
            : null;
        if ($selection === 'edit' && ! $current) {
            $this->invalid('question_context', 'Soal ini belum menggunakan pernyataan bersama.');
        }

        $format = $current?->text_format ?? $data['text_format'];
        $statement = $current?->statement;
        if (array_key_exists('context_statement', $data)) {
            $format = $data['text_format'];
            try {
                $statement = $this->text->normalize([
                    'statement' => $data['context_statement'],
                    'text_format' => $format,
                ])['statement'];
            } catch (ValidationException $exception) {
                $this->invalid('context_statement', $exception->validator->errors()->first('statement'));
            }
        }
        [$imagePath, $imageCaption] = $attachments->resolve(
            $data, 'context_image', 'question-contexts', $current?->image_path, $current?->image_caption,
        );
        if ($statement === null && $imagePath === null) {
            if ($current && ($data['remove_context_image'] ?? false)) {
                (clone $scope)->where('question_context_id', $current->id)->update(['question_context_id' => null]);

                return null;
            }
            $this->invalid('context_statement', 'Isi pernyataan atau unggah gambar untuk kelompok soal ini.');
        }

        if ($current && $current->statement === $statement && $current->text_format === $format
            && $current->image_path === $imagePath && $current->image_caption === $imageCaption) {
            return $current->id;
        }

        $context = QuestionContext::query()->create([
            'statement' => $statement,
            'text_format' => $format,
            'image_path' => $imagePath,
            'image_caption' => $imageCaption,
        ]);
        if ($current) {
            // Rombel that were reassigned after sharing this bank keep their own exam material.
            (clone $scope)->where('question_context_id', $current->id)->update(['question_context_id' => $context->id]);
        }

        return $context->id;
    }

    public function release(?int $id, QuestionAttachments $attachments): void
    {
        if ($id === null) {
            return;
        }
        $context = QuestionContext::query()->lockForUpdate()->find($id);
        if (! $context || ExamQuestion::query()->where('question_context_id', $id)->exists()
            || TrialQuestion::query()->where('question_context_id', $id)->exists()) {
            return;
        }

        $path = $context->image_path;
        $context->delete();
        $attachments->cleanAfterCommit([$path]);
    }

    public function exportStatement(ExamQuestion|TrialQuestion $question): string
    {
        $context = $question->context;

        return implode("\n\n", array_filter([
            $context ? $this->text->plainText($context->statement, $context->text_format) : '',
            $this->text->plainText($question->statement, $question->text_format),
        ], fn (string $text): bool => $text !== ''));
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
