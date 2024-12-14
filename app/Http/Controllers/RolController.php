<?php

namespace App\Http\Controllers;

use App\Models\Derbys;
use App\Models\Matchs;
use App\Models\Rol;
use App\Models\Roosters;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class RolController extends Controller
{
    public function store(Request $request)
    {
        // Buscar el derby por su ID, el cual se pasa a través de la solicitud.
        $derby = Derbys::findOrFail($request->id);

        // Definir las opciones de ordenación para los gallos.
        $sortingOptions = [
            ['ring', 'DESC'],
            ['weight', 'DESC'],
            ['name', 'DESC'],
        ];
        
        // Eliminar roles anteriores del derby ANTES DE GENERAR UNO NUEVO
        Rol::where('derby_id', $derby->id)->delete();

        // Intentar generar los enfrentamientos, pasando el derby y las opciones de ordenación.
        $response = $this->tryGeneratingEnfrentamientos($derby, $sortingOptions);

         // Si la respuesta es positiva, significa que se generaron enfrentamientos exitosamente.
        if ($response) {
            LOGGER('ROL GENERADO CON EXITO');

            // Recorrer las rondas generadas y agregar los enfrentamientos a la base de datos.
            $data = $response->getData()->RONDAS;
            foreach ($data as $ronda) {
                $enfrentamientos = $ronda->PELEAS;
                $this->agregarTabla($enfrentamientos);
            }

            // Devolver una respuesta con los enfrentamientos generados correctamente.
            return response()->json([
                'success' => true,
                'message' => $response->getData()->message,
                'RONDAS' => $response->getData()->RONDAS,
            ]);
        }

        // Si no se pudieron generar los enfrentamientos, devolver un error.
        LOGGER('ERROR PELEAS SIN EMPAREJAR DESPUÉS DE INTENTAR TODAS LAS OPCIONES');
        return response()->json([
            'success' => false,
            'message' => 'No se pudieron generar enfrentamientos emparejados después de intentar todas las opciones.',
        ], 500);

    }

    private function getInfoXRondas($derby, $by, $order)
    {
        // Obtener todos los partidos para el derby
        $partidos = Matchs::select('id')
                            ->where('derby_id', $derby->id)->get();
        
         // Recorrer los partidos para obtener la información de los gallos
        foreach ($partidos as $partido) {
            $gallos = Roosters::select('id', 'ring', 'weight', 'match_id')
                            ->where('match_id', $partido->id)
                            ->orderBy($by, $order)
                            ->get();
            $partido['gallos']=$gallos;            
        }

        // Combinar los gallos de todos los partidos
        $gallos = new Collection();
            foreach ($partidos as $partido) {
                $gallos = $gallos->merge($partido->gallos);
        }

        // Verificar si hay un número impar de gallos, y si es así, agregar un gallo extra
        $no_gallos = count($gallos);// Calcular el número de gallos)
        if ($no_gallos % 2 != 0) {
            $gallos[] = [
                'id' => '0',
                'ring' => 'EXTRA',
                'weight' => $derby->max_weight,
                'match_id' => '0',
                'group_id' => '0',
            ];
        }

         // Asignar un grupo a cada gallo
        $grupo = 1;
        foreach ($gallos as $gallo) {
            $grupo --;
            $group_id = Matchs::where('matchs.id', $gallo['match_id'])
            ->join('group_matches', 'matchs.id', '=', 'group_matches.match_id')
            ->value('group_matches.group_id') ?? $grupo;
            $gallo['group_id'] = $group_id;
        }

        return ($gallos);       
    }

    private function generateRondas($derby, $gallos)
    {
        $Rondas = [];// Inicializar el arreglo de rondas.
        $totalGallos = count($gallos);

        // Determinar cuántos gallos por ronda basándonos en el total de gallos y el número de rondas
        $baseGallosPorRonda = floor($totalGallos / $derby->no_roosters);// Gallos aproximados por ronda
        //LOGGER('TOTAL GALLOS: '.$totalGallos);
        //LOGGER('GALLOS POR RONDA (APORX): '.$baseGallosPorRonda);

        // Asignar gallos a las rondas, distribuyendo el resto si es necesario
        for ($i = 0; $i < $derby->no_roosters; $i++) {
            // Si el número base de gallos es impar, alternamos la distribución
            $cantidadGallos = $baseGallosPorRonda;
            if ($cantidadGallos % 2 != 0) {
                 // Si el índice de la ronda es par, sumamos 1 gallo, si es impar, restamos 1
                 $cantidadGallos = ($i % 2 == 0) ? $cantidadGallos + 1 : $cantidadGallos - 1;
            }
            $ronda = [
                'no' => $i, // Número de la ronda
                'cantidadGallos' => $cantidadGallos, // Número de la ronda
                'gallos' => [], // Inicializar arreglo para los gallos de esta ronda
            ];

            //LOGGER('GALLOS POR RONDA: '.$cantidadGallos);
            // Recorrer todos los gallos y agregar los gallos según el patrón deseado
            foreach ($gallos as $index => $gallo) {
                if ($index % $derby->no_roosters == $i) {
                    $ronda['gallos'][] = [
                        'id' => $gallo['id'],
                        'ring' => $gallo['ring'],
                        'weight' => $gallo['weight'],
                        'match_id' => $gallo['match_id'],
                        'group_id' => $gallo['group_id'],
                    ];
                }
            }
            //LOGGER('GALLLOS QUE SE ASIGNARON: '.count($ronda['gallos']).' DE LA RONDA N° '.$i + 1);
            // Agregar la ronda al arreglo de Rondas
            $Rondas[] = $ronda;
        }

        //SI ES IMPAR HACEMOS EL BALANCE DE GALLOS
        if ($baseGallosPorRonda % 2 != 0) {
            $this->balanceRounds($Rondas);
        }        

        //Logger($Rondas) ;
        return $Rondas;
    }

    // Función auxiliar para equilibrar las rondas y sus enfrentamientos
    private function balanceRounds(&$Rondas)
    {
        // Ordenar las rondas por menor cantidad de gallos
        usort($Rondas, function ($a, $b) {
            return $a['cantidadGallos'] - $b['cantidadGallos'];
        });
    
        // Lista de gallos sobrantes para redistribuir
        $gallosExtra = [];
    
        // Recorrer las rondas para ajustar
        foreach ($Rondas as &$ronda) {
            $gallosActuales = count($ronda['gallos']);
            $diferencia = $gallosActuales - $ronda['cantidadGallos'];
            //LOGGER('GALLOS DE LA RONDA: '. $ronda['cantidadGallos']. ' TIENE ACTUALMENTE: '.$gallosActuales). ' DIFERENCIA: '.$diferencia;
    
            if ($diferencia > 0) {
                // Sobran gallos, moverlos a la lista de gallos extra de manera aleatoria
                for ($k = 0; $k < $diferencia; $k++) {
                    $randomIndex = array_rand($ronda['gallos']); // Índice aleatorio
                    $gallosExtra[] = $ronda['gallos'][$randomIndex];
                    unset($ronda['gallos'][$randomIndex]);
                    $ronda['gallos'] = array_values($ronda['gallos']); // Reindexar el arreglo
                }
            } elseif ($diferencia < 0) {
                // Faltan gallos, tomar de la lista de gallos extra
                $faltan = abs($diferencia);
                while ($faltan > 0 && !empty($gallosExtra)) {
                    $ronda['gallos'][] = array_shift($gallosExtra);
                    $faltan--;
                }
            }

            //LOGGER('GALLOS DE LA RONDA: '. $ronda['cantidadGallos']. ' TIENE ACTUALMENTE BALANCE: '.count($ronda['gallos']));
            //LOGGER('GALLOS EXTRA LISTA: ');
            //LOGGER($gallosExtra);
        }
    
        // Verificar que todas las rondas tengan un número par de gallos
        foreach ($Rondas as &$ronda) {
            if (count($ronda['gallos']) % 2 != 0 && !empty($gallosExtra)) {
                $ronda['gallos'][] = array_shift($gallosExtra);
            }
        }

        // Ordenar las rondas por mayor cantidad de gallos
        usort($Rondas, function ($a, $b) {
            return  $b['cantidadGallos'] - $a['cantidadGallos'];
        });
    }

    private function generateEnfrentamientos($derby, $gallos, $ronda, $opcion = true)    
    {   
        $parejas = [];
        $enfrentamientos = [];

        //LOGGER("NUMERO DE RONDAS: ".$ronda);
        
         // Ordenar gallos por peso (de mayor a menor)
        usort($gallos, function ($a, $b) {
            return $a['weight'] <=> $b['weight'];
        });

        // Generar los enfrentamientos basados en el emparejamiento de gallos
        $gallos = $this->matchGalls($derby, $gallos, $opcion, $ronda, $parejas, $enfrentamientos);

        return $enfrentamientos;
    }

    

    private function matchGalls($derby, $gallos, $opcion, $ronda = 0, &$parejas, &$enfrentamientos)
    {
        // Usamos un array para almacenar los IDs de los gallos emparejados, evitando la búsqueda secuencial repetida
        $gallosEmparejados = [];
        foreach ($gallos as $index => $gallo1) {
            // Si el gallo ya está emparejado, saltamos al siguiente
            if (in_array($gallo1['id'], $gallosEmparejados)) {
                continue;
            }

            foreach ($gallos as $x => $gallo2) {
                // Evitar emparejar un gallo consigo mismo y verificar por partido, grupo y tolerancia igual
                if ($index != $x && $gallo1['match_id'] != $gallo2['match_id'] &&
                    (!$opcion || abs($gallo1['weight'] - $gallo2['weight']) <= $derby->tolerance) && // Si opcion es false, no se evalúa la tolerancia
                    $gallo1['group_id'] !== $gallo2['group_id']&& 
                    !in_array($gallo2['id'], $gallosEmparejados)) {

                    // Emparejar los gallos
                    $parejas[] = $gallo1;
                    $parejas[] = $gallo2;

                    // Agregar los gallos a los emparejados
                    $gallosEmparejados[] = $gallo1['id'];
                    $gallosEmparejados[] = $gallo2['id'];

                    // Si uno de los gallos es extra (id=0), asignar el id del otro gallo
                    if ($gallo1['id'] == 0) {
                        $gallo1['id'] = $gallo2['id'];
                    }
                    if ($gallo2['id'] == 0) {
                        $gallo2['id'] = $gallo1['id'];
                    }
                    // Crear el enfrentamiento
                    $enfrentamientos[] = [
                        'derby_id' => $derby->id,
                        'ronda' => $ronda,
                        'gallo1_id' => $gallo1['id'],
                        'gallo2_id' => $gallo2['id'],
                        'condicion' => 'Pelear',
                    ];

                    // Eliminar ambos gallos del array original
                    unset($gallos[$index]);
                    unset($gallos[$x]);

                    // Salir del bucle interno después de emparejar un gallo
                    break;
                }
            }
        }

        // Retornar los gallos restantes (los que no fueron emparejados)
        return array_values($gallos);
    }

    private function tryGeneratingEnfrentamientos($derby, $sortingOptions)
    {
        // Intentar generar enfrentamientos con diferentes opciones de ordenación
        foreach ($sortingOptions as $option) {
             // Obtener gallos según la opción seleccionada
            $gallos = $this->getInfoXRondas($derby, $option[0], $option[1]);
            $RONDAS = $this->generateRondas($derby, $gallos);            
            
            $no_enfrentamientos = 0;

            foreach ($RONDAS as $index => $ronda) {
                //LOGGER('GALLLOS: '.count($RONDAS[$index]['gallos']).' DE LA RONDA N° '.$index + 1);
                $enfrentamientos = $this->generateEnfrentamientos($derby, $RONDAS[$index]['gallos'], $index + 1);
                $RONDAS[$index]['PELEAS'] = $enfrentamientos;
                $no_enfrentamientos += count($enfrentamientos);                
            }

            $Total_Peleas = count($gallos) / 2;
            if ($Total_Peleas == $no_enfrentamientos) {
                LOGGER('ENFRENTAMIENTOS GENERADOS CORRECTAMENTE');
                //LOGGER($RONDAS);
                
                return response()->json([
                    'message' => 'ENFRENTAMIENTOS GENERADOS CORRECTAMENTE',
                    'RONDAS' => $RONDAS,
                ]);
            } else {
                LOGGER("ERROR PELEAS SIN EMPAREJAR PARA LA OPCIÓN: {$option[0]} {$option[1]}");
            }
        }

        return $this->tryGeneratingEnfrentamientosWithoutTolerance($derby, $sortingOptions);
    }

    private function tryGeneratingEnfrentamientosWithoutTolerance($derby, $sortingOptions, $maxAttempts = 6) 
    {
        // Intentar hasta $maxAttempts veces
        $attempts = 0;
        while ($attempts < $maxAttempts) {
            // Elegir una opción al azar
            $randomOption = $sortingOptions[array_rand($sortingOptions)];
    
            // Obtener gallos según la opción seleccionada
            $gallos = $this->getInfoXRondas($derby, $randomOption[0], $randomOption[1]);
            $RONDAS = $this->generateRondas($derby, $gallos);
    
            $no_enfrentamientos = 0;
    
            // Generar enfrentamientos para cada ronda
            foreach ($RONDAS as $index => $ronda) {
                //LOGGER('GALLLOS: ' . count($RONDAS[$index]['gallos']) . ' DE LA RONDA N° ' . ($index + 1));
                $enfrentamientos = $this->generateEnfrentamientos($derby, $RONDAS[$index]['gallos'], $index + 1, false); // false = Sin tolerancia
                $RONDAS[$index]['PELEAS'] = $enfrentamientos;
                $no_enfrentamientos += count($enfrentamientos);
            }
    
            $Total_Peleas = count($gallos) / 2;
    
            // Validar si se generaron todas las peleas esperadas
            if ($Total_Peleas == $no_enfrentamientos) {
                LOGGER("ENFRENTAMIENTOS GENERADOS SIN TOLERANCIA: {$randomOption[0]} {$randomOption[1]}");
                return response()->json([
                    'message' => '¡ADVERTENCIA!  =>  ENFRENTAMIENTOS GENERADOS SIN TOLERANCIA.',
                    'RONDAS' => $RONDAS,
                ]);
            }else{
                LOGGER("ERROR ENFRENTAMIENTOS NO GENERADOS SIN TOLERANCIA: {$randomOption[0]} {$randomOption[1]}");
            }
    
            // Incrementar el contador de intentos
            $attempts++;
        }
    
        // Si no se pudo generar en 5 intentos
        LOGGER("ERROR PELEAS SIN EMPAREJAR DESPUES DE 5 INTENTOS.");
        return response()->json([
            'message' => 'No se pudieron generar enfrentamientos sin tolerancia después de 5 intentos.',
            'RONDAS' => [],
        ], 400); // Código 400 para indicar un error
    }

    private function agregarTabla($enfrentamientos)
    {
        // Preparar los datos para insertar en la tabla 'rol'
        $dataInsert = array_map(function($enfrentamiento) {
            return [
                'derby_id' => $enfrentamiento->derby_id,
                'ronda' => $enfrentamiento->ronda,
                'gallo1_id' => $enfrentamiento->gallo1_id,
                'gallo2_id' => $enfrentamiento->gallo2_id,
                'condicion' => $enfrentamiento->condicion,
            ];
        }, $enfrentamientos);

        foreach ($dataInsert as $data) {
            $pelea = new Rol($data);
            $pelea->save();
        }
    }

    public function show($id)
    {
        $enfrentamientos = Rol::where('derby_id', $id)->with('gallo1', 'gallo2')->get();
        
        foreach ($enfrentamientos as $enfrentamiento) {
            // Obtener el nombre del partido para gallo1
            $match1_id = $enfrentamiento->gallo1->match_id;
            $match1 = Matchs::select('name')->where('id', $match1_id)->first();
            $enfrentamiento->gallo1->match_name = $match1 ? $match1->name : null;

            // Obtener el nombre del partido para gallo2
            $match2_id = $enfrentamiento->gallo2->match_id;
            $match2 = Matchs::select('name')->where('id', $match2_id)->first();
            $enfrentamiento->gallo2->match_name = $match2 ? $match2->name : null;
        }

        return response()->json($enfrentamientos);
    }

    public function generatePDF($id){
        $pdfContent = $this->PDF($id);

        Storage::disk('public')->put('matches/Derby N°'. ($id) . '.pdf', $pdfContent);

        return response($pdfContent, 200)->header('Content-Type', 'application/pdf');// Devolver el contenido del PDF
    }

    private function PDF($id)
    {
        $enfrentamientos = Rol::where('derby_id', $id)->with('gallo1', 'gallo2')->get();
        
        foreach ($enfrentamientos as $enfrentamiento) {
            // Obtener el nombre del partido para gallo1
            $match1_id = $enfrentamiento->gallo1->match_id;
            $match1 = Matchs::select('name')->where('id', $match1_id)->first();
            $enfrentamiento->gallo1->match_name = $match1 ? $match1->name : null;

            // Obtener el nombre del partido para gallo2
            $match2_id = $enfrentamiento->gallo2->match_id;
            $match2 = Matchs::select('name')->where('id', $match2_id)->first();
            $enfrentamiento->gallo2->match_name = $match2 ? $match2->name : null;
        }

        $derby = Derbys::findOrFail($id);

        $logoImagePath = public_path('imgPDF/LogoDerby.png');
        $logoImage = $this->getImageBase64($logoImagePath);// Convertir las imágenes a base64
 
        $data = [
            'logoImage' => $logoImage,
            'enfrentamientos' => $enfrentamientos,
            'derby' => $derby,
        ];

        $html = view('DERBYS A&J ROL (ENFRENTAMIENTOS)', $data)->render();

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();                     
    }

    private function getImageBase64($imagePath)
    {
        $file = file_get_contents($imagePath);
        $base64 = base64_encode($file);
        return 'data:image/png;base64,' . $base64;
    }  

    public function update(Request $request, $id)
    {
        $data = $request->all();

        //COLOCAR TODAS LAS PELEAS AL DEL DERBY 'condicion' => 'Pelear' 
        Rol::where('derby_id', $id)->update(['condicion' => 'Pelear']);

        foreach ($data as &$item) {
            // Encontrar la pelea por su ID
            $pelea = Rol::findOrFail($item['roundId']);

            // Verificar si es un gallo extra
            if ($pelea->gallo1_id == $pelea->gallo2_id && $item['gallo'] == 'gallo2') {
                // Actualizar los datos de la pelea con condición 'EXTRA'
                $pelea->update([
                    'condicion' => 'EXTRA',
                ]);
            } else {
                // Actualizar los datos de la pelea con el anillo proporcionado
                $pelea->update([
                    'condicion' => $item['anillo'],
                ]);
            }
        }

        $partidos = Matchs::where('derby_id', $id)->with('roosters')->get();

        foreach ($partidos as $partido) {
            $puntos = 0;
            foreach ($partido->roosters as $gallo) {
                // Buscar gallo en rosters donde $gallo->ring == condicion
                $Buscar1 = Rol::where('condicion', $gallo->ring)->first();
                $Buscar2 = Rol::where('gallo1_id', $gallo->id)->orWhere('gallo2_id', $gallo->id)->first();

                if ($Buscar1) {
                    $gallo['pelea'] = 'G'; // Si lo encuentra, asignar 'G' a pelea
                    $puntos += 3;
                } elseif ($Buscar2 && $Buscar2->condicion == 'Pelear') {
                    $gallo['pelea'] = 'E'; // Si no lo encuentra pero es uno de los gallos participantes, asignar 'E' a pelea
                    $puntos += 1;
                } else {
                    $gallo['pelea'] = 'P'; // Si no cumple ninguna condición anterior, asignar 'P' a pelea
                }
            }
            $partido['puntos'] = $puntos;
        }

        // Ordenar los partidos por puntos de mayor a menor
        $partidos = $partidos->sortByDesc('puntos')->values()->all();

        return response()->json($partidos);
    }
}