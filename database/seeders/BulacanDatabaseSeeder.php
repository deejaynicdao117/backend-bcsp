<?php

namespace Database\Seeders;

use App\Models\Barangay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BulacanDatabaseSeeder extends Seeder
{
    private const API_URL = 'https://psgc.gitlab.io/api/';

    public function run(): void
    {
        $client = Http::timeout(60)->retry(3, 500);
        $barangays = $client->get(self::API_URL.'barangays/')->throw()->json();
        $municipalities = $client->get(self::API_URL.'municipalities/')->throw()->json();
        $cities = $client->get(self::API_URL.'cities/')->throw()->json();
        $provinces = $client->get(self::API_URL.'provinces/')->throw()->json();
        $regions = $client->get(self::API_URL.'regions/')->throw()->json();
        $bulacanProvinceCode = '031400000';

        if (! is_array($barangays)) {
            throw new RuntimeException('The PSGC response did not include a valid barangay list.');
        }

        $barangays = collect($barangays)
            ->filter(fn (array $barangay): bool => ($barangay['provinceCode'] ?? null) === $bulacanProvinceCode)
            ->values()
            ->all();

        if (count($barangays) < 500) {
            throw new RuntimeException('The PSGC response did not include the expected Bulacan barangay list.');
        }

        $municipalityByCode = collect($municipalities)->keyBy('code');
        $cityByCode = collect($cities)->keyBy('code');
        $provinceByCode = collect($provinces)->keyBy('code');
        $regionByCode = collect($regions)->keyBy('code');
        $timestamp = now();

        foreach (array_chunk($barangays, 500) as $barangayChunk) {
            $rows = array_map(function (array $barangay) use (
                $municipalityByCode,
                $cityByCode,
                $provinceByCode,
                $regionByCode,
                $timestamp,
            ): array {
                $cityCode = $barangay['cityCode'] ?? false;
                $municipalityCode = $barangay['municipalityCode'] ?? false;
                $locality = $cityCode
                    ? $cityByCode->get($cityCode)
                    : $municipalityByCode->get($municipalityCode);
                $province = ($barangay['provinceCode'] ?? false)
                    ? $provinceByCode->get($barangay['provinceCode'])
                    : $regionByCode->get($barangay['regionCode'] ?? false);

                return [
                    'psgc_code' => $barangay['code'],
                    'name' => $barangay['name'],
                    'municipality' => $this->normalizeLocalityName($locality['name'] ?? 'Unassigned locality'),
                    'province' => $province['name'] ?? 'Unassigned region',
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }, $barangayChunk);

            DB::table('barangays')->upsert(
                $rows,
                ['psgc_code'],
                ['name', 'municipality', 'province', 'updated_at'],
            );
        }

        $this->removeDuplicateBulacanLocalityRows();

        if (Barangay::where('province', 'Bulacan')->where('psgc_code', '!=', 'DEFAULT')->count() < 500) {
            throw new RuntimeException('The Bulacan barangay directory was not fully imported.');
        }
    }

    private function normalizeLocalityName(string $name): string
    {
        $name = trim($name);

        return match (mb_strtolower($name)) {
            'bulacan' => 'Bulakan',
            'city of malolos', 'malolos city' => 'Malolos',
            'city of meycauayan', 'city of meycuayan' => 'Meycauayan',
            'city of san jose del monte' => 'San Jose del Monte',
            default => $name,
        };
    }

    private function removeDuplicateBulacanLocalityRows(): void
    {
        $defaultBarangayId = DB::table('barangays')->where('psgc_code', 'DEFAULT')->value('id');
        $duplicateBarangayIds = DB::table('barangays')
            ->where('province', 'Bulacan')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(municipality) = ?', ['malolos city'])
                    ->orWhereRaw('LOWER(municipality) = ?', ['city of meycuayan']);
            })
            ->pluck('id');

        if ($duplicateBarangayIds->isEmpty()) {
            return;
        }

        foreach (['users', 'resident_profiles', 'households', 'complaints', 'announcements', 'emergency_contacts', 'evacuation_centers', 'disaster_assistance_requests', 'document_types', 'document_requests', 'portal_notifications', 'activity_logs', 'system_settings'] as $tableName) {
            if (! DB::getSchemaBuilder()->hasColumn($tableName, 'barangay_id')) {
                continue;
            }
            DB::table($tableName)->whereIn('barangay_id', $duplicateBarangayIds)->update(['barangay_id' => $defaultBarangayId]);
        }

        DB::table('barangays')->whereIn('id', $duplicateBarangayIds)->delete();
    }
}
