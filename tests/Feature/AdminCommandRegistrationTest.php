<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AdminCommandRegistrationTest extends TestCase
{
    public function test_school_admin_command_is_registered(): void
    {
        $this->assertArrayHasKey('school:admin', Artisan::all());
    }

    public function test_exam_readiness_command_is_registered(): void
    {
        $this->assertArrayHasKey('exams:readiness', Artisan::all());
    }
}
