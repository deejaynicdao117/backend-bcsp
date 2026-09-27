<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\EmergencyContact;
use App\Models\EvacuationCenter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $demoUsers = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => 'password',
                'role' => 'admin',
            ],
            [
                'name' => 'Staff User',
                'email' => 'staff@example.com',
                'password' => 'password',
                'role' => 'staff',
            ],
            [
                'name' => 'Resident User',
                'email' => 'resident@example.com',
                'password' => 'password',
                'role' => 'resident',
            ],
        ];

        foreach ($demoUsers as $demoUser) {
            User::updateOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'password' => bcrypt($demoUser['password']),
                    'role' => $demoUser['role'],
                    'email_verified_at' => now(),
                ],
            );
        }

        EmergencyContact::firstOrCreate(
            ['phone' => '911'],
            ['name' => 'Emergency Hotline', 'office' => 'Barangay Emergency Response', 'availability' => '24/7'],
        );

        EmergencyContact::firstOrCreate(
            ['phone' => '02-8123-4567'],
            ['name' => 'Barangay Hall', 'office' => 'Barangay Office', 'availability' => 'Office hours'],
        );

        EvacuationCenter::firstOrCreate(
            ['name' => 'Barangay Covered Court'],
            ['address' => 'Barangay Main Road', 'purok' => 'Central', 'capacity' => 250, 'status' => 'open'],
        );
    }
}
