<?php

namespace App\Providers;


use App\Models\ClinicAppointment;
use App\Models\LabMaterial;
use App\Models\MaterialProduct;
use App\Observers\ClinicAppointmentObserver;
use App\Observers\LabMaterialObserver;
use App\Observers\MaterialProductObserver;
use App\Repositories\Access\RoleAccessRepository;
use App\Repositories\Chat\Message\MessageRepository;
use App\Repositories\Chat\Team\TeamRepository;
use App\Repositories\Clinic\Billing\ClinicBillingRepository;
use App\Repositories\Clinic\Billing\ClinicBillingRepositoryInterface;
use App\Repositories\Clinic\DentalLab\ClinicDentalLabRepository;
use App\Repositories\Clinic\DentalLab\ClinicDentalLabRepositoryInterface;
use App\Repositories\Clinic\Select\ClinicSelectRepository;
use App\Repositories\Clinic\Select\ClinicSelectRepositoryInterface;
use App\Repositories\Clinic\Settings\ClinicSettingsRepository;
use App\Repositories\Clinic\Settings\ClinicSettingsRepositoryInterface;
use App\Repositories\Clinic\Task\ClinicTaskRepository;
use App\Repositories\Clinic\Task\ClinicTaskRepositoryInterface;
use App\Repositories\Contracts\Access\RoleAccessRepositoryInterface;
use App\Repositories\Contracts\Chat\Message\MessageRepositoryInterface;
use App\Repositories\Contracts\Chat\Team\TeamRepositoryInterface;
use App\Repositories\Contracts\SuperAdmin\RoleManagementRepositoryInterface;
use App\Repositories\Contracts\SuperAdmin\SettingsRepositoryInterface as SuperAdminSettingsRepositoryInterface;
use App\Repositories\Contracts\SuperAdmin\SubscriptionDashboardRepositoryInterface;
use App\Repositories\Contracts\SuperAdmin\UserManagementRepositoryInterface;
use App\Repositories\Lab\Clinic\ClinicRepository;
use App\Repositories\Lab\Clinic\ClinicRepositoryInterface;
use App\Repositories\Lab\Lookup\LookupRepository;
use App\Repositories\Lab\Lookup\LookupRepositoryInterface;
use App\Repositories\Lab\Settings\ServiceRepository;
use App\Repositories\Lab\Settings\ServiceRepositoryInterface;
use App\Repositories\Lab\Settings\SettingsRepository;
use App\Repositories\Lab\Settings\SettingsRepositoryInterface;
use App\Repositories\Lab\Settings\UserRepository;
use App\Repositories\Lab\Settings\UserRepositoryInterface;
use App\Repositories\SuperAdmin\RoleManagementRepository;
use App\Repositories\SuperAdmin\SettingsRepository as SuperAdminSettingsRepository;
use App\Repositories\SuperAdmin\SubscriptionDashboardRepository;
use App\Repositories\SuperAdmin\UserManagementRepository;
use App\Services\Clinic\WhatsappBot\Providers\MetaWhatsAppService;
use App\Services\Clinic\WhatsappBot\Providers\TwilioWhatsAppService;
use App\Services\Clinic\WhatsappBot\Providers\WhatsAppProviderInterface;
use App\Services\Sms\ConfiguredHttpSmsProvider;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RoleAccessRepositoryInterface::class, RoleAccessRepository::class);
        $this->app->bind(UserManagementRepositoryInterface::class, UserManagementRepository::class);
        $this->app->bind(RoleManagementRepositoryInterface::class, RoleManagementRepository::class);
        $this->app->bind(SuperAdminSettingsRepositoryInterface::class, SuperAdminSettingsRepository::class);

        $this->app->bind(
            SubscriptionDashboardRepositoryInterface::class,
            SubscriptionDashboardRepository::class
        );

        $this->app->bind(ClinicRepositoryInterface::class, ClinicRepository::class);
        $this->app->bind(LookupRepositoryInterface::class, LookupRepository::class);

        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);
        $this->app->bind(SettingsRepositoryInterface::class, SettingsRepository::class);

        $this->app->bind(ClinicSettingsRepositoryInterface::class, ClinicSettingsRepository::class);
        $this->app->bind(ClinicBillingRepositoryInterface::class, ClinicBillingRepository::class);
        $this->app->bind(ClinicDentalLabRepositoryInterface::class, ClinicDentalLabRepository::class);
        $this->app->bind(ClinicTaskRepositoryInterface::class, ClinicTaskRepository::class);
        $this->app->bind(ClinicSelectRepositoryInterface::class, ClinicSelectRepository::class);


        // Chat Repository Binding
        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);
        $this->app->bind(TeamRepositoryInterface::class, TeamRepository::class);
        $this->app->bind(WhatsAppProviderInterface::class, function ($app) {
            return match ((string) config('services.whatsapp.provider')) {
                'twilio', 'twilio_whatsapp_api' => $app->make(TwilioWhatsAppService::class),
                default => $app->make(MetaWhatsAppService::class),
            };
        });
        $this->app->bind(SmsProviderInterface::class, ConfiguredHttpSmsProvider::class);

    }

    public function boot(): void
    {
        ClinicAppointment::observe(ClinicAppointmentObserver::class);
        LabMaterial::observe(LabMaterialObserver::class);
        MaterialProduct::observe(MaterialProductObserver::class);
    }
}
