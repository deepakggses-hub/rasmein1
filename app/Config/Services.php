<?php

declare(strict_types=1);

namespace Config;

use App\Models\SettingModel;
use App\Services\AuditService;
use App\Services\SettingsService;
use CodeIgniter\Config\BaseService;

/**
 * Application services.
 *
 * Register shared, long-lived collaborators here so controllers and views
 * resolve the same instance instead of newing one up each time.
 */
class Services extends BaseService
{
    /** Runtime admin settings, including the Buy/Enquire master switch. */
    public static function settings(bool $getShared = true): SettingsService
    {
        if ($getShared) {
            return static::getSharedInstance('settings');
        }

        return new SettingsService(model(SettingModel::class));
    }

    /** Writes the admin audit trail. */
    public static function audit(bool $getShared = true): AuditService
    {
        if ($getShared) {
            return static::getSharedInstance('audit');
        }

        return new AuditService();
    }
}
