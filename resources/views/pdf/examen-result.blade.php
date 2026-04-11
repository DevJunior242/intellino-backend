<!DOCTYPE html>
<html>

<head>
    <style>
        body {
            font-family: DejaVu Sans;
            font-size: 12px;
         }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }

        th {
            background: #eee;
            font-weight: bold;
            font-size: 12px;
        }

        .title {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }

       
    </style>
</head>

<body>
    <div  
        style="width: 100%; display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
        <!-- Partie gauche -->
        <div style="text-align: left;">
            <h1 style="margin:0; font-size:14px;">FEDERATION BURKINABE DE KARATE-DO</h1>
            <h2 style="margin:0; font-size:10px;">BULLETIN D’EXAMEN</h2>
        </div>

        <!-- Partie droite -->
        <div style="text-align: right; margin-top: -30px;">
            <h1 style="margin:0; font-size:14px;">Burkina Faso</h1>
            <h2 style="margin:0; font-size:10px;">Unité-Progrès-Justice</h2>
        </div>
    </div>

    <div class="title">
        BULLETIN D’EXAMEN <br>
        Grade : {{ $exam['grade'] }} | Date : {{ $exam['start_date'] }} au {{ $exam['end_date'] }}
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

                <td>{{ $s['moyenne'] ?? "N/A" }}</td>
                <td>{{ $s['rang'] ?? "N/A" }}</td>
                <td>{{ $s['passage'] ?? "N/A" }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

</body>

</html>