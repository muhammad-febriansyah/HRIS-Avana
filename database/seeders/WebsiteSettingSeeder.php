<?php

namespace Database\Seeders;

use App\Models\WebsiteSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the singleton platform website settings row (id=1) with the default
 * "Avana HR" branding, SEO meta, social links and contact info. Branding image
 * paths are left null since they require real uploaded files.
 */
class WebsiteSettingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        WebsiteSetting::current()->update([
            'site_name' => 'Avana HR',
            'tagline' => 'Solusi HRIS Modern untuk Bisnis Indonesia',
            'meta_title' => 'Avana HR — Software HRIS & Payroll Terpadu',
            'meta_description' => 'Avana HR adalah platform HRIS all-in-one: absensi, cuti, payroll, kinerja, dan rekrutmen dalam satu sistem cloud.',
            'meta_keywords' => 'hris, payroll, absensi, cuti, kinerja, rekrutmen, hr software indonesia',
            'social_facebook' => 'https://facebook.com/avanahr',
            'social_instagram' => 'https://instagram.com/avanahr',
            'social_twitter' => 'https://twitter.com/avanahr',
            'social_youtube' => 'https://youtube.com/@avanahr',
            'social_linkedin' => 'https://linkedin.com/company/avanahr',
            'social_tiktok' => 'https://tiktok.com/@avanahr',
            'contact_email' => 'halo@avanahr.id',
            'contact_phone' => '+62 21 5099 8877',
            'contact_whatsapp' => '+62 812 3456 7890',
            'contact_address' => 'Gedung Cyber 2 Lantai 15, Jl. HR. Rasuna Said Blok X-5, Jakarta Selatan 12950',
        ]);
    }
}
