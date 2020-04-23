<?php
declare(strict_types=1);

namespace JotaEleSalinas\AdminlessLdap;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class AdminlessUserProvider implements UserProvider
{
    protected $ldap_helper = null;

    public function __construct(LdapHelper $ldap_helper)
    {
        $this->ldap_helper = $ldap_helper;
    }

    public function retrieveById($identifier)
    {
        return $this->retrieveByCredentials([LdapUser::keyName() => $identifier]);
    }

    public function retrieveByToken($identifier, $token)
    {
        throw new \Exception('AdminlessUserProvider: Not possible to use "remember me" tokens.');
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        throw new \Exception('AdminlessUserProvider: Not possible to use "remember me" tokens.');
    }

    public function retrieveByCredentials(array $credentials) : ?Authenticatable
    {
        // despite the name, Laravel docs clearly specify that this method should not
        // check if the password is ok, only retrieve a user by identifier
        $username = $credentials[LdapUser::keyName()];

        $userdata = $this->ldap_helper->retrieveUser($username);
        if (!$userdata) {
            return null;
        }

        return new LdapUser($userdata);
    }

    public function validateCredentials(Authenticatable $user, array $credentials) : bool
    {
        // this is where the identifier and password are checked
        $keyfield = LdapUser::keyName();
        $identifier = $credentials[$keyfield];

        // check that the identifier of the user matches the one in the credentials
        if ($user->$keyfield !== $identifier) {
            return false;
        }

        $password = $credentials['password'];

        // check identifier and password against LDAP server
        return $this->ldap_helper->checkCredentials($identifier, $password);
    }
}
