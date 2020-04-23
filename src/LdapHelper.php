<?php
declare(strict_types=1);

namespace JotaEleSalinas\AdminlessLdap;

use Adldap\Laravel\Facades\Adldap;

class LdapHelper
{
    protected $user_key = null;
    protected $user_full_dn_fmt = null;
    protected $sync_attributes = null;

    // Makes sure that the needed config options exist and caches them
    public function __construct($config)
    {
        $this->user_key = $config['identifiers']['ldap']['locate_users_by'];
        //if (!$this->user_key) {
        //    throw new \Exception('LdapHelper: missing config "ldap_auth.identifiers.ldap.locate_users_by".');
        //}

        $this->user_full_dn_fmt = $config['identifiers']['ldap']['user_format'];
        //if (!$this->user_full_dn_fmt) {
        //    throw new \Exception('LdapHelper: missing config "ldap_auth.identifiers.ldap.user_format".');
        //}

        $this->sync_attributes = $config['sync_attributes'];
    }

    // Retrieves an LDAP user by identifier, no password checking yet
    public function retrieveUser(string $identifier) : ?array
    {
        if ($identifier === '') {
            return null;
        }

        $ldapuser = Adldap::search()->where($this->user_key, '=', $identifier)->first();
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
    public function checkCredentials(string $identifier, string $password) : bool
    {
        if ($identifier === '') {
            return false;
        }

        $userdn = sprintf($this->user_full_dn_fmt, $identifier);

        // you might need this, as reported in
        // [#14](https://github.com/jotaelesalinas/laravel-simple-ldap-auth/issues/14):
        //Adldap::auth()->bind($userdn, $password, $bindAsUser = true);
        return Adldap::auth()->attempt($userdn, $password, $bindAsUser = true);
    }
}
