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

**Important**: The use case for this authentication package is very specific:

- This package does only one thing: validate users' credentials against an LDAP server.
- It is not possible to create/modify/delete users in the Laravel application.
- User management is done in the LDAP server.

## Installation

```bash
composer require jotaelesalinas/laravel-adminless-ldap-auth
```

Go on with the configuration. The package will not work if it is not properly configured.

## Configuration -- Mandatory!

### Add variables to `.env`

---

**A note on the most important .env variables**

- `LDAP_USER_SEARCH_ATTRIBUTE`: the name of the attribute in the LDAP server that uniquely identifies a user, e.g. `uid`, `mail` or `sAMAccountName`. The value of this attribute is what the user will have to type as identifier in the login form (+ the password, of course).

- `LDAP_USER_BIND_ATTRIBUTE`: the name of the attribute in the LDAP server that is used inside the distinguished name, e.g. `uid` or `cn`. The value will be read from the user attributes returned by the LDAP server.

- `AUTH_USER_KEY_FIELD`: the name of the property that will uniquely identify the Auth user. By default, the name is `username` and the value is read from the LDAP user attribute `LDAP_USER_SEARCH_ATTRIBUTE`.

See an [explanation of how the library works](docs/explanation.md) for a better understanding of the rationale behind the different variables.

---

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

These are just a few options, the ones needed to make this example work. There are many more in `config/ldap.php`.

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

That's it! Now you should be able to use [Laravel's built-in authentication](https://laravel.com/docs/7.x/authentication#authenticating-users) to perform all auth-related tasks, e.g. `Auth::check()`, `Auth::attempt()`, `Auth::user()`, etc.

You can try with tinker:

```bash
php artisan optimize:clear
php artisan tinker
```

```php
Auth::guest()
=> true
Auth::check()
=> false
Auth::user()
=> null
Auth::id()
=> null

Auth::attempt(['id' => 'einstein', 'password' => ''])
// Throws Adldap/Auth/PasswordRequiredException.

Auth::attempt(['id' => 'einstein', 'password' => 'qwerty'])
// Issues a warning about ldap_bind() unable to bind to server and invalid credentials.
=> false

Auth::attempt(['id' => 'einstein', 'password' => 'password'])
// In tinker it will issue a warning about the session storage. Just ignore it.
=> true

Auth::guest()
=> false
Auth::check()
=> true
Auth::user()
=> JotaEleSalinas\AdminlessLdap\LdapUser {
     +"id": "einstein",
     +"name": "Albert Einstein",
     +"email": "einstein@ldap.forumsys.com",
     +"phone": "314-159-2653",
   }
Auth::id()
=> "einstein"

Auth::logout()
=> null
Auth::check()
=> false
Auth::user()
=> null
```

Remember that you have these users available in the public testing LDAP server:
`einstein`, `newton` and `tesla`. The password is `password` for all of them.

Was this package useful? Give it a star!

## Login UI (routes, controllers, views)

If you want to see how to build a login UI adapted to this specific adminless LDAP system, you can read the [Login UI guide](docs/login-ui.md).

## To do

- [ ] Tests -- WIP
- [ ] Instructions for ActiveDirectory -- help needed, I don't have access to any AD server
- [ ] Do we have to trigger events for login attempts, success, failure, logout, etc? Or are they triggered somewhere else?
- [x] Add instructions to build the login UI
- [x] Extend `LdapUser` on `Illuminate\Auth\GenericUser`
- [x] Upload to packagist
- [x] Set up the GitHub Hook for Packagist to automate new versions

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email jotaelesalinas@example.com instead of using the issue tracker.

## Credits

- [José Luis Salinas][link-author]
- [All Contributors][link-contributors]

## License and disclaimer

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

The configuration showed in this document makes use of a [publicly available testing LDAP server](http://www.forumsys.com/tutorials/integration-how-to/ldap/online-ldap-test-server/). The authors of this package are not linked in any way with it and are not responsible nor liable in any way for anything related to it.

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
