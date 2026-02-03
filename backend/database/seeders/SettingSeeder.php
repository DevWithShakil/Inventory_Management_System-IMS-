<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $setting = Setting::first();

        if (!$setting) {
            Setting::create([
                'company_name' => 'SMART IMS',
                'company_email' => 'support@ims.com',
                'company_phone' => '+8801700000000',
                'company_address' => 'Dhaka, Bangladesh',
                'currency_symbol' => '৳',
                'logo' => null,
                'invoice_footer_text' => 'Software by IMS',
            ]);
        }
    }
}
