@props(['school' => null, 'title'])

<img class="brand-logo" src="{{ asset(config('tenant.npsn') ? 'images/logo-pt-sityoy.png' : 'images/logo-ypmi-bahagia.jpeg') }}" alt="Logo" width="46" height="46">
<div class="brand-copy">
    <div class="school">{{ $school?->name ?? config('app.name') }}</div>
    <div class="title">{{ $title }}</div>
</div>
