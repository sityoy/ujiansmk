<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\TablePagination;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TablePaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_all_page_sizes_preserve_the_filtered_query_and_numbered_links(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        User::factory()->count(61)->create(['role' => UserRole::Teacher, 'name' => 'Guru Pilihan']);
        User::factory()->create(['role' => UserRole::Teacher, 'name' => 'Guru Di Luar Filter']);
        User::factory()->create(['role' => UserRole::Student, 'name' => 'Guru Pilihan']);
        $this->actingAs($admin);
        foreach (['10' => 10, '15' => 15, '20' => 20, '50' => 50, 'all' => 61] as $size => $count) {
            $response = $this->get(route('users.index', ['role' => 'teacher', 'q' => 'Guru Pilihan', 'per_page' => $size]));
            $response->assertOk()->assertViewHas('users', fn ($users): bool => $users->count() === $count && $users->total() === 61)
                ->assertDontSee('Guru Di Luar Filter')->assertSee('Semua');
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(['10', '15', '20', '50', 'all'], array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($xpath->query('//select[@name="per_page"]/option'))));
            $this->assertSame((string) $size, $xpath->query('//select[@name="per_page"]/option[@selected]')->item(0)->getAttribute('value'));
            if ($size !== 'all') {
                $response->assertSee('Halaman 2')->assertSee('per_page='.$size, false)->assertSee('role=teacher', false);
            } else {
                $this->assertSame(0, $xpath->query('//nav[@aria-label="Halaman data"]')->length);
            }
        }
        $this->get(route('users.index', ['per_page' => 'all', 'page' => 999, 'role' => 'teacher', 'q' => 'Guru Pilihan']))
            ->assertOk()->assertViewHas('users', fn ($users): bool => $users->currentPage() === 1 && $users->count() === 61);
    }

    public function test_empty_results_and_invalid_sizes_keep_a_working_selector(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $this->actingAs($admin);
        foreach (['-1', '0', '999999', 'bad', ['all']] as $invalid) {
            $this->get(route('users.index', ['per_page' => $invalid]))->assertOk()
                ->assertViewHas('users', fn ($users): bool => $users->perPage() === 10);
        }
        $this->get(route('users.index', ['per_page' => 'all', 'q' => 'Tidak Ada Akun Ini']))->assertOk()
            ->assertViewHas('users', fn ($users): bool => $users->count() === 0 && $users->total() === 0 && $users->currentPage() === 1)
            ->assertSee('Data per halaman')->assertSee('Semua')->assertSee('Menampilkan 0–0 dari 0 data');
    }

    public function test_multiple_tables_keep_independent_sizes_pages_filters_and_anchors(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $year = AcademicYear::create(['name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_active' => true]);
        for ($i = 1; $i <= 31; $i++) {
            $class = SchoolClass::create(['academic_year_id' => $year->id, 'name' => 'X-'.$i, 'grade_level' => 10]);
            if ($i <= 26) {
                Student::create(['school_class_id' => $class->id, 'student_number' => 'S'.$i, 'full_name' => 'Siswa '.$i, 'is_active' => true]);
            }
        }
        $query = ['class_year' => $year->id, 'class_page' => 2, 'class_per_page' => '15', 'student_page' => 2, 'student_per_page' => '20', 'student_search' => 'Siswa', 'extra' => ['x' => ['y' => 'keep']]];
        $response = $this->actingAs($admin)->get(route('academic.index', $query))->assertOk()
            ->assertViewHas('classes', fn ($classes): bool => $classes->count() === 15 && $classes->currentPage() === 2 && $classes->perPage() === 15)
            ->assertViewHas('students', fn ($students): bool => $students->count() === 6 && $students->currentPage() === 2 && $students->perPage() === 20);
        $xpath = $this->xpath($response->getContent());
        $classForm = '//div[@data-table-pagination="class_page"]//form';
        $this->assertSame(0, $xpath->query($classForm.'//input[@name="class_page"]')->length);
        $this->assertSame('2', $xpath->query($classForm.'//input[@name="student_page"]')->item(0)->getAttribute('value'));
        $this->assertSame('keep', $xpath->query($classForm.'//input[@name="extra[x][y]"]')->item(0)->getAttribute('value'));
        $this->assertStringEndsWith('#class-list', $xpath->query($classForm)->item(0)->getAttribute('action'));
        $this->assertStringEndsWith('#student-list', $xpath->query('//div[@data-table-pagination="student_page"]//form')->item(0)->getAttribute('action'));
        $this->get(route('academic.index', [...$query, 'class_per_page' => 'all', 'class_page' => 999]))->assertOk()
            ->assertViewHas('classes', fn ($classes): bool => $classes->count() === 31 && $classes->currentPage() === 1)
            ->assertViewHas('students', fn ($students): bool => $students->count() === 6 && $students->currentPage() === 2);
    }

    public function test_collection_pagination_supports_all_and_named_page_controls(): void
    {
        $request = Request::create('/sample?sessions_per_page=all&sessions_page=999&attempts_page=3');
        $this->app->instance('request', $request);
        $all = TablePagination::collection(collect(range(1, 73)), 'sessions_page');
        $this->assertCount(73, $all);
        $this->assertSame(1, $all->currentPage());
        $this->assertSame(73, $all->total());
        $this->assertStringContainsString('attempts_page=3', $all->url(1));
        $this->assertCount(0, TablePagination::collection(collect(), 'sessions_page'));
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }
}
