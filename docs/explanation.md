# jotaelesalinas/laravel-adminless-ldap-auth

Authenticate users in Laravel against an _adminless_ LDAP server

## Explanation of the _basic_ .env variables

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

Don't hesitate to propose improvements if you think something is unclear or not properly explained.

**Bonus!** This document was a yawner, but you finished it! Congratulations! Now you can also login to the public testing LDAP server using these new users: `euler` and `euclid`. The password is `password` for both of them.

Was this package useful? Give it a star!
