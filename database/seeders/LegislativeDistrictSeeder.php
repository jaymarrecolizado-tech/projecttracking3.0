<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Municipality → legislative district lookup for the Region II provinces,
 * per the 19th Congress (Plan §Map 4.1). Alias rows cover the spelling
 * variants that appear in the source workbooks ("Sta. Ana", "Tuguegarao",
 * "Ilagan City", …) so the backfill can match every LGU it will see.
 * Municipalities not listed stay with district = NULL on purpose.
 */
class LegislativeDistrictSeeder extends Seeder
{
    public function run(): void
    {
        $cagayan = [
            '1st District' => ['Alcala', 'Aparri', 'Baggao', 'Buguey', 'Camalaniugan', 'Gattaran', 'Gonzaga', 'Lal-lo', 'Santa Ana', 'Sta. Ana', 'Santa Teresita', 'Santa Teresita'],
            '2nd District' => ['Abulug', 'Allacapan', 'Ballesteros', 'Calayan', 'Claveria', 'Lasam', 'Pamplona', 'Piat', 'Rizal', 'Sanchez-Mira', 'Santa Praxedes', 'Santo Niño', 'Santo Nino'],
            '3rd District' => ['Tuguegarao City', 'Tuguegarao', 'Amulung', 'Enrile', 'Iguig', 'Peñablanca', 'Penablanca', 'Solana', 'Tuao'],
        ];

        $isabela = [
            '1st District' => ['Cabagan', 'Cagaban', 'Delfin Albano', 'Divilacan', 'Ilagan City', 'Ilagan', 'Maconacon', 'San Pablo', 'Santa Maria', 'Santo Tomas', 'Tumauini'],
            '2nd District' => ['Benito Soliven', 'Gamu', 'Naguilian', 'Palanan', 'Reina Mercedes', 'San Mariano'],
            '3rd District' => ['Alicia', 'Angadanan', 'Cabatuan', 'Ramon', 'San Mateo'],
            '4th District' => ['Cordon', 'Dinapigue', 'Jones', 'San Agustin', 'Santiago', 'City of Santiago'],
            '5th District' => ['Aurora', 'Burgos', 'Luna', 'Mallig', 'Quezon', 'Quirino', 'Roxas', 'San Manuel'],
            '6th District' => ['Cauayan City', 'Cauayan', 'Echague', 'San Guillermo', 'San Isidro'],
        ];

        $lone = [
            'Batanes' => ['Basco', 'Itbayat', 'Ivana', 'Mahatao', 'Sabtang', 'Uyugan'],
            'Nueva Vizcaya' => ['Alfonso Castañeda', 'Alfonso Castaneda', 'Ambaguio', 'Aritao', 'Bagabag', 'Bambang', 'Bayombong', 'Diadi', 'Dupax Del Norte', 'Dupax del Norte', 'Dupax Del Sur', 'Dupax del Sur', 'Kasibu', 'Kayapa', 'Quezon', 'Santa Fe', 'Solano', 'Villaverde'],
            'Quirino' => ['Aglipay', 'Cabarroguis', 'Diffun', 'Maddela', 'Nagtipunan', 'Saguday'],
        ];

        $rows = [];
        foreach ($cagayan as $district => $municipalities) {
            foreach ($municipalities as $municipality) {
                $rows[] = ['province' => 'Cagayan', 'municipality' => $municipality, 'district' => $district];
            }
        }
        foreach ($isabela as $district => $municipalities) {
            foreach ($municipalities as $municipality) {
                $rows[] = ['province' => 'Isabela', 'municipality' => $municipality, 'district' => $district];
            }
        }
        foreach ($lone as $province => $municipalities) {
            foreach ($municipalities as $municipality) {
                $rows[] = ['province' => $province, 'municipality' => $municipality, 'district' => 'Lone District'];
            }
        }

        DB::table('legislative_districts')->insertOrIgnore(array_map(fn ($r) => $r + ['created_at' => now(), 'updated_at' => now()], $rows));
    }
}
