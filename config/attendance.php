<?php

return [
    'finalization_grace_minutes' => (int) env('ATTENDANCE_FINALIZATION_GRACE_MINUTES', 180),
    'offline_sync_window_hours' => (int) env('ATTENDANCE_OFFLINE_SYNC_WINDOW_HOURS', 36),
];
