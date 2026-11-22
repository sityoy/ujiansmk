@props(['student'])

<div class="account">
    @if ($student->user)
        @php
            $username = $student->user->username ?: $student->user->email;
            $kind = match ($username) {
                $student->student_number => 'NIS',
                $student->nisn => 'NISN',
                default => null,
            };
        @endphp
        <!-- <div><span>Username{{ $kind ? ' ('.$kind.')' : '' }}</span><strong>{{ $username }}</strong></div>
        <div><span>Password</span><strong>{{ $student->printablePassword() ?? 'Konfirmasi ke panitia' }}</strong></div>
        <p>Gunakan {{ $kind ? $kind.' sebagai username' : 'akun ini' }} pada <a href="{{ route('login') }}">halaman login CBT</a>. Rahasiakan password.</p> -->
    @else
        <p>Akun CBT belum tersedia. Hubungi panitia.</p>
    @endif
</div>
