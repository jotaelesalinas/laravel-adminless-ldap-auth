# Howto: _adminless_ LDAP authentification in Laravel

This is a detailed step-by-step [Laravel](https://laravel.com/) installation manual adapted for _adminless_ LDAP authentication.

There is no user management at all. Users are either allowed to use the website or rejected. That's it.

Of course, you can add a "role" attribute to your LDAP directory and use that to control access to different pages or resources.
But you won't be able to modify the role from this website, or add/search/modify/delete users.
User management is done via the LDAP server.

If you need user management, use [Adldap2/Adldap2-Laravel](https://github.com/Adldap2/Adldap2-Laravel) instead.
It's a great library but it requires an administrator user in the LDAP server -the same way that you need a database user
in MySQL- in order to perform all user-related operations, including checking if a user exists and the password is correct.
In my case I didn't have any available admin user in the LDAP server, so I had to adapt the library default behaviour
to this specific use case.

As testing environment, we will be using a local Sqlite database and this publicly available testing LDAP server:

[http://www.forumsys.com/tutorials/integration-how-to/ldap/online-ldap-test-server/](http://www.forumsys.com/tutorials/integration-how-to/ldap/online-ldap-test-server/)

Tested on 2017-11-09 with Laravel v5.5.20 and Adldap2-Laravel v3.0.5.

### 1. Create a new Laravel project and install Adldap2-Laravel

```bash
composer create-project laravel/laravel laravel-simple-ldap-auth
cd laravel-simple-ldap-auth
composer require adldap2/adldap2-laravel
```

### 2. Register Adldap's service providers and façade in `config/app.php`
 
```php
'providers' => [
    ...
    Adldap\Laravel\AdldapServiceProvider::class,
    Adldap\Laravel\AdldapAuthServiceProvider::class,
],

'aliases' => [
    ...
    'Adldap' => Adldap\Laravel\Facades\Adldap::class,
],
```

#### 2b. Publish Adldap

```bash
php artisan vendor:publish --tag="adldap"
```

### 3. Change the driver of the user provider in `config/auth.php`

```php
'providers' => [
    'users' => [
        'driver' => 'adldap', // was 'eloquent'
        'model'  => App\User::class,
    ],
],
```

### 4. Configure the Adldap2 connection in `config/adldap.php`

Here, I tried to add a new connection and leave `default` untouched, but it didn't work.
Adldap2 kept trying to connect as administrator using the default setup, so I had to modify `default` directly:

```php
'connections' => [
    'default' => [
        'auto_connect' => false,
        'connection' => Adldap\Connections\Ldap::class,
        'schema' => Adldap\Schemas\OpenLDAP::class, // was Adldap\Schemas\ActiveDirectory::class
        'connection_settings' => [
            'account_prefix' => env('ADLDAP_ACCOUNT_PREFIX', ''),
            'account_suffix' => env('ADLDAP_ACCOUNT_SUFFIX', ''),
            'domain_controllers' => explode(' ', env('ADLDAP_CONTROLLERS', 'corp-dc1.corp.acme.org corp-dc2.corp.acme.org')),
            'port' => env('ADLDAP_PORT', 389),
            'timeout' => env('ADLDAP_TIMEOUT', 5),
            'base_dn' => env('ADLDAP_BASEDN', 'dc=corp,dc=acme,dc=org'),
            'admin_account_suffix' => env('ADLDAP_ADMIN_ACCOUNT_SUFFIX', ''),
            'admin_username' => env('ADLDAP_ADMIN_USERNAME', ''),
            'admin_password' => env('ADLDAP_ADMIN_PASSWORD', ''),
            'follow_referrals' => true,
            'use_ssl' => false,
            'use_tls' => false,
        ],
    ],
],
```

### 5. Change the usernames and attributes to synchronize in `config/adldap_auth.php`:

This configuration specifies which fields are copied from the LDAP server into the local database for each logged in user.

Some examples of extra attributes to synchronize could be "role" to control access to certain areas or "session_expiration_in_minutes" to force logout after some time. I am sure you can think of many other uses.

The number of fields available in the testing LDAP server is limited, so we will add `'phone'` as an example.

```php
'usernames' => [
    'ldap' => env('ADLDAP_USER_ATTRIBUTE', 'userprincipalname'), // was just 'userprincipalname'
    'eloquent' => 'username', // was 'email'
],

'sync_attributes' => [
    // 'field_in_local_db' => 'attribute_in_ldap_server',
    'username' => 'uid', // was 'email' => 'userprincipalname',
    'name' => 'cn',
    'phone' => 'telephonenumber',
],
```

### 6. Configure your LDAP and database connections in `.env`

FYI, configuration that is secret, i.e. API tokens or database passwords, should be stored in this file,
which Laravel includes by default in `.gitignore`.

```
ADLDAP_CONNECTION=default
ADLDAP_CONTROLLERS=ldap.forumsys.com 
ADLDAP_BASEDN=dc=example,dc=com
ADLDAP_USER_ATTRIBUTE=uid
ADLDAP_USER_FORMAT=uid=%s,dc=example,dc=com

DB_CONNECTION=sqlite  # was 'mysql'
DB_HOST=127.0.0.1     # remove this line
DB_PORT=3306          # remove this line
DB_DATABASE=homestead # remove this line
DB_USERNAME=homestead # remove this line
DB_PASSWORD=secret    # remove this line
```

### 7. Change `database/migrations/2014_10_12_000000_create_users_table.php`

```php
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username')->unique(); // was 'email'
            $table->string('password');
            $table->string('name'); // to be read from LDAP
            $table->string('phone'); // extra field to read from LDAP
            $table->rememberToken();
            $table->timestamps();
        });
    }
```

### 8. Delete the file `database/migrations/2014_10_12_100000_create_password_resets_table.php`

### 9. Change `app/User.php`

```php
protected $fillable = [
    // replace 'email' with 'username' and add 'phone'
    'name', 'username', 'password', 'phone',
];
```

### 10 Run the migration to create the `users` table and Auth scaffolding

Before migrating, make sure that your database is configured and working properly.

```bash
touch database/database.sqlite
php artisan migrate
php artisan make:auth
```

This last command installs many controllers and views that we are not going to need, so let's remove them.

### 11. Delete these files and folder

- `app/Http/Controllers/Auth/ForgotPasswordController.php`
- `app/Http/Controllers/Auth/RegisterController.php`
- `app/Http/Controllers/Auth/ResetPasswordController.php`
- `resources/views/auth/register.blade.php`
- `resources/views/auth/passwords` --> remove folder and all files inside

### 12. Remove this line from `resources/views/layouts/app.blade.php`

```html
<li><a href="{{ route('register') }}">Register</a></li>
```

### 13. Remove this line from `resources/views/welcome.blade.php`

```html
<a href="{{ url('/register') }}">Register</a>
```

### 14. Change 'email' for 'username' in `resources/views/auth/login.blade.php`

```html
<div class="form-group{{ $errors->has('username') ? ' has-error' : '' }}">
    <label for="username" class="col-md-4 control-label">Username</label>
    <div class="col-md-6">
        <input id="username" type="text" class="form-control" name="username" value="{{ old('username') }}" required autofocus>
        @if ($errors->has('username'))
            <span class="help-block">
                <strong>{{ $errors->first('username') }}</strong>
            </span>
        @endif
    </div>
</div>
```

### 15. Add these methods to LoginController in `app/Http/Controllers/Auth/LoginController.php`

Don't forget the `use` instructions.

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Adldap\Laravel\Facades\Adldap;

class LoginController extends Controller {
    ...

    public function username() {
        return config('adldap_auth.usernames.eloquent');
    }

    protected function validateLogin(Request $request) {
        $this->validate($request, [
            $this->username() => 'required|string|regex:/^\w+$/',
            'password' => 'required|string',
        ]);
    }

    protected function attemptLogin(Request $request) {
        $credentials = $request->only($this->username(), 'password');
        $username = $credentials[$this->username()];
        $password = $credentials['password'];
        
        $user_format = env('ADLDAP_USER_FORMAT', 'cn=%s,'.env('ADLDAP_BASEDN', ''));
        $userdn = sprintf($user_format, $username);
        
        // you might need this, as reported in
        // [#14](https://github.com/jotaelesalinas/laravel-simple-ldap-auth/issues/14):
        // Adldap::auth()->bind($userdn, $password);
        
        if(Adldap::auth()->attempt($userdn, $password, $bindAsUser = true)) {
            // the user exists in the LDAP server, with the provided password
            
            $user = \App\User::where($this->username(), $username) -> first();
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
    
    protected function retrieveSyncAttributes($username) {
        $ldapuser = Adldap::search()->where(env('ADLDAP_USER_ATTRIBUTE'), '=', $username)->first();
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
        
        foreach (config('adldap_auth.sync_attributes') as $local_attr => $ldap_attr) {
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
    
    protected static function accessProtected ($obj, $prop) {
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
