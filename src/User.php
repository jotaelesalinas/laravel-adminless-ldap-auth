<?php

namespace JotaEleSalinas\AdminlessLdap;

use Illuminate\Contracts\Auth\Authenticatable;
use \Illuminate\Auth\Authenticatable as AuthenticatableTrait;

// very ugly hack to be able to override $rememberTokenName
abstract class IntermediateUser {
    use AuthenticatableTrait;
}

class User extends IntermediateUser implements Authenticatable
{
    protected $rememberTokenName = '';
    
    public function getKeyName () {
        $column_name = config('ldap_auth.identifiers.database.username_column', null);
        if ( !$column_name ) {
            throw new \Exception('AdminlessLdapUser: Could not find keyName.');
        }
        return $column_name;
    }
}
