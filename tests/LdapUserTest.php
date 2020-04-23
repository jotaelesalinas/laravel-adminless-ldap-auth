<?php

namespace JotaEleSalinas\AdminlessLdap;

use PHPUnit\Framework\TestCase;
use Mockery;

use Illuminate\Support\Facades\Config;

class LdapUserTest extends TestCase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testConstructorLoadsAttributes()
    {
        $user = new LdapUser(['id' => 123, 'name' => 'John Doe']);
        $this->assertObjectNotHasAttribute('id', $user);
        $this->assertObjectNotHasAttribute('name', $user);
        $this->assertEquals($user->id, 123);
        $this->assertEquals($user->name, 'John Doe');

        $user = new LdapUser([]);
        $this->assertObjectNotHasAttribute('id', $user);
        $this->assertObjectNotHasAttribute('name', $user);
        $this->expectException(\Exception::class);
        $user->id;
    }

    public function testKeyNameComesFromConfig()
    {
        Config::shouldReceive('get')
              ->with('auth.key_user_field', null)
              ->once()
              ->andReturn('asdf');
        $key = LdapUser::keyName();
        $this->assertEquals($key, 'asdf');
    }

    public function testThrowsWhenConfigKeyMissing()
    {
        Config::shouldReceive('get')
              ->with('auth.key_user_field', null)
              ->once()
              ->andReturn(null);
        $this->expectException(\Exception::class);
        $key = LdapUser::keyName();
    }

    public function testRememberTokenNameIsNull()
    {
        $user = new LdapUser(['id' => 123, 'name' => 'John Doe']);
        $this->assertNull($user->getRememberTokenName());
    }
}
