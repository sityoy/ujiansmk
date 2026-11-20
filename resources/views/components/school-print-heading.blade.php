@props(['school' => null, 'title'])

<img class="brand-logo" src="{{ asset('images/logo-ypmi-bahagia.jpeg') }}" alt="Logo YPMI Bahagia" width="46" height="46">
<div class="brand-copy">
    <div class="school">{{ $school?->name ?? config('app.name') }}</div>
    <div class="title">{{ $title }}</div>
</div>
