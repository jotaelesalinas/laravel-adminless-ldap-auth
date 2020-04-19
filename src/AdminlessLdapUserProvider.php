<?php

namespace JotaEleSalinas\AdminlessLdap;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class AdminlessLdapUserProvider implements UserProvider
{
    protected $ldap_helper = null;

    function __construct (LdapHelper $ldap_helper)
    {
        $this->ldap_helper = $ldap_helper;
    }

    public function retrieveById($identifier)
    {
        return $this->retrieveByCredentials([LdapUser::keyName() => $identifier]);
    }

    public function retrieveByToken($identifier, $token)
    {
        // Get and return a user by their unique identifier and "remember me" token
        throw new \Exception('AdminlessLdapUserProvider: Not possible to use "remember me" tokens.');
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        // Save the given "remember me" token for the given user
        throw new \Exception('AdminlessLdapUserProvider: Not possible to use "remember me" tokens.');
    }

    public function retrieveByCredentials(array $credentials)
    {
        $username = $credentials[LdapUser::keyName()];
        
        $userdata = $this->ldap_helper->retrieveUser($username);
        if ( !$userdata ) {
            return null;
        }

        return new LdapUser($userdata);
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        $keyfield = LdapUser::keyName();
        $username = $credentials[$keyfield];
        
        if ( $user->$keyfield !== $username ) {
            return false;
        }

        $password = $credentials['password'];

        return $this->ldap_helper->bind($username, $password);
    }
}
