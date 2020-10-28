<?php
declare(strict_types=1);

namespace JotaEleSalinas\AdminlessLdap;

use Adldap\Laravel\Facades\Adldap;
use Illuminate\Contracts\Auth\Authenticatable;

class LdapHelper
{
    protected $connection = null;
    protected $search_field = null;
    protected $bind_field = null;
    protected $user_full_dn_fmt = null;
    protected $sync_attributes = null;

    // Makes sure that the needed config options exist and caches them
    public function __construct($config)
    {
        $this->connection = $config['connection'];
        $this->search_field = $config['identifiers']['ldap']['locate_users_by'];
        $this->bind_field = $config['identifiers']['ldap']['bind_users_by'];
        $this->user_full_dn_fmt = $config['identifiers']['ldap']['user_format'];
        $this->sync_attributes = $config['sync_attributes'];
    }

    public function retrieveLdapAttribs(string $identifier, string $password) : ?array
    {
        static $cached_users = [];

        if ($identifier === '') {
            return null;
        }

        if (isset($cached_users[$identifier])) {
            return $cached_users[$identifier];
        }

        // bind to server as the provided user
        $provider = Adldap::getProvider($this->connection);
        $provider->connect(sprintf($this->user_full_dn_fmt, $identifier), $password);
        
        $ldapuser = Adldap::search()->where($this->search_field, '=', $identifier)->first();
        if (!$ldapuser) {
            // log error
            return null;
        }
        // if you want to see the list of available attributes in your specific LDAP server:
        // dd($ldapuser);
        // and look for `attributes` (protected)
        
        $ldapuser_attrs = self::accessProtected($ldapuser, 'attributes');

        $attrs = [];
        foreach ($ldapuser_attrs as $k => $v) {
            if ($k == 'objectclass') {
                continue;
            } elseif (preg_match('/^\d+$/', $k . '')) {
                continue;
            }
            $attrs[$k] = is_array($v) ? $v[0] : $v;
        }

        $cached_users[$identifier] = $attrs;
        return $attrs;
    }

    // Retrieves an LDAP user by identifier, no password checking yet
    public function retrieveUser(string $identifier, string $password) : ?array
    {
        $user = $this->retrieveLdapAttribs($identifier, $password);
        if (!$user) {
            return null;
        }

        $attrs = [];

        foreach ($this->sync_attributes as $local_attr => $ldap_attr) {
            if (!isset($user[$ldap_attr])) {
                // an exception could be thrown
                $attrs[$local_attr] = null;
                continue;
            }

            $attrs[$local_attr] = $user[$ldap_attr];
        }

        return $attrs;
    }

    // Access to protected properties from the Adldap2 object
    protected static function accessProtected($obj, $prop)
    {
        $reflection = new \ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    // Binds a user to the LDAP server, efectively checking if identifier and password match
    public function checkCredentials(string $identifier, string $password) : bool
    {
        if ($identifier === '') {
            return false;
        }
        
        $user_ldap_attribs = $this->retrieveLdapAttribs($identifier, $password);

        if ($user_ldap_attribs[$this->search_field] != $identifier) {
            return false;
        }

        $userdn = sprintf($this->user_full_dn_fmt, $user_ldap_attribs[$this->bind_field]);

        // you might need this, as reported in
        // [#14](https://github.com/jotaelesalinas/laravel-adminless-ldap-auth/issues/14):
        //Adldap::auth()->bind($userdn, $password, $bindAsUser = true);
        return Adldap::auth()->attempt($userdn, $password, $bindAsUser = true);
    }
}
