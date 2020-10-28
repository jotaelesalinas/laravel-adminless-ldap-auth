<?php

namespace JotaEleSalinas\AdminlessLdap;

use PHPUnit\Framework\TestCase;
use Mockery;

use Adldap\Laravel\Facades\Adldap;

class LdapHelperTest extends TestCase
{
    protected $err_handler = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->err_handler = function ($errno, $errstr, $errfile, $errline) {
            // We are only interested in one kind of error
            if (preg_match('/^Undefined index: \w+\b/', $errstr)) {
                throw new CustomTestException($errstr);
            }
            return false;
        };
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected static function config()
    {
        return [
            'connection' => 'default',
            'identifiers' => [
                'ldap' => [
                    'locate_users_by' => 'sAMAccountName',
                    'bind_users_by' => 'cn',
                    'user_format' => 'cn=%s,ou=users,dc=Acme,dc=corp',
                ],
            ],
            'sync_attributes' => [
                'local1' => 'ldap1',
                'local2' => 'ldap2',
                'local3' => 'ldap3',
            ]
        ];
    }

    public function testConstructWrongParams()
    {
        set_error_handler($this->err_handler);
        $this->expectException(CustomTestException::class);
        $lh = new LdapHelper([]);
        restore_error_handler();
    }

    public function testConstructMissingConfigLocate()
    {
        $config = self::config();
        set_error_handler($this->err_handler);
        unset($config['identifiers']['ldap']['locate_users_by']);
        $this->expectException(CustomTestException::class);
        $lh = new LdapHelper($config);
        restore_error_handler();
    }

    public function testConstructMissingConfigBind()
    {
        $config = self::config();
        set_error_handler($this->err_handler);
        unset($config['identifiers']['ldap']['bind_users_by']);
        $this->expectException(CustomTestException::class);
        $lh = new LdapHelper($config);
        restore_error_handler();
    }

    public function testConstructMissingConfigFormat()
    {
        $config = self::config();
        set_error_handler($this->err_handler);
        unset($config['identifiers']['ldap']['user_format']);
        $this->expectException(CustomTestException::class);
        $lh = new LdapHelper($config);
        restore_error_handler();
    }

    public function testSearchWrongUserReturnsNull()
    {
        $config = self::config();
        $lh = new LdapHelper($config);

        $userdata = $lh->retrieveUser('', '');
        $this->assertNull($userdata);

        $mock_first = Mockery::mock();
        $mock_first->shouldReceive('first')
                    ->andReturn(null);

        $mock_search = Mockery::mock();
        $mock_search->shouldReceive('where')
                    ->with($config['identifiers']['ldap']['locate_users_by'], '=', 'asdf')
                    ->andReturn($mock_first);

        $mock_provider = Mockery::mock(); // Adldap\Connections\Provider
        $mock_provider->shouldReceive('connect')
                      ->andReturn(true);

        Adldap::shouldReceive('getProvider')
              ->once()
              ->andReturn($mock_provider);
        
        Adldap::shouldReceive('search')
              ->once()
              ->andReturn($mock_search);
        
        $userdata = $lh->retrieveUser('asdf', 'qwertz');
        $this->assertNull($userdata);
    }

    public function testBindWrongUserReturnsFalse()
    {
        $config = self::config();
        $lh = new LdapHelper($config);

        $userdata = $lh->checkCredentials('', '');
        $this->assertFalse($userdata);

        //$mock_attempt = Mockery::mock();
        //$mock_attempt->shouldReceive('attempt')
        //             ->with($config['identifiers']['ldap']['user_format'], '', true)
        //             ->andReturn(false);

        //Adldap::shouldReceive('auth')
        //      ->once()
        //      ->andReturn($mock_attempt);
        
        // ['sAMAccountName' => 'jdoe', 'cn' => 'John Doe', 'email' => 'jdoe@example.com']
        //$userdata = $lh->checkCredentials('asdf', '');
        //$this->assertFalse($userdata);
    }
}
