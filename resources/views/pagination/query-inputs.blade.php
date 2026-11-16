@foreach ($values as $key => $value)
    @php($name = $prefix === null ? (string) $key : $prefix.'['.$key.']')
    @if (is_array($value))
        @include('pagination.query-inputs', ['values' => $value, 'prefix' => $name])
    @elseif (is_scalar($value))
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endif
@endforeach
