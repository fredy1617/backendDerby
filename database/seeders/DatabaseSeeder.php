<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {

        \App\Models\User::factory()->create([
            'name' => 'Administrador',
            'lastname' => 'A&J',
            'email' => 'admin@derbys.com.mx',
            'password' => Hash::make('adminA&J'),
            'active' => true,
            'rol' => 'Administrador'
        ]);

        \App\Models\User::factory()->create([
            'name' => 'Admin_2.0',
            'lastname' => 'A&J_2.0',
            'email' => 'admin2@derbys.com.mx',
            'password' => Hash::make('admin1234'),
            'active' => true,
            'rol' => 'Administrador'
        ]);
    }
}
