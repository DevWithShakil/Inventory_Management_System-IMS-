<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $staff = User::where('email', 'staff@gmail.com')->first();

        if (!$staff) {
            User::create([
                'name' => 'Sales Staff',
                'email' => 'staff@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]);
        }
    }
}
