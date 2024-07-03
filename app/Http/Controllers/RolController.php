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
        $derby = Derbys::findOrFail($request->id);
        $sortingOptions = [
            ['weight', 'ASC'],
            ['ring', 'ASC'],
            ['name', 'ASC'],
            ['id', 'ASC'],
            ['match_id', 'ASC'],
            ['match_id', 'DESC'],
            ['id', 'DESC'],
            ['name', 'DESC'],
            ['weight', 'DESC'],
            ['ring', 'DESC'],
        ];
        
        // Eliminar roles anteriores del derby ANTES DE GENERAR UNO NUEVO
        Rol::where('derby_id', $derby->id)->delete();

        $response = $this->tryGeneratingEnfrentamientos($derby, $sortingOptions);

        if ($response->getData()) {
            LOGGER('ROL GENERADO CON EXITO');

            // Recorrer cada ronda y generar las peleas en $this->agregarTabla($enfrentamientos, $derby->id);
            $data = $response->getData()->RONDAS;
            foreach ($data as $ronda) {
                $enfrentamientos = $ronda->PELEAS;
                $this->agregarTabla($enfrentamientos, $derby->id);
            }
            return response()->json([
                'success' => true,
                'message' => $response->getData()->message,
                'RONDAS' => $response->getData()->RONDAS,
            ]);
        }

        LOGGER('ERROR PELEAS SIN EMPAREJAR DESPUÉS DE INTENTAR TODAS LAS OPCIONES');
        return response()->json([
            'success' => false,
            'message' => 'No se pudieron generar enfrentamientos emparejados después de intentar todas las opciones.',
        ], 500);

    }

    private function generateRondas($derby, $gallos)
    {
        $Rondas = [];

        // Iterar sobre cada posición de gallo en la ronda
        for ($i = 0; $i < $derby->no_roosters; $i++) {
            $ronda = [
                'no' => $i, // Número de la ronda
                'gallos' => [], // Inicializar arreglo para los gallos de esta ronda
            ];

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

            // Agregar la ronda al arreglo de Rondas
            $Rondas[] = $ronda;
        }

        return $Rondas;
    }

    private function generateEnfrentamientos($derby, $gallos, $ronda, $opcion = 1)    
    {
        $parejas = [];
        $enfrentamientos = [];
            
        $gallos = $this->matchGalls($derby, $gallos, $opcion, $ronda, $parejas, $enfrentamientos);

        return $enfrentamientos;
    }


    private function getInfoXRondas($derby, $by, $order)
    {
        $partidos = Matchs::select('id')
                            ->where('derby_id', $derby->id)->get();
        

        foreach ($partidos as $partido) {
            $gallos = Roosters::select('id', 'ring', 'weight', 'match_id')
                            ->where('match_id', $partido->id)
                            ->orderBy($by, $order)
                            ->get();
            $partido['gallos']=$gallos;            
        }

        $gallos = new Collection();
            foreach ($partidos as $partido) {
                $gallos = $gallos->merge($partido->gallos);
        }

        $no_gallos = count($gallos);// Calcular el número de gallos)

        if ($no_gallos % 2 != 0) {
            $gallos[] = [
                'id' => '0',
                'ring' => 'EXTRA',
                'weight' => $derby->min_weight,
                'match_id' => '0',
                'group_id' => '0',
            ];
        }

        //5.- RECORRER LOS GALLOS PARA AGREGAR GRUPO
        $grupo = 1;
        foreach ($gallos as $gallo) {
            $grupo --;
            // Fetch the group_id from the database or use the default $grupo value
            $group_id = Matchs::where('matchs.id', $gallo['match_id'])
            ->join('group_matches', 'matchs.id', '=', 'group_matches.match_id')
            ->value('group_matches.group_id') ?? $grupo;

            // Set the group_id for the current gallo
            $gallo['group_id'] = $group_id;
        }
        return ($gallos);
    }

    private function matchGalls($derby, $gallos, $opcion = 1, $ronda = 0, &$parejas, &$enfrentamientos)
    {
        foreach ($gallos as $index => $gallo1) {
            // Si el gallo ya está emparejado, continuar con el siguiente
            if (in_array($gallo1, $parejas)) {
                continue;
            }
        
            foreach ($gallos as $x => $gallo2) {
                // Evitar emparejar un gallo consigo mismo y verificar por partido, grupo y tolerancia igual
                if ($index != $x && $gallo1['match_id'] != $gallo2['match_id'] &&
                    ($opcion == 2 || abs($gallo1['weight'] - $gallo2['weight']) <= $derby->tolerance) &&// AQUI SI LA OPCION ES 2 quitar esta linea
                    $gallo1['group_id'] !== $gallo2['group_id']) {

                    // Agregar ambos gallos a la lista de emparejados
                    $parejas[] = $gallo1;
                    $parejas[] = $gallo2;

                    if ($gallo1['id'] == 0) {
                        $gallo1['id'] = $gallo2['id'];
                    }
                    if ($gallo2['id'] == 0) {
                        $gallo2['id'] = $gallo1['id'];
                    }
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
        //LOGGER('GALLOS RESTANTES: ');
        //LOGGER($gallos);

        return array_values($gallos);
    }

    private function tryGeneratingEnfrentamientos($derby, $sortingOptions)
    {
        foreach ($sortingOptions as $option) {
            $gallos = $this->getInfoXRondas($derby, $option[0], $option[1]);
            $RONDAS = $this->generateRondas($derby, $gallos);
            $this->balanceRounds($RONDAS, 1, 0);
            $this->balanceRounds($RONDAS, 1, 2);

            $no_enfrentamientos = 0;
            foreach ($RONDAS as $index => $ronda) {
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

    private function tryGeneratingEnfrentamientosWithoutTolerance($derby, $sortingOptions)
    {
        foreach ($sortingOptions as $option) {
            $gallos = $this->getInfoXRondas($derby, $option[0], $option[1]);
            $RONDAS = $this->generateRondas($derby, $gallos);
            $this->balanceRounds($RONDAS, 1, 0);
            $this->balanceRounds($RONDAS, 1, 2);

            $no_enfrentamientos = 0;
            foreach ($RONDAS as $index => $ronda) {
                $enfrentamientos = $this->generateEnfrentamientos($derby, $RONDAS[$index]['gallos'], $index + 1, 2);
                $RONDAS[$index]['PELEAS'] = $enfrentamientos;
                $no_enfrentamientos += count($enfrentamientos);
            }

            $Total_Peleas = count($gallos) / 2;
            if ($Total_Peleas == $no_enfrentamientos) {
                LOGGER('ENFRENTAMIENTOS GENERADOS SIN TOLERANCIA');
                //LOGGER($RONDAS);
                
                return response()->json([
                    'message' => '¡ADVERTENCIA!  =>  ENFRENTAMIENTOS GENERADOS SIN TOLERANCIA.',
                    'RONDAS' => $RONDAS,
                ]);
            } else {
                LOGGER("ERROR PELEAS SIN EMPAREJAR PARA LA OPCIÓN SIN TOLERANCIA: {$option[0]} {$option[1]}");
            }
        }

        return null;
    }

    private function balanceRounds(&$RONDAS, $sourceIndex, $targetIndex)
    {
        if (count($RONDAS[$targetIndex]['gallos']) % 2 != 0) {
            if (isset($RONDAS[$sourceIndex]) && count($RONDAS[$sourceIndex]['gallos']) > 0) {
                $galloMovido = array_shift($RONDAS[$sourceIndex]['gallos']);
                $RONDAS[$targetIndex]['gallos'][] = $galloMovido;
            }
        }
    }

    private function generateEnfrentamientos2($derby, $gallos)    
    {
        $parejas = [];
        $enfrentamientos = [];

        $gallos = $this->matchGalls($derby, $gallos, $parejas, $enfrentamientos);

        if (count($gallos) == 2 && ($gallos[0]['ring'] == 'EXTRA' || $gallos[1]['ring'] == 'EXTRA')) {
            $this->addEnfrentamiento($derby, $gallos[0], $gallos[1], $enfrentamientos);
            $gallos = [];
        } else if (count($gallos) > 2) {
            $gallos = $this->getInfo($derby, 'weight', 'DESC');
            $gallos = $this->matchGalls($derby, $gallos, $parejas, $enfrentamientos);
        }
        if (count($gallos) == 2 && ($gallos[0]['ring'] == 'EXTRA' || $gallos[1]['ring'] == 'EXTRA')) {
            $this->addEnfrentamiento($derby, $gallos[0], $gallos[1], $enfrentamientos);
            $gallos = [];
        } else if (count($gallos) > 2) {
            $gallos = $this->getInfo($derby, 'ring', 'DESC');
            $gallos = $this->matchGalls($derby, $gallos, $parejas, $enfrentamientos);
        }
        if (count($gallos) == 2 && ($gallos[0]['ring'] == 'EXTRA' || $gallos[1]['ring'] == 'EXTRA')) {
            $this->addEnfrentamiento($derby, $gallos[0], $gallos[1], $enfrentamientos);
            $gallos = [];
        } else if (count($gallos) > 2) {
            $gallos = $this->getInfo($derby, 'ring', 'ASC');
            $gallos = $this->matchGalls($derby, $gallos, $parejas, $enfrentamientos);
        }
        if (count($gallos) == 2 && ($gallos[0]['ring'] == 'EXTRA' || $gallos[1]['ring'] == 'EXTRA')) {
            $this->addEnfrentamiento($derby, $gallos[0], $gallos[1], $enfrentamientos);
            $gallos = [];
        } else if (count($gallos) > 2) {
            $gallos = $this->getInfo($derby, 'roosters.name', 'ASC');
            $gallos = $this->matchGalls($derby, $gallos, $parejas, $enfrentamientos);
        }
        if (count($gallos) == 2 && ($gallos[0]['ring'] == 'EXTRA' || $gallos[1]['ring'] == 'EXTRA')) {
            $this->addEnfrentamiento($derby, $gallos[0], $gallos[1], $enfrentamientos);
            $gallos = [];
        } else if (count($gallos) > 2) {
            $gallos = $this->getInfo($derby, 'roosters.name', 'DESC');
            $gallos = $this->matchGalls($derby, $gallos, $parejas, $enfrentamientos);
        }
        if (count($gallos) == 2 && ($gallos[0]['ring'] == 'EXTRA' || $gallos[1]['ring'] == 'EXTRA')) {
            $this->addEnfrentamiento($derby, $gallos[0], $gallos[1], $enfrentamientos);
            $gallos = [];
        } else if (count($gallos) > 2) {
            $gallos = $this->getInfo($derby, 'roosters.match_id', 'DESC');
            $gallos = $this->matchGalls($derby, $gallos, $parejas, $enfrentamientos);

        }
        if (count($gallos) == 2 && ($gallos[0]['ring'] == 'EXTRA' || $gallos[1]['ring'] == 'EXTRA')) {
            $this->addEnfrentamiento($derby, $gallos[0], $gallos[1], $enfrentamientos);
            $gallos = [];
        } else if (count($gallos) > 2) {
            $gallos = $this->getInfo($derby, 'roosters.match_id', 'ASC');
            $gallos = $this->matchGalls($derby, $gallos, $parejas, $enfrentamientos);

        }

        return $enfrentamientos;
    }

    private function addEnfrentamiento($derby, $gallo1, $gallo2, &$enfrentamientos)
    {
        if ($gallo1['id'] == 0) {
            $gallo1['id'] = $gallo2['id'];
        }
        if ($gallo2['id'] == 0) {
            $gallo2['id'] = $gallo1['id'];
        }

        $enfrentamientos[] = [
            'derby_id' => $derby->id,
            'ronda' => 0,
            'gallo1_id' => $gallo1['id'],
            'gallo2_id' => $gallo2['id'],
            'condicion' => 'Pelear',
            'gallo1_match' => $gallo1['match_id'],
            'gallo2_match' => $gallo2['match_id'],
        ];
    }


    private function getInfo($derby, $by, $order)
    {
        //2.- GALLOS DEL DERBY CON PARTIDO Y EN ORDEN PESO
        $gallos = Roosters::select('roosters.id', 'ring', 'weight', 'match_id')
                            ->join('matchs', 'roosters.match_id', '=', 'matchs.id')
                            ->where('matchs.derby_id', $derby->id)
                            ->orderBy($by, $order)
                            ->get();

        $no_rondas = $derby->no_roosters;// Calcular el número de rondas (igual al número de gallos del derby)
        $no_gallos = count($gallos);// Calcular el número de gallos)

        if ($no_gallos % 2 != 0) {
            $gallos[] = [
                'id' => '0',
                'ring' => 'EXTRA',
                'weight' => $derby->min_weight,
                'match_id' => '0',
                'group_id' => '0',
            ];
            $no_gallos = count($gallos); // Calcular el número de gallos
        }
       
        LOGGER ('Total Rondas: '.$no_rondas);
        LOGGER ('Total Gallos: '.$no_gallos);
        

        //5.- RECORRER LOS GALLOS PARA AGREGAR GRUPO
        $grupo = 1;
        foreach ($gallos as $gallo) {
            $grupo --;
            // Fetch the group_id from the database or use the default $grupo value
            $group_id = Matchs::where('matchs.id', $gallo['match_id'])
            ->join('group_matches', 'matchs.id', '=', 'group_matches.match_id')
            ->value('group_matches.group_id') ?? $grupo;

            // Set the group_id for the current gallo
            $gallo['group_id'] = $group_id;
        }

        LOGGER ('Gallos: ');
        //LOGGER ($gallos);
        return $gallos->toArray();
    }


    public function store2(Request $request)
    {
        try{
            // Obtener el derby y sus partidos
            $derby = Derbys::findOrFail($request->id);
            $partidos = Matchs::where('derby_id', $derby->id)->with('roosters')->get();

            // Calcular el número de rondas (igual al número de gallos por ronda)
            $no_rondas = $derby->no_roosters;

            // Obtener todos los gallos de los partidos del derby
            $gallos = new Collection();
            foreach ($partidos as $partido) {
                $gallos = $gallos->merge($partido->roosters);
            }
            $gallos = $gallos->sortBy('weight')->values(); // Ordenar por peso y reindexar

            $numPartidos = count($partidos);
            $Total_Peleas = 0;
            $RONDAS = [];

            if ($numPartidos % 2 == 0) {
                $Total_Peleas = ($numPartidos * $no_rondas) / 2;
            } else {
                $Total_Peleas = (($numPartidos * $no_rondas) + 1) / 2;
            }
            LOGGER($no_rondas.' RONDAS   ---   PARTIDOS '.$numPartidos);

            LOGGER ('TOTAL DE PELEAS'.$Total_Peleas);
            
            if ($numPartidos % 2 == 0) {
                for ($i = 1; $i <= $no_rondas; $i++) {                 
                    $peleas = $Total_Peleas / $no_rondas;
                    $RONDAS['RONDA '.$i]['peleas'] = $peleas;
                }
            } else {
                for ($i = 1; $i <= $no_rondas; $i++) { 
                    if ($i % 2 == 0) {
                        $peleas = ($numPartidos - 1) / 2;
                    } else {
                        $peleas = ($numPartidos + 1) / 2;
                    }
                    $RONDAS['RONDA '.$i]['peleas'] = $peleas;
                }
            }
            
           

            for ($i = 1; $i <= $no_rondas; $i++) {
                $conditionApplied = false;
                $negativo=0;
                foreach ($gallos as $gallo) {
                    $negativo --;
                    $group_id = Matchs::where('matchs.id', $gallo->match_id)
                                ->join('group_matches', 'matchs.id', '=', 'group_matches.match_id')
                                ->value('group_matches.group_id') ?? $negativo;

                    if ($gallo['name'] == 'Gallo N°' . $i) {                    

                        // Verificar si el gallo tiene el mismo ring que algún gallo de la ronda anterior
                        $isDuplicateRing = false;
                        if ($i > 1 && isset($RONDAS['RONDA ' . ($i - 1)]['gallos'])) {
                            foreach ($RONDAS['RONDA ' . ($i - 1)]['gallos'] as $prevGallo) {
                                if ($gallo['ring'] == $prevGallo['ring']) {
                                    $isDuplicateRing = true;
                                    break;
                                }
                            }
                        }
            
                        if (!$isDuplicateRing) {
                            $RONDAS['RONDA ' . $i]['gallos'][] = [
                                'id' => $gallo->id, // Asegúrate de tener el campo 'id' correctamente
                                'name' => $gallo->name,
                                'weight' => $gallo->weight,
                                'ring' => $gallo->ring,
                                'match_id' => $gallo->match_id,
                                'group_id' => $group_id, // Incluir el group_id obtenido
                            ];
                        } 
                    }
                    
                    // Condición especial para rondas impares 
                    if ($numPartidos % 2 !== 0 && $i % 2 !== 0 && !$conditionApplied) {
                        if ($gallo['name'] == 'Gallo N°' . ($i + 1)) {
                            $RONDAS['RONDA ' . $i]['gallos'][] = [
                                'id' => $gallo->id, // Asegúrate de tener el campo 'id' correctamente
                                'name' => $gallo->name,
                                'weight' => $gallo->weight,
                                'ring' => $gallo->ring,
                                'match_id' => $gallo->match_id,
                                'group_id' => $group_id, // Incluir el group_id obtenido
                            ];
                            $conditionApplied = true; // Marcar la condición como aplicada
                        }
                    }                
                }
                if ($numPartidos % 2 !== 0 && $i % 2 !== 0 && $i == $no_rondas) {
                    $RONDAS['RONDA ' . $i]['gallos'][] = [
                        'id' => '0', // Asegúrate de tener el campo 'id' correctamente
                        'name' => 'EXTRA',
                        'weight' => '2300',
                        'ring' => 'EXTRA',
                        'match_id' => '0',
                        'group_id' => '0',
                    ];
                }
            }
        
            // Mostrar información de las rondas
            //foreach ($RONDAS as $ronda => $info) {
                //Logger($ronda);
                //Logger($info);
            //}
            // Generar los enfrentamientos por cada ronda
            $enfrentamientos = [];

            foreach ($RONDAS as $ronda => $infoRonda) {
                //LOGGER($infoRonda);

                $rondaTurno = 1;
                $gallosDisponibles = $infoRonda['gallos']; // Lista de gallos disponibles para la ronda actual

                // Crear una lista de enfrentamientos para controlar las peleas ya asignadas
                $enfrentamientosRonda = [];

                while ($rondaTurno <= $infoRonda['peleas']) {
                    // Lógica para seleccionar los gallos y aplicar las condiciones
                    $gallo1 = null;
                    $gallo2 = null;

                    foreach ($gallosDisponibles as $index1 => $gallo1) {
                        // Verificar si el gallo ya tiene una pelea dentro de la misma ronda
                        if ($this->galloConPelea($enfrentamientosRonda, $gallo1)) {
                            continue; // Saltar este gallo y pasar al siguiente
                        }

                        foreach ($gallosDisponibles as $index2 => $gallo2) {
                            // Verificar si el gallo ya tiene una pelea dentro de la misma ronda
                            if ($this->galloConPelea($enfrentamientosRonda, $gallo2)) {
                                continue; // Saltar este gallo y pasar al siguiente
                            }

                            // Verificar que los gallos cumplan las condiciones y no sean el mismo gallo
                            if (
                                $index1 != $index2 && // No es el mismo gallo
                                abs($gallo1['weight'] - $gallo2['weight']) <= $derby->tolerance &&
                                $gallo1['match_id'] !== $gallo2['match_id'] &&
                                $gallo1['group_id'] !== $gallo2['group_id'] &&                            
                                !$this->peleaRepetida($enfrentamientosRonda, $gallo1, $gallo2)
                            ) {
                                // Agregar el enfrentamiento si cumple las condiciones
                                $enfrentamientosRonda[] = [
                                    'pelea' => "Gallo {$gallo1['ring']} vs Gallo {$gallo2['ring']}",
                                    'condicion' => "Ronda " . ($ronda),
                                ];
                                
                                //INSERT EN TABLA ROL 'derby_id', 'ronda', 'gallo1_id', 'gallo2_id', 'condicion',
                                // Crear una instancia de Rol y asignar los valores
                                
                                $numeroRonda = (int) filter_var($ronda, FILTER_SANITIZE_NUMBER_INT);
                                
                                if ($gallo1['id'] == 0) {
                                    $gallo1['id'] = $gallo2['id'];
                                }
                                if ($gallo2['id'] == 0) {
                                    $gallo2['id'] = $gallo1['id'];
                                }

                                $enfrentamientos[] = [
                                    'derby_id' => $derby->id,
                                    'ronda' => $numeroRonda,
                                    'gallo1_id' => $gallo1['id'],
                                    'gallo2_id' => $gallo2['id'],
                                    'condicion' => 'Pelear',
                                    'match1_id' => $gallo1['match_id'],
                                    'match2_id' => $gallo2['match_id'],
                                ];
                                $rondaTurno++;
                                break 2; // Salir de ambos bucles foreach
                            }
                        }
                    }
                }
            }

            $this->agregarTabla($enfrentamientos, $derby->id);
            // Devolver respuesta exitosa con los enfrentamientos
            return response()->json([
                'success' => true,
                'message' => 'Enfrentamientos generados correctamente.',
                'enfrentamientos' => $enfrentamientos,
            ]);
        } catch (\Exception $e) {
            // Devolver respuesta de error en caso de excepción
            return response()->json([
                'success' => false,
                'message' => 'Error al generar enfrentamientos: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    private function agregarTabla($enfrentamientos, $derby)
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

        // Registrar los datos ordenados de enfrentamientos
        //LOGGER($dataInstert);

        foreach ($dataInsert as $data) {
            $pelea = new Rol($data);
            $pelea->save();
        }
    }

    // Función para verificar si una pelea ya ha sido agregada previamente en la misma ronda
    private function peleaRepetida($enfrentamientosRonda, $gallo1, $gallo2)
    {
        foreach ($enfrentamientosRonda as $pelea) {
            $gallosPelea = explode(' vs ', $pelea['pelea']);
            if (
                ($gallosPelea[0] == "Gallo {$gallo1['ring']}" && $gallosPelea[1] == "Gallo {$gallo2['ring']}") ||
                ($gallosPelea[0] == "Gallo {$gallo2['ring']}" && $gallosPelea[1] == "Gallo {$gallo1['ring']}") 
            ) {
                return true; // La pelea ya ha sido agregada previamente en la misma ronda
            }
        }
        return false; // La pelea es única en la misma ronda
    }

    // Función para verificar si un gallo ya tiene una pelea en la misma ronda
    private function galloConPelea($enfrentamientosRonda, $gallo)
    {
        foreach ($enfrentamientosRonda as $pelea) {
            $gallosPelea = explode(' vs ', $pelea['pelea']);
            if (
                $gallosPelea[0] == "Gallo {$gallo['ring']}" ||
                $gallosPelea[1] == "Gallo {$gallo['ring']}"
            ) {
                return true; // El gallo ya tiene una pelea dentro de la misma ronda
            }
        }
        return false; // El gallo no tiene peleas en la misma ronda
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
