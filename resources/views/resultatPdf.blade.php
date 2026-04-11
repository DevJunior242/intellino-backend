<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: DejaVu Sans; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 5px; text-align: center; }
        th { background: #eee; }
        .title { text-align: center; font-size: 18px; font-weight: bold; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="title">
    BULLETIN D’EXAMEN <br>
    Grade : {{ $exam['grade'] }} | Date : {{ $exam['date'] }}
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Nom</th>
            <th>Grade</th>
            @foreach(array_keys($students[0]) as $key)
                @if(!in_array($key,['id','fullname','birthdate','moyenne','rang','passage']))
                    <th>{{ $key }}</th>
                @endif
            @endforeach
            <th>Moyenne /100</th>
            <th>Rang</th>
            <th>Décision</th>
        </tr>
    </thead>

    <tbody>
        @foreach($students as $i => $s)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $s['fullname'] }}</td>
                <td>{{ $exam['grade'] }}</td>

                @foreach($s as $k => $v)
                    @if(!in_array($k,['id','fullname','birthdate','moyenne','rang','passage']))
                        <td>{{ $v }}</td>
                    @endif
                @endforeach

                <td>{{ $s['moyenne'] }}</td>
                <td>{{ $s['rang'] }}</td>
                <td>{{ $s['passage'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
