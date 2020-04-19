<?php
namespace JotaEleSalinas\AdminlessLdap;

use Auth;
use Illuminate\Support\ServiceProvider;

class AdminlessLdapServiceProvider extends ServiceProvider {
    public function boot()
    {
        Auth::provider('adminless_ldap', function($app, array $config) {
            return new AdminlessLdapUserProvider(new LdapHelper($config));
        });
    }
}