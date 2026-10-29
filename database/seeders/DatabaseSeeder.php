<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\DocumentType;
use App\Models\EmergencyContact;
use App\Models\EvacuationCenter;
use App\Models\User;
use App\Support\CurrentBarangay;
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
        $this->call(BulacanBarangaySeeder::class);
        $defaultBarangayId = (int) Barangay::where('psgc_code', 'DEFAULT')->value('id');
        app(CurrentBarangay::class)->set($defaultBarangayId);

        $demoUsers = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => 'password',
                'role' => 'platform_admin',
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
                    'barangay_id' => $defaultBarangayId,
                    'email_verified_at' => now(),
                ],
            );
        }

        DocumentType::firstOrCreate(
            ['name' => 'Barangay Indigency'],
            ['description' => 'Certificate confirming that a resident belongs to a low-income household.', 'barangay_id' => $defaultBarangayId],
        );

        EmergencyContact::firstOrCreate(
            ['phone' => '911'],
            ['name' => 'Emergency Hotline', 'office' => 'Barangay Emergency Response', 'availability' => '24/7', 'barangay_id' => $defaultBarangayId],
        );

        EmergencyContact::firstOrCreate(
            ['phone' => '02-8123-4567'],
            ['name' => 'Barangay Hall', 'office' => 'Barangay Office', 'availability' => 'Office hours', 'barangay_id' => $defaultBarangayId],
        );

        EvacuationCenter::firstOrCreate(
            ['name' => 'Barangay Covered Court'],
            ['address' => 'Barangay Main Road', 'purok' => 'Central', 'capacity' => 250, 'status' => 'open', 'barangay_id' => $defaultBarangayId],
        );
    }
}
