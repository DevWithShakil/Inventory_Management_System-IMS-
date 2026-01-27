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
    Setting::create([
        'company_name' => 'SmartIMS Demo',
        'company_email' => 'support@smartims.com',
        'company_phone' => '+8801700000000',
        'company_address' => 'Dhaka, Bangladesh',
        'currency_symbol' => '৳',
        'invoice_footer_text' => 'Software by SmartIMS',
    ]);
}
}
