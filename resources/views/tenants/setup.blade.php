<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>Admin pertama CBT</title></head>
<body><main style="max-width:32rem;margin:7vh auto;padding:1rem;font:1.1rem/1.6 system-ui;color:#193759">
<h1>Siapkan admin CBT</h1><p>Akun ini mengelola ujian untuk sekolah dengan NPSN {{ config('tenant.npsn') }}.</p>
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<form method="post" action="/aktivasi-admin">@csrf
<input type="hidden" id="activation-ticket" name="ticket" value="{{ old('ticket') }}">
<p><label>Nama pengelola<br><input name="name" value="{{ old('name') }}" required></label></p>
<p><label>Email login CBT<br><input name="email" type="email" value="{{ old('email') }}" required></label></p>
<p><label>Kata sandi (minimal 12 karakter)<br><input name="password" type="password" minlength="12" autocomplete="new-password" required></label></p>
<p><label>Ulangi kata sandi<br><input name="password_confirmation" type="password" autocomplete="new-password" required></label></p>
<button>Buat admin sekolah</button></form><p><a href="/login">Masuk CBT</a></p></main>
<script>if(location.hash){document.getElementById('activation-ticket').value=location.hash.slice(1);history.replaceState(null,'',location.pathname);}</script>
</body></html>
