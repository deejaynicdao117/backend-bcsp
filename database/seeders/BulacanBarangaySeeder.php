<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BulacanBarangaySeeder extends Seeder
{
    private const PROVINCE_CODE = '031400000';

    public function run(): void
    {
        $municipalities = Http::timeout(30)->retry(3, 500)
            ->get('https://psgc.gitlab.io/api/provinces/'.self::PROVINCE_CODE.'/cities-municipalities/')
            ->throw()
            ->json();

        foreach ($municipalities as $municipality) {
            $places = Http::timeout(30)->retry(3, 500)
                ->get('https://psgc.gitlab.io/api/cities-municipalities/'.$municipality['code'].'/barangays/')
                ->throw()
                ->json();

            foreach ($places as $place) {
                Barangay::updateOrCreate(
                    ['psgc_code' => $place['code']],
                    [
                        'name' => $place['name'],
                        'municipality' => $municipality['name'],
                        'province' => 'Bulacan',
                        'is_active' => true,
                    ],
                );
            }
        }

        if (Barangay::where('province', 'Bulacan')->count() < 500) {
            throw new RuntimeException('The PSGC response did not include the expected Bulacan barangay list.');
        }
    }
}
