<?php

namespace App\Http\Controllers;

use App\Models\Derbys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DerbysController extends Controller
{
    public function index()
    {
        $derbys = Derbys::all(); 
        return response()->json($derbys); 
    }

    public function store(Request $request)
    {      
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'No estás autenticado'], 401);
        }

        // Verifica si el usuario tiene derbys disponibles
        if ($user->derbys <= 0) {
            return response()->json(['message' => 'No cuentas con derbys disponibles'], 400);
        }

        // Crear el nuevo derby
        $data = $request->all();
        $data['user_id'] = $user->id;
        $data['date_created'] = now();
        $Derby = new Derbys($data);

        if ($Derby->save()) {
            // Resta uno a los derbys disponibles del usuario solo si el derby se guarda correctamente
            $user->derbys -= 1;
            $user->save();
    
            return response()->json(['message' => 'Derby registrado con éxito'], 201);
        } else {    
            return response()->json(['message' => 'Error al registrar el derby'], 500);
        }
    }

    public function show($id)
    {
        $derby = Derbys::find($id);
        return response()->json($derby);
    }

    public function update(Request $request, $id)
    {
        // Find the area by ID, or return a 404 error if not found
        $Derby = Derbys::findOrFail($id);

        // Update the Derby with the provided data
        $Derby->update($request->all());
        
        return response()->json(['message' => 'Derby actualizada exitosamente.'], 201);
    }

}
