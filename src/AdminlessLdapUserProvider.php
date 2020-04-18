<?php

namespace JotaEleSalinas\AdminlessLdap;

use Adldap\Laravel\Facades\Adldap;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Auth;

class AdminlessLdapUserProvider implements UserProvider
{
    /**
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        if ( !config('auth.key_user_field') ) {
            throw new \Exception('AdminlessLdapUserProvider: missing config "auth.key_user_field".');
        }

        return $this->retrieveByCredentials([config('auth.key_user_field') => $identifier]);
    }

    /**
     * @param  mixed   $identifier
     * @param  string  $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {
        // Get and return a user by their unique identifier and "remember me" token
        throw new \Exception('AdminlessLdapUserProvider: Not possible to use "remember me" tokens.');
    }

    /**
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        // Save the given "remember me" token for the given user
        throw new \Exception('AdminlessLdapUserProvider: Not possible to use "remember me" tokens.');
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        if ( !config('auth.key_user_field') ) {
            throw new \Exception('AdminlessLdapUserProvider: missing config "auth.key_user_field".');
        }

        $username_field = config('auth.key_user_field');

        $username = $credentials[$username_field];
        $password = ''; //$credentials['password'];
        
        $sync_attrs = $this->retrieveSyncAttributes($username, $password);
        if ( !$sync_attrs ) {
            return null;
        }

        $user = new LdapUser();
        $user->$username_field = $username;
        foreach ($sync_attrs as $field => $value) {
            $user->$field = $value !== null ? $value : '';
        }

        return $user;
    }

    protected function retrieveSyncAttributes($username, $password)
    {
        $configvar = 'ldap_auth.identifiers.ldap.locate_users_by';
        if ( !config($configvar, null) ) {
            throw new \Exception('AdminlessLdapUserProvider: missing config "' . $configvar . '".');
        }

        $ldapuser = Adldap::search()->where(config($configvar), '=', $username)->first();
        if ( !$ldapuser ) {
            // log error
            return null;
        }
        // if you want to see the list of available attributes in your specific LDAP server:
        // dd($ldapuser);
        // and look for `attributes` (protected)
        
        // needed if any attribute is not directly accessible via a method call.
        // attributes in \Adldap\Models\User are protected, so we will need
        // to retrieve them using reflection.
        $ldapuser_attrs = null;

        foreach (config('ldap_auth.sync_attributes', []) as $local_attr => $ldap_attr) {
            if ( $local_attr == config('auth.key_user_field') ) {
                continue;
            }

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

    protected static function accessProtected ($obj, $prop)
    {
        $reflection = new \ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if ( !config('auth.key_user_field') ) {
            throw new \Exception('AdminlessLdapUserProvider: missing config "auth.key_user_field".');
        }

        $keyfield = config('auth.key_user_field');
        $username = $credentials[$keyfield];
        
        if ( $user->$keyfield !== $username ) {
            return false;
        }

        $password = $credentials['password'];

        return $this->bind($username, $password);
    }

    protected function bind ($username, $password) {
        $user_format = env('LDAP_USER_FORMAT', null);
        if ( !$user_format ) {
            throw new \Exception('AdminlessLdapUserProvider: missing env "LDAP_USER_FORMAT".');
        }
        $userdn = sprintf($user_format, $username);

        // you might need this, as reported in
        // [#14](https://github.com/jotaelesalinas/laravel-simple-ldap-auth/issues/14):
        //Adldap::auth()->bind($userdn, $password);
        return Adldap::auth()->attempt($userdn, $password, $bindAsUser = true);
    }

}
