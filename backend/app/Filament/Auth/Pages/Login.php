<?php

namespace App\Filament\Auth\Pages;

use Filament\Schemas\Components\Component;

class Login extends \Filament\Auth\Pages\Login
{
    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->default((string) env('FILAMENT_ADMIN_EMAIL', ''));
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->default((string) env('FILAMENT_ADMIN_PASSWORD', ''));
    }
}
