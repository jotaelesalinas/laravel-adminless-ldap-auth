# jotaelesalinas/laravel-adminless-ldap-auth

Authenticate users in Laravel against an _adminless_ LDAP server

## Login UI guide

You can build your own web login system around `Auth`, but as you already probably know,
Laravel provides an amazing scaffold for authentication. Here's how you can use it.

We are going to assume that you created a new, clean Laravel app and that you already went through the installation and configuration of the package and that everything worked perfectly when you tested it in Tinker.

### Scaffold the auth routes, controllers and views

```bash
composer require laravel/ui
php artisan ui vue --auth
npm install && npm run dev
```

### Delete unneeded files and folders

```bash
# user and password reset migrations
rm database/migrations/2014_10_12_000000_create_users_table.php \
   database/migrations/2014_10_12_100000_create_password_resets_table.php
# unnecessary auth controllers
rm app/Http/Controllers/Auth/ConfirmPasswordController.php \
   app/Http/Controllers/Auth/ForgotPasswordController.php \
   app/Http/Controllers/Auth/RegisterController.php \
   app/Http/Controllers/Auth/ResetPasswordController.php \
   app/Http/Controllers/Auth/VerificationController.php
# registration and email verification views
rm resources/views/auth/register.blade.php \
   resources/views/auth/verify.blade.php
# password reset views
rm -r resources/views/auth/passwords
```

### Remove unused auth routes

Replace this line in `routes/web.php`:

```php
Auth::routes();
```

With these ones:

```php
Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('login', 'Auth\LoginController@login');
Route::post('logout', 'Auth\LoginController@logout')->name('logout');
```

### Specify the users' identifier field in the login controller

Add this method to `LoginController` in `app/Http/Controllers/Auth/LoginController.php`:

```php
public function username()
{
    return config('auth.auth_user_key');
}
```

### Adapt login form

In `resources/views/auth/login.blade.php`, change `email` to `config('auth.auth_user_key')` (HTML code might change in the future):

```html
<div class="form-group row">
    <label for="{{ config('auth.auth_user_key') }}"
           class="col-md-4 col-form-label text-md-right"
        >{{ __('Username') }}</label>

    <div class="col-md-6">
        <input id="{{ config('auth.auth_user_key') }}"
               type="text"
               class="form-control @error(config('auth.auth_user_key')) is-invalid @enderror"
               name="{{ config('auth.auth_user_key') }}"
               value="{{ old(config('auth.auth_user_key')) }}"
               required
               autocomplete="{{ config('auth.auth_user_key') }}"
               autofocus>

        @error(config('auth.auth_user_key'))
            <span class="invalid-feedback" role="alert">
                <strong>{{ $message }}</strong>
            </span>
        @enderror
    </div>
</div>
```

Also, delete the whole "Remember me" section:

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

Modify `resources/views/home.blade.php`, replacing the line:

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

### Good to go!

Change your app name and URL in `.env`:

```ini
APP_NAME=Adminless LDAP
APP_URL=http://localhost:8000
```

Let's run the website and try to log in.

```bash
php artisan optimize:clear
php artisan serve
```

Visit <http://localhost:8000> in your favourite browser.

Try to visit <http://localhost:8000/home> before logging in. You should be redirected to the login page.

**Bonus!** Because you read this tutorial about creating the login UI, you can also login to the public testing LDAP server using these new users: `riemann` and `gauss`. The password is `password` as usual.

Log in and play around.

Was this package useful? Give it a star!
