<?php

use App\Models\OnboardingSlide;

it('returns active onboarding slides in order, without auth', function (): void {
    OnboardingSlide::create(['title' => 'Kedua', 'subtitle' => 'B', 'sort_order' => 1, 'is_active' => true]);
    OnboardingSlide::create(['title' => 'Pertama', 'subtitle' => 'A', 'sort_order' => 0, 'is_active' => true]);
    OnboardingSlide::create(['title' => 'Nonaktif', 'subtitle' => 'X', 'sort_order' => 2, 'is_active' => false]);

    $response = $this->getJson('/api/v1/onboarding-slides')->assertOk();

    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.title', 'Pertama');
    $response->assertJsonPath('data.1.title', 'Kedua');
    $response->assertJsonStructure(['data' => [['id', 'title', 'subtitle', 'image_url', 'order']]]);
});

it('returns an empty list when there are no slides', function (): void {
    $this->getJson('/api/v1/onboarding-slides')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
