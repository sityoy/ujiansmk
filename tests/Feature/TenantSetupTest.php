<?php

namespace Tests\Feature;

use App\Http\Controllers\TenantSetupController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_a_current_school_ticket_can_claim_the_initial_admin_once(): void
    {
        $this->get('/aktivasi-admin')->assertNotFound(); // Standard CBT instance
        Route::post('/aktivasi-admin', [TenantSetupController::class, 'store'])->middleware('web');
        $secret = bin2hex(random_bytes(32));
        config(['tenant.npsn'=>'00112233','tenant.setup_key'=>$secret]);
        Schema::create('tenant_activation', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->timestamp('consumed_at')->nullable();
        });
        DB::table('tenant_activation')->insert(['id'=>1]);
        $form = ['name'=>'Pengelola','email'=>'pengelola@example.test',
            'password'=>'KataSandi-Uji-123','password_confirmation'=>'KataSandi-Uji-123'];
        $ticket = fn ($npsn,$expires) => $expires.'.'.hash_hmac('sha256',"admin|$npsn|$expires",$secret);
        $this->post('/aktivasi-admin',$form+['ticket'=>$ticket('00112234',time()+500)])->assertForbidden();
        $this->post('/aktivasi-admin',$form+['ticket'=>$ticket('00112233',time()-10)])->assertForbidden();
        $this->assertDatabaseCount('users',0);
        $valid = $form+['ticket'=>$ticket('00112233',time()+500)];
        $this->post('/aktivasi-admin',$valid)->assertRedirect('/login');
        $this->assertDatabaseHas('users',['role'=>'super_admin','email'=>$form['email']]);
        $this->post('/aktivasi-admin',$valid)->assertStatus(409);
        $this->assertDatabaseCount('users',1);
    }
}
