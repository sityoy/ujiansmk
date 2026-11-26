@php
    $hasBreakdown = collect($groups)->contains(fn ($group) => count($group['columns']) > 1);
    $scoreColumns = array_sum(array_map(fn ($group) => count($group['columns']), $groups));
    $totalColumns = ($compact ? 4 : 5) + $scoreColumns;
    $rowspan = $hasBreakdown ? 2 : 1;
@endphp
<table @if ($compact) style="--score-columns: {{ $scoreColumns }}" @endif>
    <colgroup>
        <col class="row-number">
        @unless ($compact)<col class="identifier">@endunless
        <col class="student-name">
    </colgroup>
    @foreach ($groups as $group)
        <colgroup span="{{ count($group['columns']) }}"></colgroup>
    @endforeach
    <colgroup><col class="average"><col class="rank"></colgroup>
    <thead>
        <tr>
            <th scope="col" rowspan="{{ $rowspan }}">No.</th>
            @unless ($compact)<th scope="col" rowspan="{{ $rowspan }}">NIS/NISN</th>@endunless
            <th class="name" scope="col" rowspan="{{ $rowspan }}">
                Nama Peserta Didik
                @if ($compact)<small class="student-id">NIS / NISN</small>@endif
            </th>
            @foreach ($groups as ['subject' => $subject, 'columns' => $columns])
                @if (count($columns) > 1)
                    <th scope="colgroup" colspan="{{ count($columns) }}">{{ $subject->subject->name }}</th>
                @else
                    <th scope="col" rowspan="{{ $rowspan }}">{{ $subject->subject->name }}</th>
                @endif
            @endforeach
            <th scope="col" rowspan="{{ $rowspan }}">Rata-rata</th>
            <th scope="col" rowspan="{{ $rowspan }}">Rank</th>
        </tr>
        @if ($hasBreakdown)
            <tr>
                @foreach ($groups as $group)
                    @if (count($group['columns']) > 1)
                        @foreach ($group['columns'] as $key => $label)
                            <th scope="col" @class(['score-heading', 'final-score' => $key === 'final'])>{{ $label }}</th>
                        @endforeach
                    @endif
                @endforeach
            </tr>
        @endif
    </thead>
    <tbody>
        @forelse ($rows as $index => $row)
            <tr>
                <td>{{ $index + 1 }}</td>
                @unless ($compact)
                    <td>{{ $row['student']->student_number }}<br>{{ $row['student']->nisn ?? '—' }}</td>
                @endunless
                <td class="name">
                    {{ $row['student']->full_name }}
                    @if ($compact)
                        <small class="student-id">{{ $row['student']->student_number }} / {{ $row['student']->nisn ?? '—' }}</small>
                    @endif
                </td>
                @foreach ($groups as ['subject' => $subject, 'columns' => $columns])
                    @php
                        $result = $row['results'][$subject->id] ?? null;
                    @endphp
                    @foreach ($columns as $key => $label)
                        @php
                            $final = $key === 'final';
                            $score = $final ? ($result['final'] ?? null) : ($result['components'][$key] ?? null);
                            $waiting = $result && $result['final'] === null && ($final || array_key_exists($key, $result['components']));
                        @endphp
                        <td @class(['final-score' => $final])>
                            @if ($score !== null)
                                {{ number_format($score, 2, ',', '.') }}
                            @elseif ($waiting)
                                <span class="pending">Menunggu koreksi</span>
                            @else
                                —
                            @endif
                        </td>
                    @endforeach
                @endforeach
                <td>
                    {{ $row['average'] === null ? '—' : number_format($row['average'], 2, ',', '.') }}
                    @if ($row['average'] !== null && ! $row['complete'])<span class="pending">Sementara</span>@endif
                </td>
                <td>{{ $row['rank'] ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ $totalColumns }}">Belum ada peserta pada kelas ini.</td></tr>
        @endforelse
    </tbody>
</table>
