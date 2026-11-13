<?php

namespace Tests\Unit;

use App\Services\Exams\QuestionText;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QuestionTextTest extends TestCase
{
    public function test_formatted_text_keeps_allowed_styles_and_removes_executable_elements_and_attributes(): void
    {
        $formatter = new QuestionText;
        $html = '<p onclick="alert(1)" style="position:fixed"><strong>Bold</strong><em>Italic</em><u>Underlined</u><sup>2</sup><sub>n</sub></p>'
            .'<script>alert(1)</script><svg onload="alert(2)"><script>alert(3)</script></svg>'
            .'<iframe srcdoc="<script>alert(4)</script>"></iframe><img src=x onerror=alert(5)>'
            .'<ol><li>First</li><li>Second</li></ol>';
        $output = (string) $formatter->render($html, 'html');
        $this->assertStringContainsString('<strong>Bold</strong><em>Italic</em><u>Underlined</u><sup>2</sup><sub>n</sub>', $output);
        $this->assertStringContainsString('<ol><li>First</li><li>Second</li></ol>', $output);
        foreach (['script', 'onclick', 'style=', 'svg', 'iframe', 'onerror', 'alert(', '<img'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $output);
        }
        $this->assertSame('&lt;b&gt;literal&lt;/b&gt; &amp; 1 &lt; 2', (string) $formatter->render('<b>literal</b> & 1 < 2'));
    }

    public function test_visible_length_is_checked_after_sanitization_without_truncating_unicode_text(): void
    {
        $formatter = new QuestionText;
        $text = str_repeat('é', 4999).'¡';
        $data = $formatter->normalize(['text_format' => 'html', 'question_type' => 'essay', 'question_text' => '<p><strong>'.$text.'</strong></p>']);
        $this->assertSame($text, $formatter->plainText($data['question_text'], 'html'));
        $this->expectException(ValidationException::class);
        $formatter->normalize(['text_format' => 'html', 'question_text' => '<p>'.str_repeat('x', 5001).'</p>']);
    }

    public function test_blank_rich_text_cannot_bypass_required_question_validation(): void
    {
        $this->expectException(ValidationException::class);
        (new QuestionText)->normalize(['text_format' => 'html', 'question_text' => '<p><br>&nbsp;</p>']);
    }

    public function test_export_produces_readable_plain_text(): void
    {
        $formatter = new QuestionText;
        $this->assertSame("First paragraph\nSecond line\nLast line", $formatter->plainText(
            '<p><strong>First paragraph</strong></p><p>Second line<br>Last line</p>', 'html',
        ));
        $data = $formatter->normalize(['text_format' => 'html', 'question_type' => 'essay', 'question_text' => 'A valid question', 'statement' => '<p><br></p>']);
        $this->assertNull($data['statement']);
    }
}
