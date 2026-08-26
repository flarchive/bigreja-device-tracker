<?php

use Flarum\Extend;
use SuperBraga\DeviceTracker\Api\Controller\ListSharedDevicesController;
use SuperBraga\DeviceTracker\Middleware\TrackDeviceMiddleware;
use SuperBraga\DeviceTracker\Notification\DuplicateAccountAlertBlueprint;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js'),

    (new Extend\Notification())
        ->type(DuplicateAccountAlertBlueprint::class, ['alert']),

    (new Extend\Routes('api'))
        ->get('/device-tracker/shared', 'device-tracker.shared.index', ListSharedDevicesController::class),

    (new Extend\Middleware('forum'))->add(TrackDeviceMiddleware::class),
    (new Extend\Middleware('api'))->add(TrackDeviceMiddleware::class),
];