# jotaelesalinas/laravel-adminless-ldap-auth

Authenticate users in Laravel against an _adminless_ LDAP server

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]](LICENSE.md)
<!--
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
-->

**Important**: The use case of this authentication package is very specific: in the Laravel application there is no user management at all. Users are either allowed to use the website or rejected, depending on the credentials that they provide. That's it. User management is done in the LDAP server.

**Disclaimer**: This software is offered as-is. Use it at your own risk. Read the license.

This package uses this publicly available testing LDAP server as default configuration:

[http://www.forumsys.com/tutorials/integration-how-to/ldap/online-ldap-test-server/](http://www.forumsys.com/tutorials/integration-how-to/ldap/online-ldap-test-server/)

It is recommended that you start by testing that it works against this server -if possible- and then changing the configuration to your specific setup.

## Installation

Require this package in your Laravel application:

```bash
composer require jotaelesalinas/laravel-adminless-ldap-auth
```

## Explanation of the _basic_ .env variables

TL;DR:

- `LDAP_USER_SEARCH_ATTRIBUTE`: the name of the attribute in the LDAP server that uniquely identifies a user, e.g. `uid`, `mail` or `sAMAccountName`. The value of this attribute is what the user will have to type as identifier in the login form (+ the password, of course).

- `LDAP_USER_BIND_ATTRIBUTE`: the name of the attribute in the LDAP server that is used inside the distinguished name, e.g. `uid` or `cn`. The value will be read from the user attributes returned by the LDAP server.

- `AUTH_USER_KEY_FIELD`: the name of the property that will uniquely identify the Auth user. By default, the name is `username` and the value is read from the LDAP user attribute `LDAP_USER_SEARCH_ATTRIBUTE`.

The long version:

To understand how configuration works, you have to know first the different steps of the authentication process and the objects and fields involved:

1. The user provides their credentials with two fields: identifier and password. We use the generic word "identifier" because it can be a username, an email, a staff number, a phone number... you name it.

    For now, let's say that the identifier is `jdoe`.

2. We check in the LDAP server if there is any user with the given identifier. No password checks yet (this is how Laravel Auth works).

    This check is done using the attribute `LDAP_USER_SEARCH_ATTRIBUTE` in the LDAP server. It can be `uid`, `sAMAccountName`... Depends on the LDAP server. In this example, it will be `uid`.

    If there is a matching user, we will have a list of attributes from the LDAP user like this:

```php
[
    'uid' => 'jdoe',
    'cn' => 'John Doe',
    'mail' => 'jdoe@example.com',
    'telephonenumber' => '555-12-23-34',
    'department' => 'Sales',
    'location' => 'HQ',
    'distinguishedName' => 'cn=John Doe,dc=example,dc=com',
    ...
]
```

3. Then, we check that the credentials are correct. But! For authentication, LDAP does not use identifier + password. It uses "distinguished name" + password.

    The distinguished name is a comma-separated list of fields that uniquely identifies an item inside the LDAP registry. In this example: `cn=John Doe,dc=example,dc=com`.

    Gasp! For the password validation we might have to use a different field than the one used to search the user.

    To avoid asking the users for their `uid` _and_ their `cn` in the login form, we need another .env variable, `LDAP_USER_BIND_ATTRIBUTE`, in this case `cn`.

    The rest of the fields of the distinguished name, `dc=example,dc=com`, go into the .env variable `LDAP_BASE_DN`.

    It could happen that both are the same, e.g. `LDAP_USER_SEARCH_ATTRIBUTE=uid` and `LDAP_USER_BIND_ATTRIBUTE=uid`.

4. Finally, when the user is retrieved and the password validated, the data from the LDAP server is converted into an object of class `LdapUser`. This is the object returned by `Auth::user()`.

    This object will have the identifier stored in the property that you specify in the .env variable `AUTH_USER_KEY_FIELD`. If it is `id`, the user will have a property `id` equal to `jdoe`. If you choose `username`, the user will have a property `username` equal to `jdoe`.

    Also, using the config variable `ldap_auth.sync_attributes` you will be able to tell which fields from the LDAP server you want "imported" into the Auth user, and under which names. For security reasons, you have to whitelist the attributes to be imported.

    As an example, if you have this entry in `config/ldap_auth.php`:

    ```php
    'sync_attributes' => [
        // 'field_in_local_user_model' => 'attribute_in_ldap_server',
        env('AUTH_USER_KEY_FIELD', null) => env('LDAP_USER_SEARCH_ATTRIBUTE', null),
        'name' => 'cn',
        'email' => 'mail',
        'phone' => 'telephonenumber',
    ],
    ```

    Given the sample LDAP user from the step 2, you will have the following Auth user:

    ```php
    JotaEleSalinas\AdminlessLdap\LdapUser {
        "username": "jdoe",
        "name": "John Doe",
        "email": "jdoe@example.com",
        "phone": "555-12-23-34",
    }
    ```

    And this is the user object that you will use thoughout your Laravel app.

    `LdapUser` is not an Eloquent model! You cannot do `LdapUser::where('uid', '=', 'jdoe')` and all the nice things that you might be used to do with Eloquent.

## Configuration

### Add variables to `.env`

You will need the assistance of your LDAP administrator to get these options right.

```bash
LDAP_SCHEMA=OpenLDAP                # Has to be one of these:
                                    #  - OpenLDAP
                                    #  - FreeIPA
                                    #  - ActiveDirectory
LDAP_HOSTS=ldap.forumsys.com        # Your LDAP server
LDAP_BASE_DN=dc=example,dc=com      # base distinguished name
LDAP_USER_SEARCH_ATTRIBUTE=uid      # field by which your users are identified in the LDAP server
LDAP_USER_BIND_ATTRIBUTE=uid        # field by which your users are binded to the LDAP server
LDAP_USER_FULL_DN_FMT=${LDAP_USER_BIND_ATTRIBUTE}=%s,${LDAP_BASE_DN}
                                    # full user distinguished name to be used with sprintf:
                                    # %s will be replaced by $user->${LDAP_USER_BIND_ATTRIBUTE}
LDAP_CONNECTION=default             # which configuration to use from config/ldap.php
```

These are just a few options, the most common ones and the ones needed to make this example work. There are many more in `config/ldap.php`.

**For ActiveDirectory users**

This configuration might work for you (I can't promise it will):

```bash
LDAP_SCHEMA=ActiveDirectory
LDAP_USER_SEARCH_ATTRIBUTE=sAMAccountName
LDAP_USER_BIND_ATTRIBUTE=cn
```

Also, add the name of the property that will uniquely identify your Auth user:

```bash
AUTH_USER_KEY_FIELD=username
```

You can change the value of `AUTH_USER_KEY_FIELD` to whatever you want, e.g. `id`, `email` or `phonenumber`, but you don't really have to.

### Modify `config/auth.php`

Add a new LDAP provider using the newly installed `adminless_ldap` driver:

```php
'providers' => [
    'ldap' => [
        'driver' => 'adminless_ldap',
    ],
],
```

You can delete the `users` provider if you want. Or just comment it out.
Do not leave unused code hanging around.

Modify the web guard to use the new `ldap` provider:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'ldap',
    ],
],
```

Delete the `api` guard if you dont need it. Or at least comment it out.

Create this new entry:

```php
'auth_user_key' => env('AUTH_USER_KEY_FIELD', null),
```

### Publish the config files of Adldap and AdldapAuth

```bash
php artisan vendor:publish --provider="Adldap\Laravel\AdldapServiceProvider"
php artisan vendor:publish --provider="Adldap\Laravel\AdldapAuthServiceProvider"
```

### Configure the LDAP connection in `config/ldap.php`

Again, you will need the assistance of your LDAP administrator. See comments below.

```php
'connections' => [

    // here, in theory, we should leave `default` untouched and create a new connection
    // (and change `LDAP_CONNECTION` in `.env` accordingly)
    // but I wasn't able to make the underlying Adldap package work with any connection
    // other than `default`, so we will modify the default connection directly

    'default' => [
        'auto_connect' => env('LDAP_AUTO_CONNECT', false),

        'connection' => Adldap\Connections\Ldap::class,

        'settings' => [

            // replace this line:
            // 'schema' => Adldap\Schemas\ActiveDirectory::class,
            // with this:
            'schema' => env('LDAP_SCHEMA', '') == 'OpenLDAP' ?
                            Adldap\Schemas\OpenLDAP::class :
                            ( env('LDAP_SCHEMA', '') == 'FreeIPA' ?
                                Adldap\Schemas\FreeIPA::class :
                                Adldap\Schemas\ActiveDirectory::class ),

            // remove the default values of these options:
            'hosts' => explode(' ', env('LDAP_HOSTS', '')),
            'base_dn' => env('LDAP_BASE_DN', ''),
            'username' => env('LDAP_ADMIN_USERNAME', ''),
            'password' => env('LDAP_ADMIN_PASSWORD', ''),

            // and talk to your LDAP administrator about these other options.
            // do not modify them here, use .env!
            'account_prefix' => env('LDAP_ACCOUNT_PREFIX', ''),
            'account_suffix' => env('LDAP_ACCOUNT_SUFFIX', ''),
            'port' => env('LDAP_PORT', 389),
            'timeout' => env('LDAP_TIMEOUT', 5),
            'follow_referrals' => env('LDAP_FOLLOW_REFERRALS', false),
            'use_ssl' => env('LDAP_USE_SSL', false),
            'use_tls' => env('LDAP_USE_TLS', false),

        ],
    ],
],
```

### Configure the LDAP authentication in `config/ldap_auth.php`

Tell the Adldap library how to search and bind users in your LDAP server:

```php
'identifiers' => [
    // ... other code ...

    'ldap' => [
        'locate_users_by' => env('LDAP_USER_SEARCH_ATTRIBUTE', ''),
        'bind_users_by' => env('LDAP_USER_BIND_ATTRIBUTE', ''),
        'user_format' => env('LDAP_USER_FULL_DN_FMT', ''),
    ],

    // ... other code ...
],
```

And tell the new auth provider which fields from the LDAP user entry you will
want "imported" into your Auth user _on every sucessful login_.

```php
'sync_attributes' => [
    // 'field_in_local_user_model' => 'attribute_in_ldap_server',
    env('AUTH_USER_KEY_FIELD', null) => env('LDAP_USER_SEARCH_ATTRIBUTE', null),
    'name' => 'cn',
    'email' => 'mail',
    'phone' => 'telephonenumber',
],
```

## Usage

That's it! Now you should be able to use [Laravel's built-in authentication](https://laravel.com/docs/7.x/authentication#included-authenticating) to perform all auth-related tasks, e.g. `Auth::check()`, `Auth::attempt()`, `Auth::user()`, etc.

You can try with tinker:

```bash
php artisan optimize:clear
php artisan tinker
```

```php
Auth::check()   // false
Auth::guest()   // true
Auth::user()    // null

