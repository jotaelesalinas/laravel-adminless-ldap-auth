<?php
declare(strict_types=1);

namespace JotaEleSalinas\AdminlessLdap;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Config;

class LdapUser extends GenericUser
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    // The name of the property that uniquely identifies the users
    public static function keyName() : string
    {
        $column_name = Config::get('auth.key_user_field', null);
        if (!$column_name) {
            throw new \Exception('LdapUser: Could not find keyName.');
        }
        return $column_name;
    }

    public function getAuthIdentifierName()
    {
        return self::keyName();
    }

    public function getRememberTokenName()
    {
        return null;
    }

    public function __debugInfo() {
        return $this->attributes;
    }
}
