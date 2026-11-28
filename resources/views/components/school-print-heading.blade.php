@props(['school' => null, 'title'])

<x-school-logo :school="$school" class="brand-logo" width="46" height="46" />
<div class="brand-copy">
    <div class="school">{{ $school?->name ?? config('app.name') }}</div>
    <div class="title">{{ $title }}</div>
</div>
