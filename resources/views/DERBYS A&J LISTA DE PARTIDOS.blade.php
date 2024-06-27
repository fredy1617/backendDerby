<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>DERBYS A&J LISTA DE PARTIDOS</title>
    <style>
      .clearfix:after {
        content: "";
        display: table;
        clear: both;
      }

      header {
        padding: 10px 0px;
      }

      .column-1 {
        width: 20%;
        float: left;
        text-align: center;
        border: 2.5px solid #D1D1D1;
      }

      .column-2 {
        width: 79%;
        float: left;
        border: 2.5px solid #D1D1D1;
      }

      .row{
        display: table;
        width: 100%;
        clear: both;
      }
      
      a {
        color: #5D6975;
        text-decoration: underline;
      }

      body {
        position: relative;
        width: 19cm;  
        height: 29.7cm; 
        margin: 0 auto; 
        color: #001028;
        background: #FFFFFF; 
        font-size: 11px; 
        font-family: "Arial Narrow", Arial, sans-serif;
      }
      
      #logo {
        flex: 3;
        text-align: left;       
      }     

      .logoImg{
        width: 100%;
        margin: 0 0 0 0;
      }

      .blueTitle {
        font-family: "Arial Narrow", Arial, sans-serif;
        color: #4A3800;
        font-size: 3em;
        font-weight: bold;
        text-align: center;
        margin: 10px 0 10px 0;
      }

      .title {
        font-family: "Arial Narrow", Arial, sans-serif;
        color: #000000;
        font-size: 1.5em;
        line-height: 1.4em;
        font-weight: bold;
        text-align: center;
        margin: 10px 0 10px 0;
      }
      
      table {
        border-collapse: collapse;
        border-spacing: 0;
        margin-bottom: 10px;
      }

      table th {
        font-family: "Arial Narrow", Arial, sans-serif;
        text-align: center;
        padding: 0px 5px;
        color: #FFFFFF;
        font-size: 1.6em;
        border: 1px solid #000;      
        font-weight: bold;
        background: #000;
      }

      table td {
        padding: 0px 5px;
        border: 1px solid #000;
        font-size: 1.2em;
      }     
    </style>
  </head>
  <body>
    <header class="clearfix">
      <div class="column-1">
        <div id="logo">
          <img class="logoImg" src="{{ $logoImage }}">
        </div>
      </div>
      <div class="column-2">
        <p class="blueTitle">DERBYS A&J</p>
        <p class="title">LISTA DE PARTIDOS</p>
      </div>      
    </header>
    <main>    
      <div class="row">
        <p class="title">DERBY:</p>
        <table style="width: 100%; border: 2px solid;">
            <tr>
                <td><b>Nombre</b></td><td  colspan="2">{{ $derby->name }}</td>
                <td><b>Fecha</b></td><td  colspan="2">{{ $derby->date }}</td>
                <td><b>Entrada</b></td><td>${{ $derby->money }}</td>
            </tr>
            <tr>
                <td><b>N° Gallos</b></td><td>{{ $derby->no_roosters }} Gallos</td>
                <td><b>Tolerancia</b></td><td>{{ $derby->tolerance }}g</td>
                <td><b>Peso Min.</b></td><td>{{ $derby->min_weight }}g</td>
                <td><b>Peso Max.</b></td><td>{{ $derby->max_weight }}g</td>
            </tr>
        </table>
      </div>
      <div class="row">
        <table style="width: 100%; border: 2px solid;">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>NOMBRE DEL PARTIDO</th>
                    <th>GALLOS</th>
                </tr>
            </thead>
            <tbody>
                @php $index = 0; @endphp
                @foreach ($partidos as $match)
                    @php $index++; @endphp
                    <tr>
                        <td>{{ $index }}</td>
                        <td>{{ $match->name }}</td>
                        <td>
                            <ul>
                                @if($match->roosters && count($match->roosters) > 0)
                                    @foreach($match->roosters as $rooster)
                                        <li><b>{{ $rooster->name }}</b> - Anillo: {{ $rooster->ring }}, Peso: {{ $rooster->weight }}</li>
                                    @endforeach
                                @else
                                    <li>No hay gallos registrados para este partido</li>
                                @endif
                            </ul>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
      </div>       
    </main>
  </body>
</html>