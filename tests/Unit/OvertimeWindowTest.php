<?php

use App\Support\OvertimeWindow;

it('measures a plain evening stretch', function (): void {
    expect(OvertimeWindow::hoursBetween('18:00', '20:00'))->toBe(2.0)
        ->and(OvertimeWindow::hoursBetween('18:30', '21:15'))->toBe(2.75);
});

it('carries a stretch that runs past midnight into the next day', function (): void {
    // The common shape for evening overtime; treating 22:00–01:00 as negative
    // (or as 21 hours) would be a payroll error either way.
    expect(OvertimeWindow::hoursBetween('22:00', '01:00'))->toBe(3.0)
        ->and(OvertimeWindow::hoursBetween('23:30', '00:30'))->toBe(1.0);
});

it('treats an identical start and end as a full day, not zero', function (): void {
    // Only reachable by picking the same time twice, and 24 hours is over the
    // cap — so it is refused rather than silently filed as nothing.
    expect(OvertimeWindow::hoursBetween('19:00', '19:00'))->toBe(24.0)
        ->and(OvertimeWindow::isPlausible('19:00', '19:00'))->toBeFalse();
});

it('accepts seconds in the stored time format', function (): void {
    // MySQL hands back `18:00:00`; the picker sends `18:00`.
    expect(OvertimeWindow::hoursBetween('18:00:00', '20:00:00'))->toBe(2.0);
});

it('rejects a stretch too short or too long to be a shift', function (): void {
    expect(OvertimeWindow::isPlausible('18:00', '18:15'))->toBeFalse()
        ->and(OvertimeWindow::isPlausible('08:00', '21:00'))->toBeFalse()
        ->and(OvertimeWindow::isPlausible('18:00', '18:30'))->toBeTrue()
        ->and(OvertimeWindow::isPlausible('09:00', '21:00'))->toBeTrue();
});

it('labels a range for display and stays quiet without one', function (): void {
    expect(OvertimeWindow::label('18:00:00', '20:30:00'))->toBe('18:00 – 20:30')
        ->and(OvertimeWindow::label(null, '20:00'))->toBeNull()
        ->and(OvertimeWindow::label('18:00', null))->toBeNull();
});
