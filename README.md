# Howto: _adminless_ LDAP authentification in Laravel

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

Tested on 2019-10-25 with Laravel v6.2 and Adldap2-Laravel v6.0.

If you cannot upgrade to the latest versions, you can have a look at the old tutorials for [Laravel 5.5 and Adldap-Laravel 3.0](https://github.com/jotaelesalinas/laravel-simple-ldap-auth/blob/4fecf4c94317e27315eb47cf27dfb18567dc13db/README.md) or [Laravel 5.7 and Adldap-Laravel 6.0](https://github.com/jotaelesalinas/laravel-simple-ldap-auth/blob/56fa7c0f46c16cd4a97a11fbf75781f1beedf213/README.md).

**Disclaimer**: I created this GitHub repo because I faced a very specific problem some time ago and I could not find a solution on the internet. I decided to share the solution I came up with, just in case anyone else stumbled upon the same problem. You can consider this a proof-of-concept. I am really sorry but I can't look into your code or provide solutions to other use cases like Active Directory. That said, if you find a problem with this repo, you are very welcome to open an issue, indicating where exactly the error is, or even better, fix it and send a pull request.

### 1. Create a new Laravel project and install Adldap2-Laravel

```bash
composer create-project laravel/laravel laravel-simple-ldap-auth
cd laravel-simple-ldap-auth
composer require adldap2/adldap2-laravel
```

### 2. Register Adldap's façade in `config/app.php`

```php
'aliases' => [
    // already existing façade aliases

    'Adldap' => Adldap\Laravel\Facades\Adldap::class,
],
```

Since Laravel 5.5 you don't need to add the service providers.

### 3. Publish Adldap service providers

```bash
php artisan vendor:publish --provider="Adldap\Laravel\AdldapServiceProvider"
php artisan vendor:publish --provider="Adldap\Laravel\AdldapAuthServiceProvider"
```

### 4. Change the driver of the user provider in `config/auth.php`

```php
'providers' => [
    'users' => [
        'driver' => 'ldap', // was 'eloquent'
        'model'  => App\User::class,
    ],
],
```

### 5. Configure your LDAP and database connections in `.env`

FYI, configuration that is secret, i.e. API tokens or database passwords,
should be stored in this file, which Laravel includes by default in `.gitignore`.

```
LDAP_SCHEMA=OpenLDAP
LDAP_HOSTS=ldap.forumsys.com
LDAP_BASE_DN=dc=example,dc=com
LDAP_USER_ATTRIBUTE=uid
LDAP_USER_FORMAT=uid=%s,dc=example,dc=com
LDAP_CONNECTION=default

# Change from mysql to sqlite:
DB_CONNECTION=sqlite

# Remove all this lines, only for this tutorial
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=homestead
# DB_USERNAME=homestead
# DB_PASSWORD=secret
```

### 6. Change `database/migrations/2014_10_12_000000_create_users_table.php`

```php
public function up()
{
    Schema::create('users', function (Blueprint $table) {
        $table->increments('id');
        $table->string('name');

        // remove this line:
        // $table->string('email')->unique();
        // and replace it with this one:
        $table->string('username')->unique();

        // remove this line as well:
        // $table->timestamp('email_verified_at')->nullable();

        // add this line:
        $table->string('phone');

        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });
}
```

### 7. Delete the password resets migration file

```bash
rm database/migrations/2014_10_12_100000_create_password_resets_table.php
```

### 8. Run the migration to create the users table

Before migrating, make sure that your database is configured and working properly.

```bash
touch database/database.sqlite
php artisan migrate
```

### 9. Configure the LDAP connection in `config/ldap.php`

```php
'connections' => [

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

            'account_prefix' => env('LDAP_ACCOUNT_PREFIX', ''),
            'account_suffix' => env('LDAP_ACCOUNT_SUFFIX', ''),
            'hosts' => explode(' ', env('LDAP_HOSTS', 'corp-dc1.corp.acme.org corp-dc2.corp.acme.org')),
            'port' => env('LDAP_PORT', 389),
            'timeout' => env('LDAP_TIMEOUT', 5),
            'base_dn' => env('LDAP_BASE_DN', 'dc=corp,dc=acme,dc=org'),
            'username' => env('LDAP_ADMIN_USERNAME', ''),
            'password' => env('LDAP_ADMIN_PASSWORD', ''),
            'follow_referrals' => env('LDAP_FOLLOW_REFERRALS', false),
            'use_ssl' => env('LDAP_USE_SSL', false),
            'use_tls' => env('LDAP_USE_TLS', false),
        ],
    ],

],
```

### 10. Configure the LDAP authentication in `config/ldap_auth.php`

```php
'identifiers' => [
    'ldap' => [
        'locate_users_by' => env('LDAP_USER_ATTRIBUTE', 'userprincipalname'),
        'bind_users_by' => env('LDAP_USER_ATTRIBUTE', 'distinguishedname'),
    ],

    'database' => [
        'guid_column' => 'objectguid',
        'username_column' => 'username',
    ],
],

'sync_attributes' => [
    // 'field_in_local_db' => 'attribute_in_ldap_server',
    'username' => 'uid',
    'name' => 'cn',
    'phone' => 'telephonenumber',
],
```

### 11. Scaffold login controllers and routes

```bash
composer require laravel/ui
php artisan ui vue --auth
```

### 12. Tell Laravel that users are identified by username instead of email address

Inside the file `app/Http/Controllers/Auth/LoginController.php`,
you'll need to add the public method `username()`:

```php
class LoginController extends Controller
{
    /* rest of the class */

    public function username()
    {
        // we could return config('ldap_auth...') directly
        // but it seems to change a lot and with this check we make sure
        // that it will fail in future versions, if changed.
        // you can return the config(...) directly if you want.
        $column_name = config('ldap_auth.identifiers.database.username_column', null);
        if ( !$column_name ) {
            die('Error in LoginController::username(): could not find username column.');
        }
        return $column_name;
    }
}
```

### 13. Remove unused auth routes in `routes/web.php`

[https://stackoverflow.com/questions/42695917/laravel-5-4-disable-register-route](https://stackoverflow.com/questions/42695917/laravel-5-4-disable-register-route)

```php
// replace this line:
// Auth::routes();
// with these ones:
Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('login', 'Auth\LoginController@login');
Route::post('logout', 'Auth\LoginController@logout')->name('logout');
```

### 14. Replace 'email' with 'username' in `resources/views/auth/login.blade.php`

```html
<div class="form-group row">
    <label for="username" class="col-md-4 col-form-label text-md-right">{{ __('Username') }}</label>

    <div class="col-md-6">
        <input id="username" type="text" class="form-control @error('username') is-invalid @enderror" name="username" value="{{ old('username') }}" required autocomplete="username" autofocus>

        @error('username')
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
        @enderror
    </div>
</div>
```

### 15. Delete the following files and folder

```bash
rm app/Http/Controllers/Auth/ConfirmPasswordController.php
rm app/Http/Controllers/Auth/ForgotPasswordController.php
rm app/Http/Controllers/Auth/RegisterController.php
rm app/Http/Controllers/Auth/ResetPasswordController.php
rm app/Http/Controllers/Auth/VerificationController.php
rm resources/views/auth/register.blade.php
rm resources/views/auth/verify.blade.php
rm -r resources/views/auth/passwords
```

### 16. Add these methods to LoginController in `app/Http/Controllers/Auth/LoginController.php`

Important note: the proper way to do this is by creating a custom user provider
([https://laravel.com/docs/5.7/authentication#adding-custom-user-providers](https://laravel.com/docs/5.7/authentication#adding-custom-user-providers)).

Don't forget the `use` statements.

```php
/* namespace and previous use statements */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Adldap\Laravel\Facades\Adldap;

class LoginController extends Controller
{
    /* rest of the class, including the previously added username() */

    protected function attemptLogin(Request $request)
    {
        $credentials = $request->only($this->username(), 'password');
        $username = $credentials[$this->username()];
        $password = $credentials['password'];

        $user_format = env('LDAP_USER_FORMAT', 'cn=%s,'.env('LDAP_BASE_DN', ''));
        $userdn = sprintf($user_format, $username);

        // you might need this, as reported in
        // [#14](https://github.com/jotaelesalinas/laravel-simple-ldap-auth/issues/14):
        // Adldap::auth()->bind($userdn, $password);

        if(Adldap::auth()->attempt($userdn, $password, $bindAsUser = true)) {
            // the user exists in the LDAP server, with the provided password

            $user = \App\User::where($this->username(), $username)->first();
            if (!$user) {
                // the user doesn't exist in the local database, so we have to create one

                $user = new \App\User();
                $user->username = $username;
                $user->password = '';

                // you can skip this if there are no extra attributes to read from the LDAP server
                // or you can move it below this if(!$user) block if you want to keep the user always
                // in sync with the LDAP server 
                $sync_attrs = $this->retrieveSyncAttributes($username);
                foreach ($sync_attrs as $field => $value) {
                    $user->$field = $value !== null ? $value : '';
                }
            }

            // by logging the user we create the session, so there is no need to login again (in the configured time).
            // pass false as second parameter if you want to force the session to expire when the user closes the browser.
            // have a look at the section 'session lifetime' in `config/session.php` for more options.
            $this->guard()->login($user, true);
            return true;
        }

        // the user doesn't exist in the LDAP server or the password is wrong
        // log error
        return false;
    }

    protected function retrieveSyncAttributes($username)
    {
        $ldapuser = Adldap::search()->where(env('LDAP_USER_ATTRIBUTE'), '=', $username)->first();
        if ( !$ldapuser ) {
            // log error
            return false;
        }
        // if you want to see the list of available attributes in your specific LDAP server:
        // var_dump($ldapuser->attributes); exit;

        // needed if any attribute is not directly accessible via a method call.
        // attributes in \Adldap\Models\User are protected, so we will need
        // to retrieve them using reflection.
        $ldapuser_attrs = null;

        $attrs = [];

        foreach (config('ldap_auth.sync_attributes') as $local_attr => $ldap_attr) {
            if ( $local_attr == 'username' ) {
                continue;
            }

            $method = 'get' . $ldap_attr;
            if (method_exists($ldapuser, $method)) {
                $attrs[$local_attr] = $ldapuser->$method();
                continue;
            }

            if ($ldapuser_attrs === null) {
                $ldapuser_attrs = self::accessProtected($ldapuser, 'attributes');
            }

            if (!isset($ldapuser_attrs[$ldap_attr])) {
                // an exception could be thrown
                $attrs[$local_attr] = null;
                continue;
            }

            if (!is_array($ldapuser_attrs[$ldap_attr])) {
                $attrs[$local_attr] = $ldapuser_attrs[$ldap_attr];
            }

            if (count($ldapuser_attrs[$ldap_attr]) == 0) {
                // an exception could be thrown
                $attrs[$local_attr] = null;
                continue;
            }

            // now it returns the first item, but it could return
            // a comma-separated string or any other thing that suits you better
            $attrs[$local_attr] = $ldapuser_attrs[$ldap_attr][0];
            //$attrs[$local_attr] = implode(',', $ldapuser_attrs[$ldap_attr]);
        }

        return $attrs;
    }

    protected static function accessProtected ($obj, $prop)
    {
        $reflection = new \ReflectionClass($obj);
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

}
```

# Run the website

We're done!

Don't forget to set the web server port to `8000` in your local testing `.env` file:

```
APP_URL=http://localhost:8000
```

Let's run the website and try to log in.

```bash
php artisan serve
```

Visit `http://localhost:8000` in your favourite browser.

Try to visit `http://localhost:8000/home` before logging in.

Remember that you have these users available in the testing LDAP server:
`riemann`, `gauss`, `euler`, `euclid`, `einstein`, `newton`, `galieleo` and `tesla`.
The password is `password` for all of them.

Log in and play around.

Was this article useful? Give it a star!
