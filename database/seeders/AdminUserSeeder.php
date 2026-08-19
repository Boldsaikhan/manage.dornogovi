<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@dornogovi.gov.mn')],
            [
                'name' => 'Админ',
                // Production дээр `.env`-ийн ADMIN_PASSWORD-оор дамжуулна.
                // Локал хөгжүүлэлтэд өгөгдмөл нь "password".
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
                'is_admin' => true,
            ]
        );
    }
}
