<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Icd10Code;
use App\Models\McuItem;
use App\Models\Station;
use Illuminate\Database\Seeder;

class Icd10AndMcuItemSeeder extends Seeder
{
    public function run(): void
    {
        // ── Seed ICD-10 Codes ───────────────────────────────────────────────
        $icd10Data = [
            [
                'code'      => 'Z00.0',
                'name_en'   => 'General medical examination',
                'name_id'   => 'Pemeriksaan medis umum',
                'category'  => 'Special Investigations',
                'is_active' => true,
            ],
            [
                'code'      => 'Z01.0',
                'name_en'   => 'Examination of eyes and vision',
                'name_id'   => 'Pemeriksaan mata dan penglihatan',
                'category'  => 'Special Examinations',
                'is_active' => true,
            ],
            [
                'code'      => 'Z01.1',
                'name_en'   => 'Examination of ears and hearing',
                'name_id'   => 'Pemeriksaan telinga dan pendengaran',
                'category'  => 'Special Examinations',
                'is_active' => true,
            ],
            [
                'code'      => 'E11.9',
                'name_en'   => 'Type 2 diabetes mellitus without complications',
                'name_id'   => 'Diabetes melitus tipe 2 tanpa komplikasi',
                'category'  => 'Endocrine & Metabolic',
                'is_active' => true,
            ],
            [
                'code'      => 'I10',
                'name_en'   => 'Essential (primary) hypertension',
                'name_id'   => 'Hipertensi esensial (primer)',
                'category'  => 'Circulatory System',
                'is_active' => true,
            ],
            [
                'code'      => 'E78.5',
                'name_en'   => 'Hyperlipidemia, unspecified',
                'name_id'   => 'Hiperlipidemia (kolesterol tinggi)',
                'category'  => 'Endocrine & Metabolic',
                'is_active' => true,
            ],
            [
                'code'      => 'K76.0',
                'name_en'   => 'Fatty (change of) liver, not elsewhere classified',
                'name_id'   => 'Perlemakan hati (Fatty Liver)',
                'category'  => 'Digestive System',
                'is_active' => true,
            ],
            [
                'code'      => 'H53.5',
                'name_en'   => 'Color vision deficiencies',
                'name_id'   => 'Defisiensi penglihatan warna (Buta warna)',
                'category'  => 'Eye & Adnexa',
                'is_active' => true,
            ],
        ];

        foreach ($icd10Data as $icd) {
            Icd10Code::firstOrCreate(['code' => $icd['code']], $icd);
        }

        // ── Seed MCU Items ──────────────────────────────────────────────────
        $labStation  = Station::where('name', 'LIKE', '%Lab%')->first();
        $radStation  = Station::where('name', 'LIKE', '%Rontgen%')->orWhere('name', 'LIKE', '%Radiologi%')->first();
        $ecgStation  = Station::where('name', 'LIKE', '%EKG%')->first();
        $physStation = Station::where('name', 'LIKE', '%Fisik%')->first();

        $mcuItemsData = [
            [
                'code'                  => 'LAB_HB',
                'name'                  => 'Hemoglobin (Hb)',
                'category'              => 'Hematologi',
                'unit'                  => 'g/dL',
                'normal_reference_male' => '13.5 - 17.5',
                'normal_reference_female' => '12.0 - 15.5',
                'price'                 => 45000,
                'station_id'            => $labStation?->id,
                'is_active'             => true,
            ],
            [
                'code'                  => 'LAB_LEUKO',
                'name'                  => 'Leukosit (WBC)',
                'category'              => 'Hematologi',
                'unit'                  => '/uL',
                'normal_reference_male' => '4.500 - 11.000',
                'normal_reference_female' => '4.500 - 11.000',
                'price'                 => 45000,
                'station_id'            => $labStation?->id,
                'is_active'             => true,
            ],
            [
                'code'                  => 'LAB_GLU_FAST',
                'name'                  => 'Glukosa Puasa',
                'category'              => 'Metabolisme Karbohidrat',
                'unit'                  => 'mg/dL',
                'normal_reference_male' => '70 - 99',
                'normal_reference_female' => '70 - 99',
                'price'                 => 50000,
                'station_id'            => $labStation?->id,
                'is_active'             => true,
            ],
            [
                'code'                  => 'LAB_CHOLESTEROL',
                'name'                  => 'Kolesterol Total',
                'category'              => 'Profil Lipid',
                'unit'                  => 'mg/dL',
                'normal_reference_male' => '< 200',
                'normal_reference_female' => '< 200',
                'price'                 => 60000,
                'station_id'            => $labStation?->id,
                'is_active'             => true,
            ],
            [
                'code'                  => 'RAD_THORAX',
                'name'                  => 'Foto Rontgen Thorax PA',
                'category'              => 'Radiologi',
                'unit'                  => 'Interpretasi Dokter',
                'normal_reference_male' => 'Cor & Pulmo DBN',
                'normal_reference_female' => 'Cor & Pulmo DBN',
                'price'                 => 175000,
                'station_id'            => $radStation?->id,
                'is_active'             => true,
            ],
            [
                'code'                  => 'EKG_12LEAD',
                'name'                  => 'Elektrokardiografi (EKG 12-Lead)',
                'category'              => 'Kardiologi',
                'unit'                  => 'Interpretasi Dokter',
                'normal_reference_male' => 'Sinus Rhythm, Normal EKG',
                'normal_reference_female' => 'Sinus Rhythm, Normal EKG',
                'price'                 => 120000,
                'station_id'            => $ecgStation?->id,
                'is_active'             => true,
            ],
            [
                'code'                  => 'PHYS_BMI',
                'name'                  => 'Indeks Massa Tubuh (IMT / BMI)',
                'category'              => 'Pemeriksaan Fisik',
                'unit'                  => 'kg/m²',
                'normal_reference_male' => '18.5 - 24.9',
                'normal_reference_female' => '18.5 - 24.9',
                'price'                 => 25000,
                'station_id'            => $physStation?->id,
                'is_active'             => true,
            ],
            [
                'code'                  => 'PHYS_BP',
                'name'                  => 'Tekanan Darah (Sistolik / Diastolik)',
                'category'              => 'Pemeriksaan Fisik',
                'unit'                  => 'mmHg',
                'normal_reference_male' => '< 120/80',
                'normal_reference_female' => '< 120/80',
                'price'                 => 25000,
                'station_id'            => $physStation?->id,
                'is_active'             => true,
            ],
        ];

        foreach ($mcuItemsData as $item) {
            McuItem::firstOrCreate(['code' => $item['code']], $item);
        }
    }
}
