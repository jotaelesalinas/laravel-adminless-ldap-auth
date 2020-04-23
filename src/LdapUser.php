<?php
declare(strict_types=1);

namespace JotaEleSalinas\AdminlessLdap;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

// very ugly hack to be able to override $rememberTokenName
// (it is inside a trait, that is why we cannot override it directly)
abstract class IntermediateLdapUser
{
    use AuthenticatableTrait;
}

class LdapUser extends IntermediateLdapUser implements Authenticatable
{
    // Tells Laravel Auth that "remember me" is not available
    protected $rememberTokenName = '';

    // Allows mass-assigning properties on construction
    public function __construct(array $data = [])
    {
        foreach ($data as $field => $value) {
            $this->$field = $value !== null ? $value : '';
        }
    }

    // The name of the property that uniquely identifies the users
    public static function keyName() : string
    {
        $column_name = config('auth.key_user_field', null);
        if (!$column_name) {
            throw new \Exception('LdapUser: Could not find keyName.');
        }
        return $column_name;
    }

    // Laravel returns 'email' by default
    public function getKeyName() : string
    {
        return self::keyName();
    }
}