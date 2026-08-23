<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdminUser extends Command
{
    protected $signature = 'galak:admin
        {email? : Alamat email Super Admin}
        {--name= : Nama lengkap Super Admin}';

    protected $description = 'Membuat atau memperbarui akun Super Admin GALAK CBT';

    public function handle(): int
    {
        $email = Str::lower(trim((string) ($this->argument('email')
            ?: $this->ask('Alamat email Super Admin'))));
        $name = trim((string) ($this->option('name')
            ?: $this->ask('Nama lengkap')));
        $password = (string) $this->secret('Kata sandi (minimal 10 karakter, huruf besar, huruf kecil, dan angka)');

        $validator = Validator::make(
            compact('email', 'name', 'password'),
            [
                'email' => ['required', 'email', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', Password::min(10)->mixedCase()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing && ! $this->confirm('Akun sudah ada. Perbarui menjadi Super Admin dan ganti kata sandinya?', false)) {
            $this->warn('Tidak ada perubahan yang dilakukan.');

            return self::SUCCESS;
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->newLine();
        $this->info('Akun Super Admin berhasil disiapkan.');
        $this->table(['Nama', 'Email', 'Role'], [[$name, $email, UserRole::SuperAdmin->label()]]);

        return self::SUCCESS;
    }
}
