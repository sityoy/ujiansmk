<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantSetupController
{
    public function show()
    {
        return response()->view('tenants.setup')->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ticket'=>['required','regex:/^\d{10}\.[a-f0-9]{64}$/D'],
            'name'=>'required|string|max:160','email'=>'required|email|max:254',
            'password'=>'required|string|min:12|max:72|confirmed',
        ]);
        [$expires,$signature] = explode('.', $data['ticket']);
        $key = config('tenant.setup_key');
        abort_unless(is_string($key) && strlen($key) === 64 && $expires >= time() && $expires <= time()+600 &&
            hash_equals(hash_hmac('sha256','admin|'.config('tenant.npsn').'|'.$expires,$key),$signature),403,'Buka kembali tautan aktivasi dari portal Kepala Sekolah.');
        abort_if(strlen($data['password']) > 72,422,'Kata sandi maksimal 72 byte.');
        DB::transaction(function () use ($data) {
            $claimed = DB::table('tenant_activation')->where('id',1)->whereNull('consumed_at')->update(['consumed_at'=>now()]);
            abort_unless($claimed && !User::query()->exists(),409,'Admin pertama sudah tersedia. Masuk dengan akun CBT sekolah.');
            User::create(['name'=>$data['name'],'email'=>strtolower($data['email']),'password'=>$data['password'],
                'role'=>UserRole::SuperAdmin,'is_active'=>true,'must_change_password'=>false]);
        });
        return redirect('/login')->with('status','Admin sekolah siap. Masuk dengan email dan kata sandi CBT Anda.');
    }
}
