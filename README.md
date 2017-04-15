# jotaelesalinas/laravel-simple-ldap/auth

This is a basic [Laravel](https://laravel.com/) installation manual (tested with version 5.4)
adapted to log in users from an external LDAP server.

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

## Steps

### 1. Create a new Laravel project

```bash
composer create-project laravel/laravel laravel-simple-ldap-auth
cd laravel-simple-ldap
```

### 2. Add `build` folder and `composer.lock` to `.gitignore`:

```
/build
composer.lock
```

### 3. Install Adldap2-Laravel

```bash
composer require adldap2/adldap2-laravel
```

### 4. Register Adldap's service providers and façade in `config/app.php`
  
```
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

### 5. Change the driver of the user provider in `config/auth.php`

```
'providers' => [
    'users' => [
        'driver' => 'adldap', // was 'eloquent'
        'model'  => App\User::class,
    ],
],
```

### 6. Copy Adldap2's configuration files to the `config` folder:

```
php artisan vendor:publish
```

### 7. Configure the Adldap2 connection in `adldap.php`

Here, I tried to add a new connection and leave `default` untouched, but it didn't work.
Adldap2 kept trying to connect as administrator using the default setup, so I had to modify `default` directly:

```
'connections' => [
    'default' => [
        'auto_connect' => false,
        'connection' => Adldap\Connections\Ldap::class,
        'schema' => Adldap\Schemas\ActiveDirectory::class,
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

### 8. Change the usernames and attributes to synchronize in `config/adldap_auth.php`:

This configuration specifies which fields are copied from the LDAP server into the local database for each logged in user.

Some examples could be an attribute "role" or "session_expiration_in_minutes". I am sure you can think of many other uses.

```
'usernames' => [
    'ldap' => env('ADLDAP_USER_ATTRIBUTE', 'userprincipalname'), // was just 'userprincipalname'
    'eloquent' => 'username', // was 'email'
],

'sync_attributes' => [
    'username' => 'uid', // was 'email' => 'userprincipalname',
    'name' => 'cn',
],
```

### 9. Configure your LDAP connections in `.env`

FYI, configuration that is secret, e.g. API tokens or database passwords, should be store in this file,
which is included by default in `.gitignore` by Laravel.

```
ADLDAP_CONNECTION=default
ADLDAP_CONTROLLERS=ldap.forumsys.com 
ADLDAP_BASEDN=dc=example,dc=com
ADLDAP_USER_ATTRIBUTE=uid
ADLDAP_USER_FORMAT=uid=%s,dc=example,dc=com
```

### 10. Configure your database in `.env`

Use whatever you need. Here you see changes for Sqlite.

```
DB_CONNECTION=sqlite  --> was 'mysql'
DB_HOST=127.0.0.1     --> remove line
DB_PORT=3306          --> remove line
DB_DATABASE=homestead --> remove line
DB_USERNAME=homestead --> remove line
DB_PASSWORD=secret    --> remove line
```

### 11. Change `database/migrations/2014_10_12_000000_create_users_table.php`

```
$table->string('username')->unique(); // was 'email'
```

### 12. Change `app/User.php`

```
protected $fillable = [
    'name', 'username', 'password', // was 'email' instead of 'username'
];
```

### 13. Delete the file `database/migrations/2014_10_12_100000_create_password_resets_table.php`

### 14. Run the migration to create the `users` table

Before migrating, make sure that your database is working properly.

```bash
touch database/database.sqlite
php artisan migrate
```

### 15. Install Laravel's built-in Auth

```bash
php artisan make:auth
```

This last command installed many controllers and views that we are not going to need, so let's remove them.

### 16. Delete these files

- `app/Http/Controllers/Auth/ForgotPasswordController.php`
- `app/Http/Controllers/Auth/RegisterController.php`
- `app/Http/Controllers/Auth/ResetPasswordController.php`
- `resources/views/auth/register.blade.php`
- `resources/views/auth/passwords`

### 17. Remove this line from `resources/views/layouts/app.blade.php`

```
<li><a href="{{ route('register') }}">Register</a></li>
```

### 18. Remove this line from `resources/views/welcome.blade.php`

```
<a href="{{ url('/register') }}">Register</a>
```

### 19. Change 'email' for 'username' in `resources/views/auth/login.blade.php`

We are using usernames instead of emails -Laravel's default,- so we need some changes in the login view.

```
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

### 20. Add these methods to LoginController in `app/Http/Controllers/Auth/LoginController.php`

Don't forget the `use` instructions.

```
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
        
        if(Adldap::auth()->attempt($userdn, $password, $bindAsUser = true)) {
            $user = \App\User::where($this->username(), $username) -> first();
            if ( !$user ) {
                $user = new \App\User();
                $user->name = $username;
                $user->username = $username;
                $user->password = '';
            }
            $this->guard()->login($user, true);
            return true;
        }
        
        return false;
    }
    
}
```

# Run the website

We're done!

Let's run the website and try to log in.

```
php artisan serve
```

Visit `http://localhost:8000` in your favourite browser.

Try to visit `http://localhost:8000/home` before logging in.


Remember that you have these users available in the testing LDAP server:
`riemann`, `gauss`, `euler`, `euclid`, `einstein`, `newton`, `galieleo` and `tesla`.
The password is `password` for all of them.

Log in. Play around. Enjoy life!
