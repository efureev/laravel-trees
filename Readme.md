[![Latest Stable Version](https://poser.pugx.org/efureev/social/v/stable)](https://packagist.org/packages/efureev/social)
[![Total Downloads](https://poser.pugx.org/efureev/social/downloads)](https://packagist.org/packages/efureev/social)
[![Latest Unstable Version](https://poser.pugx.org/efureev/social/v/unstable)](https://packagist.org/packages/efureev/social)

## Information
Wrapper on Laravel Socialite

## Install
- `composer require efureev/social`
- In file `AppServiceProvider.php` register `AuthenticatableModel` binding.
Value - your Auth Model:
```php
public function register(): void
{
    $this->app->bind('AuthenticatableModel', User::class);
}
```

## Config

### Config Props 
- `redirectOnAuth` [string] redirect on address after user auth.
- `onSuccess` [\Closure|array] action on auth success. Params: \Fureev\Socialite\Two\AbstractProvider
- `drivers` [array] Driver list (`driverName => driverConfig`)

### Driver Config
- `clientId` [string] Require
- `clientSecret` [string] Require
- `enabled`  [bool] Default, true.
- `label` [string] Title for view. Default, `driverName`
- `provider` [string] Class of Provider (\Fureev\Socialite\Two\AbstractProvider)
- `url_token`  [string] Token URL for the provider
- `url_auth`   [string] Authentication URL for the provider
- `userInfoUrl`   [string] Url to get the raw user data
- `onSuccess` [\Closure|array] action on auth success. Overwrite common `onSuccess` 
- `scopeSeparator` [string]
- `scopes` [array]

### Example
File `config/social.php`
```php
<?php

return [
    'onSuccess' => function ($driver) {
        $user = \Fureev\Social\Services\SocialAccountService::setOrGetUser($driver);

        return \Fureev\Social\Services\SocialAccountService::auth($user);
    },
    //'onSuccess' => [\App\Http\Controllers\IndexController::class, 'index'],
    'drivers'   => [
        'gitlab' => [
            'enabled'  => false,
            'provider' => \Fureev\Socialite\Two\GitlabProvider::class,
            //            'enabled'  => false,
            'label'    => '<i class="fab fa-gitlab"></i>'
        ],
        'vk'     => [
            // 'enabled'  => false,
            'label'        => '<i class="fab fa-vk"></i>',
            'clientId'     => env('VK_CLIENT_ID'),
            'clientSecret' => env('VK_CLIENT_SECRET'),
        ],
        'github' => [
            'enabled' => false,
            'label'   => '<i class="fab fa-github-alt"></i>'
        ],
        'google' => [
            // 'enabled'      => false,
            'clientId'         => env('G+_CLIENT_ID'),
            'clientSecret'     => env('G+_CLIENT_SECRET'),
            'url_token'        => 'https://accounts.google.com/o/oauth2/token',
            'url_auth'         => 'https://accounts.google.com/o/oauth2/auth',
            'userInfoUrl'      => 'https://www.googleapis.com/plus/v1/people/me?',
            'label'            => '<i class="fab fa-google"></i>',
            //            'onSuccess'        => [\App\Http\Controllers\HomeController::class, 'index'],
            'scopeSeparator'   => ' ',
            'scopes'           => ['openid', 'profile', 'email',],
            'tokenFieldsExtra' => [
                'grant_type' => 'authorization_code'
            ],
            'mapFields'        =>
                [
                    'id'     => 'id',
                    'name'   => 'displayName',
                    'email'  => 'emails.0.value',
                    'avatar' => 'image.url',
                ],
            'guzzle'           => [
                'query'   => [
                    'prettyPrint' => 'false',
                ],
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer {{%TOKEN%}}',
                ],
            ]
        ]
    ]
];

```

File `\App\Services\SocialAccountService.php`
```php
<?php 
use Fureev\Socialite\Contracts\Provider as ProviderContract;
class SocialAccountService
{
    public static function setOrGetUser(ProviderContract $provider)
    {
        $providerUser = $provider->user();

        $providerName = $provider->getName();

        //...

    }
}
```