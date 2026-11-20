@props(['incident', 'forPrint' => false])

@php
    $category = data_get($incident, 'category');
    $details = data_get($incident, 'details', []) ?? [];
    $occurredAt = data_get($incident, 'occurred_at');
    $label = [
        'tab_hidden' => 'Berpindah tab/aplikasi',
        'fullscreen_exit' => 'Keluar layar penuh',
        'window_blur' => 'Fokus hilang (bubble/notifikasi)',
        'system_gesture' => 'Membuka bilah sistem/notifikasi',
        'viewport_change' => 'Perubahan layar mencurigakan/split screen',
        'restricted_action' => 'Tombol atau gestur terlarang',
        'navigation_attempt' => 'Mencoba keluar dengan tombol kembali',
        'supervisor_resume' => 'Diizinkan lanjut',
        'supervisor_submit' => 'Dikumpulkan pengawas',
        'supervisor_reset' => 'Hitungan pelanggaran direset',
        'supervisor_restart' => 'Ujian diulang oleh pengawas',
    ][$category] ?? $category;
@endphp

{{ $occurredAt ? \Illuminate\Support\Carbon::parse($occurredAt)->format($forPrint ? 'd/m/Y H:i:s' : 'd/m H:i:s') : '—' }} · {{ $label }}
@if (array_key_exists('counted', $details)) · {{ $details['counted'] ? 'Dihitung' : 'Sinyal bersamaan, tidak dihitung ulang' }} @endif
@if (! $forPrint && isset($details['context']['viewport_width'], $details['context']['viewport_height']))
    <br><span class="text-slate-500">Layar {{ $details['context']['viewport_width'] }}×{{ $details['context']['viewport_height'] }} px</span>
@endif
@if (isset($details['reason']))<br>{{ $details['reviewer_name'] ?? 'Pengawas' }}: {{ $details['reason'] }}
@elseif ($category === 'supervisor_restart' && isset($details['reviewer_name'])) · {{ $details['reviewer_name'] }}
@endif
@if (isset($details['reset_number'])) · Pemulihan {{ $details['reset_number'] }}/2 @endif