Auth::attempt(['id' => 'einstein', 'password' => 'qwerty'])
                // false
                // will show a warning with the failed LDAP binding
Auth::attempt(['id' => 'einstein', 'password' => 'password'])
                // true
                // might issue a warning about the session storage. just ignore it.

Auth::check()   // true
Auth::guest()   // false
Auth::user()    // dump of your User model
Auth::id()      // "einstein"
```

Remember that you have these users available in the testing LDAP server:
`riemann`, `gauss`, `euler`, `euclid`, `einstein`, `newton` and `tesla`.
The password is `password` for all of them.


Was this package useful? Give it a star!



## To do

- [ ] Upload to packagist
- [ ] Set up the GitHub Hook for Packagist <https://packagist.org/about#how-to-update-packages>
- [ ] Do we have to trigger events for login attempts, success, failure, logout, etcc? Or are they triggered somewhere else?
- [ ] Instructions for ActiveDirectory -- help needed, I don't have access to any AD server
- [ ] Tests -- ongoing
- [ ] Add instructions to build the login UI
- [ ] Remove Adldap2 dependency and use [PHP's LDAP module](https://www.php.net/manual/en/book.ldap.php) directly
- [x] Extend `LdapUser` on `Illuminate\Auth\GenericUser`

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email jotaelesalinas@example.com instead of using the issue tracker.

## Credits

- [José Luis Salinas][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.


[ico-version]: https://img.shields.io/packagist/v/jotaelesalinas/laravel-adminless-ldap-auth.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/jotaelesalinas/laravel-adminless-ldap-auth/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/jotaelesalinas/laravel-adminless-ldap-auth.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/jotaelesalinas/laravel-adminless-ldap-auth.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/jotaelesalinas/laravel-adminless-ldap-auth.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/jotaelesalinas/laravel-adminless-ldap-auth
[link-travis]: https://travis-ci.org/jotaelesalinas/laravel-adminless-ldap-auth
[link-scrutinizer]: https://scrutinizer-ci.com/g/jotaelesalinas/laravel-adminless-ldap-auth/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/jotaelesalinas/laravel-adminless-ldap-auth
[link-downloads]: https://packagist.org/packages/jotaelesalinas/laravel-adminless-ldap-auth
[link-author]: https://github.com/jotaelesalinas
[link-contributors]: ../../contributors
