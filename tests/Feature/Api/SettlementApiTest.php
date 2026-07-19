<?php

use App\Models\Employee;
use App\Models\Settlement;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->employeeUser = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->employeeUser->employee;

    $this->tokenFor = function (string $email): string {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password'])->json('access_token');
    };

    $this->auth = function (string $token) {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };
});

/**
 * A settlement owned by the given employee, carrying trip context and one
 * expense line so the detail payload has something to shape.
 */
function apiSettlement(Employee $employee, string $status = Settlement::STATUS_SUBMITTED): Settlement
{
    $settlement = Settlement::create([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'number' => 'STL-API-'.fake()->unique()->numberBetween(1000, 9999),
        'title' => 'Settlement Perdin Jakarta',
        'category' => 'penerbangan',
        'submission_date' => '2026-07-18',
        'destination' => 'Jakarta, Indonesia',
        'trip_start_date' => '2026-07-18',
        'trip_end_date' => '2026-07-21',
        'destination_latitude' => -6.2088,
        'destination_longitude' => 106.8456,
        'status' => $status,
    ]);

    $settlement->items()->create([
        'tenant_id' => $employee->tenant_id,
        'category' => 'penerbangan',
        'description' => 'Flight Ticket',
        'detail' => 'Garuda Indonesia GA-204',
        'amount' => 2_500_000,
    ]);

    $settlement->recalculateTotals();

    return $settlement->fresh();
}

it('lists only the caller own settlements', function (): void {
    $mine = apiSettlement($this->employee);

    $colleague = Employee::forTenant($this->employee->tenant_id)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();
    $theirs = apiSettlement($colleague);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    $response = ($this->auth)($token)
        ->getJson('/api/v1/me/settlements')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'number', 'title', 'total', 'status', 'destination', 'submission_date']]]);

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($theirs->id);
});

it('returns trip context, expense lines, receipts and timeline on the detail', function (): void {
    Storage::fake('public');

    $settlement = apiSettlement($this->employee);
    $settlement->attachments()->create([
        'tenant_id' => $settlement->tenant_id,
        'path' => 'settlements/receipt.jpg',
        'original_name' => 'Airfare_Receipt.jpg',
        'size' => 1024,
    ]);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    $body = ($this->auth)($token)
        ->getJson("/api/v1/me/settlements/{$settlement->id}")
        ->assertOk()
        ->json('data');

    expect($body['travel']['destination'])->toBe('Jakarta, Indonesia')
        ->and($body['travel']['days'])->toBe(4)
        ->and($body['travel']['latitude'])->toBe(-6.2088)
        ->and($body['items'][0]['detail'])->toBe('Garuda Indonesia GA-204')
        ->and($body['items'][0]['icon'])->toBe('flight')
        ->and($body['items'][0]['category_label'])->toBe('Tiket Pesawat')
        ->and($body['documents'][0]['name'])->toBe('Airfare_Receipt.jpg')
        ->and($body['subtotal'])->toBe(2_500_000)
        ->and($body['tax_amount'])->toBe(275_000)
        ->and($body['total'])->toBe(2_775_000)
        ->and(collect($body['timeline'])->pluck('key')->all())
        ->toBe(['submitted', 'manager_approved', 'finance_verified', 'paid']);
});

it('hides a settlement belonging to another employee', function (): void {
    $colleague = Employee::forTenant($this->employee->tenant_id)
        ->where('id', '!=', $this->employee->id)
        ->firstOrFail();
    $theirs = apiSettlement($colleague);

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->getJson("/api/v1/me/settlements/{$theirs->id}")
        ->assertNotFound();
});

it('files a settlement with trip context and receipts', function (): void {
    Storage::fake('public');

    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    $response = ($this->auth)($token)
        ->postJson('/api/v1/me/settlements', [
            'title' => 'Settlement Perdin Surabaya',
            'submission_date' => '2026-07-19',
            'destination' => 'Surabaya, Indonesia',
            'trip_start_date' => '2026-07-15',
            'trip_end_date' => '2026-07-17',
            'destination_latitude' => -7.2575,
            'destination_longitude' => 112.7521,
            'items' => [
                ['description' => 'Hotel Accommodation', 'detail' => 'The Ritz-Carlton (3 Nights)', 'category' => 'akomodasi', 'amount' => 1_800_000],
                ['description' => 'Transport/Taxi', 'detail' => 'Airport transfer', 'category' => 'transportasi', 'amount' => 350_000],
            ],
            'documents' => [UploadedFile::fake()->image('receipt.jpg')],
            'action' => 'submit',
        ])
        ->assertCreated();

    $settlement = Settlement::findOrFail($response->json('data.id'));

    expect($settlement->employee_id)->toBe($this->employee->id)
        ->and($settlement->status)->toBe(Settlement::STATUS_SUBMITTED)
        ->and($settlement->destination)->toBe('Surabaya, Indonesia')
        ->and($settlement->tripDays())->toBe(3)
        ->and($settlement->number)->toStartWith('STL-')
        ->and((float) $settlement->subtotal)->toBe(2_150_000.0)
        ->and((float) $settlement->total)->toBe(2_386_500.0)
        ->and($settlement->items)->toHaveCount(2)
        ->and($settlement->attachments)->toHaveCount(1);
});

it('keeps a drafted settlement out of the manager queue', function (): void {
    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    $response = ($this->auth)($token)
        ->postJson('/api/v1/me/settlements', [
            'title' => 'Draft dulu',
            'submission_date' => '2026-07-19',
            'items' => [['description' => 'Tiket kereta', 'category' => 'transportasi', 'amount' => 500_000]],
            'action' => 'draft',
        ])
        ->assertCreated();

    expect(Settlement::findOrFail($response->json('data.id'))->status)
        ->toBe(Settlement::STATUS_DRAFT);
});

it('rejects a trip that ends before it starts', function (): void {
    $token = ($this->tokenFor)('bagus.p@nusantara.co.id');

    ($this->auth)($token)
        ->postJson('/api/v1/me/settlements', [
            'title' => 'Tanggal terbalik',
            'submission_date' => '2026-07-19',
            'trip_start_date' => '2026-07-20',
            'trip_end_date' => '2026-07-18',
            'items' => [['description' => 'Tiket', 'category' => 'transportasi', 'amount' => 100_000]],
            'action' => 'submit',
        ])
        ->assertJsonValidationErrors('trip_end_date');
});

it('refuses an unauthenticated caller', function (): void {
    $this->getJson('/api/v1/me/settlements')->assertUnauthorized();
});
