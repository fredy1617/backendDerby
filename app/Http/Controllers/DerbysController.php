<?php

namespace App\Http\Controllers;

use App\Models\Derbys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $data = $request->all();
        $data['user_id'] = $user->id;
        $Derby = new Derbys($data);

        $Derby->save();
        return response()->json(['message' => 'Derby registrado con exito'], 201);
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
