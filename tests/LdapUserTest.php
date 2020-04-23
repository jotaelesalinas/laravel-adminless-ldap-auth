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

        $attrs = $user->__debugInfo();
        $this->assertArrayHasKey('id', $attrs);
        $this->assertArrayHasKey('name', $attrs);

        $user = new LdapUser([]);
        $this->assertObjectNotHasAttribute('id', $user);
        $this->assertObjectNotHasAttribute('name', $user);
        $attrs = $user->__debugInfo();
        $this->assertArrayNotHasKey('id', $attrs);
        $this->assertArrayNotHasKey('name', $attrs);
        $this->expectException(\Exception::class);
        $user->id;
    }

    public function testKeyNameComesFromConfig()
    {
        Config::shouldReceive('get')
              ->with('auth.auth_user_key', null)
              ->once()
              ->andReturn('asdf');
        $key = LdapUser::keyName();
        $this->assertEquals($key, 'asdf');
    }

    public function testThrowsWhenConfigKeyMissingStatic()
    {
        Config::shouldReceive('get')
              ->with('auth.auth_user_key', null)
              ->once()
              ->andReturn(null);
        $this->expectException(\Exception::class);
        $key = LdapUser::keyName();
    }

    public function testThrowsWhenConfigKeyMissing()
    {
        Config::shouldReceive('get')
              ->with('auth.auth_user_key', null)
              ->once()
              ->andReturn(null);
        $this->expectException(\Exception::class);
        $lu = new LdapUser([]);
        $key = $lu->getAuthIdentifierName();
    }

    public function testRememberTokenNameIsNull()
    {
        $user = new LdapUser(['id' => 123, 'name' => 'John Doe']);
        $this->assertNull($user->getRememberTokenName());
    }
}
