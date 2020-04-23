<?php
declare(strict_types=1);

namespace JotaEleSalinas\AdminlessLdap;

use Adldap\Laravel\Facades\Adldap;
use Illuminate\Contracts\Auth\Authenticatable;

class LdapHelper
{
    protected $search_field = null;
    protected $bind_field = null;
    protected $user_full_dn_fmt = null;
    protected $sync_attributes = null;

    // Makes sure that the needed config options exist and caches them
    public function __construct($config)
    {
        $this->search_field = $config['identifiers']['ldap']['locate_users_by'];
        $this->bind_field = $config['identifiers']['ldap']['bind_users_by'];
        $this->user_full_dn_fmt = $config['identifiers']['ldap']['user_format'];
        $this->sync_attributes = $config['sync_attributes'];
    }

    // Retrieves an LDAP user by identifier, no password checking yet
    public function retrieveUser(string $identifier) : ?array
    {
        if ($identifier === '') {
            return null;
        }

        $ldapuser = Adldap::search()->where($this->search_field, '=', $identifier)->first();
        if (!$ldapuser) {
            // log error
            return null;
        }
        // if you want to see the list of available attributes in your specific LDAP server:
        // dd($ldapuser);
        // and look for `attributes` (protected)

        $attrs = [];

        // needed if any attribute is not directly accessible via a method call.
        // attributes in \Adldap\Models\User are protected, so we will need
        // to retrieve them using reflection.
        $ldapuser_attrs = null;

        foreach ($this->sync_attributes as $local_attr => $ldap_attr) {
            $method = 'get' . $ldap_attr;
            if (method_exists($ldapuser, $method)) {
                $attrs[$local_attr] = $ldapuser->$method();
                continue;
            }

            if ($ldapuser_attrs === null) {
                $ldapuser_attrs = self::accessProtected($ldapuser, 'attributes');
            }

            if (!isset($ldapuser_attrs[$ldap_attr])) {
                // an exception could be thrown
                $attrs[$local_attr] = null;
                continue;
            }

            if (!is_array($ldapuser_attrs[$ldap_attr])) {
                $attrs[$local_attr] = $ldapuser_attrs[$ldap_attr];
            }

            if (count($ldapuser_attrs[$ldap_attr]) == 0) {
                // an exception could be thrown
                $attrs[$local_attr] = null;
                continue;
            }

            // now it returns the first item, but it could return
            // a comma-separated string or any other thing that suits you better
            $attrs[$local_attr] = $ldapuser_attrs[$ldap_attr][0];
            //$attrs[$local_attr] = implode(',', $ldapuser_attrs[$ldap_attr]);
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
    public function checkCredentials(Authenticatable $user, string $identifier, string $password) : bool
    {
        if ($identifier === '') {
            return false;
        }
        
        $search = $this->search_field;
        if ($user->$search != $identifier) {
            return false;
        }

        $bind = $this->bind_field;
        $userdn = sprintf($this->user_full_dn_fmt, $user->$bind);

        // you might need this, as reported in
        // [#14](https://github.com/jotaelesalinas/laravel-simple-ldap-auth/issues/14):
        //Adldap::auth()->bind($userdn, $password, $bindAsUser = true);
        return Adldap::auth()->attempt($userdn, $password, $bindAsUser = true);
    }
}
