<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Masuk — {{ $schoolProfile?->name ?? 'Sistem Ujian Sekolah' }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    @php($schoolName = $schoolProfile?->name ?? 'SMK Islam Bahagia')

    <main class="login-shell">
        <div class="login-card">
            <section class="login-brand" aria-labelledby="login-brand-title">
                <div class="login-logos">
                    <div class="login-identity">
                        <div class="login-logo-tile">
                            <img src="{{ asset('images/logo-ypmi-bahagia.jpeg') }}" alt="Logo YPMI Bahagia"
                                width="1222" height="1198">
                        </div>
                        <p>{{ $schoolName }}</p>
                    </div>
                    <div class="login-identity">
                        <div class="login-logo-tile">
                            <img src="{{ asset('images/logo-pt-sityoy.png') }}" alt="Logo SIS"
                                width="1024" height="768">
                        </div>
                        <p>PT Sityoy IT Solutions</p>
                    </div>
                </div>
                <p id="login-brand-title" class="login-brand-title">Sistem Ujian Sekolah</p>
            </section>

            <section class="login-form-panel" aria-labelledby="login-title">
                <div class="login-form-content">
                    <h1 id="login-title">Masuk ke sistem</h1>
                    <p class="login-instructions">Gunakan akun yang diberikan oleh administrator atau panitia ujian.</p>

                    @if (session('status'))
                        <p class="login-notice login-notice-success" role="status">{{ session('status') }}</p>
                    @endif
                    @if ($errors->any())
                        <p class="login-notice login-notice-error" role="alert">{{ $errors->first() }}</p>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="login-form">
                        @csrf

                        <div class="login-field">
                            <label for="login">Email, NISN, atau NIS</label>
                            <input id="login" name="login" type="text" value="{{ old('login') }}" autocomplete="username"
                                required autofocus placeholder="Email untuk petugas · NISN/NIS untuk siswa">
                        </div>

                        <div class="login-field">
                            <label for="password">Kata sandi</label>
                            <x-password-input id="password" name="password" autocomplete="current-password" required
                                placeholder="Masukkan kata sandi" />
                        </div>

                        <label class="login-remember">
                            <input name="remember" type="checkbox" value="1" @checked(old('remember'))>
                            Ingat sesi pada perangkat ini
                        </label>

                        <button type="submit" class="login-submit">Masuk ke Sistem Ujian</button>
                    </form>

                    <p class="login-footer">Jaga kerahasiaan akun dan kata sandi ujian Anda.</p>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
