<?php

namespace App\Support;

use App\Models\AssessmentSubject;
use App\Models\ExamQuestion;
use App\Models\TrialQuestion;
use App\Models\TrialQuestionBank;

class QuestionBankNavigation
{
    public static function questionUrl(AssessmentSubject|TrialQuestionBank $bank, ExamQuestion|TrialQuestion $question): string
    {
        $size = TablePagination::selected();
        $page = $size === 'all' ? 1
            : intdiv($bank->questions()->where('position', '<', $question->position)->count(), (int) $size) + 1;
        $route = $bank instanceof TrialQuestionBank ? 'trial-question-banks.questions.index' : 'scheduling.questions.index';

        return route($route, [$bank, 'page' => $page, 'per_page' => $size]).'#bank-question-'.$question->id;
    }
}
