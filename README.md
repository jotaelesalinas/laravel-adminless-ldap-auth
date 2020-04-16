# jotaelesalinas/laravel-simple-ldap-auth

Authenticate users in Laravel against an _adminless_ LDAP server

This is a detailed step-by-step [Laravel](https://laravel.com/) installation manual adapted
for _adminless_ LDAP authentication.

There is no user management at all. Users are either allowed to use the website or rejected.
That's it.

Of course, you can add a "role" attribute to your LDAP directory and use that to control access
to different pages or resources. But you won't be able to modify the role from this website, or
add/search/modify/delete users. User management is done via the LDAP server.

If you need user management, use [Adldap2/Adldap2-Laravel](https://github.com/Adldap2/Adldap2-Laravel)
instead. It's a great library but it requires an administrator user in the LDAP server -the same way
that you need a database user in MySQL- in order to perform all user-related operations, including
checking if a user exists and the password is correct.
In my case I didn't have any available admin user in the LDAP server, so I had to adapt the library
default behaviour to this specific use case.

As testing environment, we will be using a local Sqlite database and this publicly available testing LDAP server:

[http://www.forumsys.com/tutorials/integration-how-to/ldap/online-ldap-test-server/](http://www.forumsys.com/tutorials/integration-how-to/ldap/online-ldap-test-server/)

Tested on 2020-04-16 with Laravel v7.0 and Adldap2-Laravel v6.0.

If you cannot upgrade to the latest versions, you can have a look at the old tutorials for:
- [Laravel 6.2 and Adldap2-Laravel 6.0](https://github.com/jotaelesalinas/laravel-simple-ldap-auth/blob/d26ea52ddcc9a1336eb49a9c01469f51f8c83165/README.md)
- [Laravel 5.7 and Adldap2-Laravel 5.0](https://github.com/jotaelesalinas/laravel-simple-ldap-auth/blob/56fa7c0f46c16cd4a97a11fbf75781f1beedf213/README.md)
- [Laravel 5.5 and Adldap2-Laravel 3.0](https://github.com/jotaelesalinas/laravel-simple-ldap-auth/blob/4fecf4c94317e27315eb47cf27dfb18567dc13db/README.md)

**Disclaimer**: I created this GitHub repo because I faced a very specific problem some time ago and I could not find a solution on the internet. I decided to share the solution I came up with, just in case anyone else stumbled upon the same problem. You can consider this a proof-of-concept. I am really sorry but I can't look into your code or provide solutions to other use cases like Active Directory. That said, if you find a problem with this repo, you are very welcome to open an issue, indicating where exactly the error is, or even better, fix it and send a pull request.

**Important**: I do not provide support. This software is offered as-is. Use it at your own risk.

### Create a new Laravel project

```bash
composer create-project laravel/laravel adminless && \
cd adminless
```

### Install jotaelesalinas/laravel-simple-ldap-auth

```bash
composer require jotaelesalinas/laravel-simple-ldap-auth
```

### Add users' login field to `.env`

```bash
LOGIN_FIELD=username
```

This has nothing to do with your "key username field" in the LDAP server.
You should use either `username` or `email` for the sake of clarity,
but it doesn't really make a difference. E.g. your key field in the LDAP
server could be `phonenumber`, and it would still work with `username` here.
But it just doesn't feel right, I know.

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
'login_field' => env('LOGIN_FIELD', null),
```

### Publish the config files of Adldap and AdldapAuth

```bash
php artisan vendor:publish --provider="Adldap\Laravel\AdldapServiceProvider" && \
php artisan vendor:publish --provider="Adldap\Laravel\AdldapAuthServiceProvider"
```

### Add LDAP configuration options to `.env`

You will need the assistance of your LDAP administrator to get these options right.

```bash
LDAP_SCHEMA=OpenLDAP                         # Has to be one of these:
                                             #  - OpenLDAP
                                             #  - FreeIPA
                                             #  - ActiveDirectory
LDAP_HOSTS=ldap.forumsys.com                 # Your LDAP server
LDAP_BASE_DN=dc=example,dc=com               # base distinguished name
LDAP_USER_ATTRIBUTE=uid                      # field by which your users are
                                             # identified in the LDAP server
LDAP_USER_FORMAT=${LDAP_USER_ATTRIBUTE}=%s,${LDAP_BASE_DN}
                                             # full user distinguished name
                                             # to be used with sprintf,
LDAP_CONNECTION=default                      # which configuration to use
                                             # from config/ldap.php
```

This is where you have to enter the proper "key username field" by which users are identified
in you LDAP server. In this case, it is `uid`, and you have to enter it in `LDAP_USER_ATTRIBUTE`
and maybe `LDAP_USER_FORMAT`.

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

            // and talk to your LDAP administrator about these other options:
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

Tell the Adldap library by which field users are to be located:

```php
'identifiers' => [
    // ... other code ...

    'ldap' => [
        'locate_users_by' => env('LDAP_USER_ATTRIBUTE', 'userprincipalname'),
        'bind_users_by' => env('LDAP_USER_ATTRIBUTE', 'distinguishedname'),
    ],

    'database' => [
            'guid_column' => 'objectguid',
            'username_column' => env('LOGIN_FIELD', null),
    ],

    // ... other code ...
],
```

And tell the new auth provider which fields from the LDAP user entry you will
want "imported" into your User model _on every sucessful login_.

```php
'sync_attributes' => [
    // 'field_in_user_model' => 'attribute_in_ldap_server',
    env('LOGIN_FIELD', null) => 'uid',
    'name' => 'cn',
    'phone' => 'telephonenumber',
],
```

### Create the auth routes, controllers and views

```bash
composer require laravel/ui && \
php artisan ui vue --auth && \
npm install && npm run dev
```

### Delete unneeded files and folders

```bash
# user migration
rm database/migrations/2014_10_12_000000_create_users_table.php && \
# password reset migration
rm database/migrations/2014_10_12_100000_create_password_resets_table.php && \
# unnecessary auth controllers and views
rm app/Http/Controllers/Auth/ConfirmPasswordController.php && \
rm app/Http/Controllers/Auth/ForgotPasswordController.php && \
rm app/Http/Controllers/Auth/RegisterController.php && \
rm app/Http/Controllers/Auth/ResetPasswordController.php && \
rm app/Http/Controllers/Auth/VerificationController.php && \
rm resources/views/auth/register.blade.php && \
rm resources/views/auth/verify.blade.php && \
rm -r resources/views/auth/passwords
```

### Remove unused auth routes in `routes/web.php`

```php
// replace this line:
// Auth::routes();
// with these ones:
Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('login', 'Auth\LoginController@login');
Route::post('logout', 'Auth\LoginController@logout')->name('logout');
// If you want to be able to log out from the /logout URL, e.g. during development:
Route::get('logout', 'Auth\LoginController@logout');
```

### Tell Laravel that users are identified by your field of choice in `app/Http/Controllers/Auth/LoginController.php`

```php
class LoginController extends Controller
{
    // ... existing code ...

    public function username()
    {
        return config('auth.login_field');
    }
}
```

### Adapt login form in `resources/views/auth/login.blade.php`

Change `email` to `username` if you need it (specific HTML code might change in the future):

```html
<div class="form-group row">
    <label for="username"
           class="col-md-4 col-form-label text-md-right"
        >{{ __('Username') }}</label>

    <div class="col-md-6">
        <input id="username"
               type="text"
               class="form-control @error('username') is-invalid @enderror"
               name="username"
               value="{{ old('username') }}"
               required
               autocomplete="username"
               autofocus>

        @error('username')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
        @enderror
    </div>
</div>
```

We hardcoded `username` instead of using `{{ config('auth.login_field')}}` because
it is something that will (or should) not change in the future.

Now, delete the "Remember me" checkbox, because there is no "Remember me" with this system.
Remove this whole form group (specific HTML markup could change in the future):

```html
<div class="form-group row">
    <div class="col-md-6 offset-md-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label" for="remember">
                {{ __('Remember Me') }}
            </label>
        </div>
    </div>
</div>
```

### Show user data on login

Modify `resources/views/home.blade.php`:

Change the line:

```html
    You are logged in!
```

With:

```html
    <p>You are logged in!</p>

    <p>Your user data:</p>

    <pre>
{{ json_encode(Auth::user(), JSON_PRETTY_PRINT) }}
    </pre>
```

(Don't leave blank spaces before the JSON to get nicer results.)

Remember that, as usual with Laravel, you can always access you current user's data via `Auth::user()`.

### Set your URL in `.env`

```bash
APP_URL=http://localhost:8000
```

Clear your caches:

```bash
php artisan optimize:clear
```

### Good to go!

Let's run the website and try to log in.

```bash
php artisan serve
```

Visit <http://localhost:8000> in your favourite browser.

Try to visit <http://localhost:8000/home> before logging in. You should be redirected to the login page.

Remember that you have these users available in the testing LDAP server:
`riemann`, `gauss`, `euler`, `euclid`, `einstein`, `newton` and `tesla`.
The password is `password` for all of them.

Log in and play around.

Was this article useful? Give it a star!

#### To do

- [x] Upload to packagist
- [x] Set up the GitHub Hook for Packagist <https://packagist.org/about#how-to-update-packages>
- [ ] Do we have to trigger events for login attempts, success, failure, logout, etcc? Or are they triggered somewhere else?
- [ ] Instructions for ActiveDirectory -- help needed, I don't have access to any AD server
- [ ] Tests
- [ ] Check code style
