<?php

namespace JotaEleSalinas\AdminlessLdap;

use Illuminate\Contracts\Auth\Authenticatable;
use \Illuminate\Auth\Authenticatable as AuthenticatableTrait;

// very ugly hack to be able to override $rememberTokenName
// (it is inside a trait, that is why we cannot override it dierectly)
abstract class IntermediateLdapUser {
    use AuthenticatableTrait;
}

class LdapUser extends IntermediateLdapUser implements Authenticatable
{
    protected $rememberTokenName = '';
    
    public function getKeyName () {
        $column_name = config('auth.key_user_field', null);
        if ( !$column_name ) {
            throw new \Exception('AdminlessLdapUser: Could not find keyName.');
        }
        return $column_name;
    }
}
