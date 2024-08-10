<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function usersAll()
    {
        $users = User::all(); 
        return response()->json($users); 
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.unique' => '*El correo ya está registrado.',
            'email.email' => '*El formato del correo no es válido.',
            'password.confirmed' => '*Las contraseñas no coinciden.',
            'password.min' => '*Las contraseñas tiene que tener al menos 8 caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'lastname' => $request->lastname,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'rol' => $request->rol ?? 'user',
            'active' => $request->active ?? false,
        ]);

        return response()->json($user, 201);
    }


    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => '*Correo no registrado (No encontrado)'], 401);
        }

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => '*Contraseña Incorrecta'], 401);
        }

        $user = Auth::user();

        if ($user->active == false) {
            return response()->json(['message' => '*Usuario inactivo, contacte a un Administrador'], 401);
        }

        return response()->json([
            'user' => $user,
            'token' => $request->user()->createToken('auth_token')->plainTextToken
        ]);
    }

    public function logout(Request $request)
    {
        if ($user = $request->user()) {
            $user->tokens()->delete();
            return response()->json(['message' => 'Logged out successfully']);
        }
    
        return response()->json(['message' => '*Usuario no autenticado'], 401);
    }

}
