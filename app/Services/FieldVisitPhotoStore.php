<?php

namespace App\Services;

use App\Models\FieldVisit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores and removes the photo evidence attached to a field visit, so the API
 * and the Avana web form handle uploads the same way.
 */
final class FieldVisitPhotoStore
{
    /**
     * How many photos one visit may carry.
     */
    public const MAX = 5;

    /**
     * Validation rules for a photos[] upload.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'photos' => ['nullable', 'array', 'max:'.self::MAX],
            'photos.*' => ['image', 'max:4096'],
        ];
    }

    /**
     * Attach uploaded files to a visit as photo rows.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public static function attach(FieldVisit $visit, array $files): void
    {
        foreach ($files as $file) {
            $visit->photos()->create([
                'tenant_id' => $visit->tenant_id,
                'employee_id' => $visit->employee_id,
                'file_path' => $file->store('field-visits', 'public'),
            ]);
        }
    }

    /**
     * Public URLs for a visit's photos, oldest first.
     *
     * @return list<string>
     */
    public static function urls(FieldVisit $visit): array
    {
        return $visit->photos
            ->sortBy('id')
            ->map(fn (mixed $photo): string => Storage::disk('public')->url($photo->file_path))
            ->values()
            ->all();
    }

    /**
     * Delete a visit's photo files from disk. The rows themselves go with the
     * visit through the cascading foreign key.
     */
    public static function purge(FieldVisit $visit): void
    {
        foreach ($visit->photos as $photo) {
            Storage::disk('public')->delete($photo->file_path);
        }
    }
}
