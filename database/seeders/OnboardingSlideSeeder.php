<?php

namespace Database\Seeders;

use App\Models\OnboardingSlide;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds 3 demo onboarding slides for the mobile app intro carousel, reusing the
 * vector illustrations bundled with the Flutter app. Each stub SVG is copied to
 * the public disk so it is served with a real URL, exactly like an uploaded one.
 */
class OnboardingSlideSeeder extends Seeder
{
    use WithoutModelEvents;

    private const DISK = 'public';

    private const FOLDER = 'onboarding';

    public function run(): void
    {
        $slides = [
            ['stub' => 'attendance.svg', 'title' => 'Absensi Praktis', 'subtitle' => 'Clock-in & clock-out langsung dari ponsel, di mana saja.'],
            ['stub' => 'growth.svg', 'title' => 'Kelola Karier', 'subtitle' => 'Pantau kinerja, cuti, dan pengembangan diri dalam satu aplikasi.'],
            ['stub' => 'leave_payroll.svg', 'title' => 'Cuti & Slip Gaji', 'subtitle' => 'Ajukan cuti dan lihat slip gaji kapan pun dibutuhkan.'],
        ];

        $stubDir = database_path('seeders/stubs/onboarding');

        foreach ($slides as $index => $slide) {
            $imagePath = null;
            $source = $stubDir.'/'.$slide['stub'];

            if (is_file($source)) {
                $target = self::FOLDER.'/'.$slide['stub'];
                Storage::disk(self::DISK)->put($target, (string) file_get_contents($source));
                $imagePath = $target;
            }

            OnboardingSlide::updateOrCreate(
                ['title' => $slide['title']],
                [
                    'subtitle' => $slide['subtitle'],
                    'image_path' => $imagePath,
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }
}
