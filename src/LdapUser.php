<?php
declare(strict_types=1);

namespace JotaEleSalinas\AdminlessLdap;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Config;

class LdapUser extends GenericUser implements AuthorizableContract
{
    use Authorizable;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    // The name of the property that uniquely identifies the users
    public static function keyName() : string
    {
        $column_name = Config::get('auth.auth_user_key', null);
        if (!$column_name) {
            throw new \Exception('LdapUser: missing config variable "auth.auth_user_key".');
        }
        return $column_name;
    }

    public function getAuthIdentifierName()
    {
        return self::keyName();
    }

    public function getRememberToken()
    {
        return null;
    }

    public function getRememberTokenName()
    {
        return null;
    }

    public function __debugInfo()
    {
        return $this->attributes;
    }
}
