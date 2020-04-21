<?php
declare(strict_types=1);

namespace JotaEleSalinas\AdminlessLdap;

use Illuminate\Support\ServiceProvider;
use Auth;

class AdminlessLdapServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Auth::provider('adminless_ldap', function ($app, $config) {
            return new AdminlessLdapUserProvider(new LdapHelper($config));
        });
    }
}
