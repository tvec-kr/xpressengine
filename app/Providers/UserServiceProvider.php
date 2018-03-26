<?php
/**
 * This file is register this package for laravel
 *
 * PHP version 5
 *
 * @category    User
 * @package     Xpressengine\User
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL-2.1
 * @link        https://xpressengine.io
 */

namespace App\Providers;

use Closure;
use Illuminate\Contracts\Validation\Factory as Validator;
use Illuminate\Support\ServiceProvider;
use Xpressengine\Media\Thumbnailer;
use Xpressengine\User\EmailBroker;
use Xpressengine\User\Guard;
use Xpressengine\User\Middleware\Admin;
use Xpressengine\User\Models\Guest;
use Xpressengine\User\Models\UnknownUser;
use Xpressengine\User\Models\User;
use Xpressengine\User\Parts\AgreementPart;
use Xpressengine\User\Parts\CaptchaPart;
use Xpressengine\User\Parts\DefaultPart;
use Xpressengine\User\Parts\DynamicFieldPart;
use Xpressengine\User\Parts\RegisterFormPart;
use Xpressengine\User\Parts\EmailVerifyPart;
use Xpressengine\User\Repositories\PendingEmailRepository;
use Xpressengine\User\Repositories\PendingEmailRepositoryInterface;
use Xpressengine\User\Repositories\RegisterTokenRepository;
use Xpressengine\User\Repositories\TermsRepository;
use Xpressengine\User\Repositories\UserAccountRepository;
use Xpressengine\User\Repositories\UserAccountRepositoryInterface;
use Xpressengine\User\Repositories\UserEmailRepository;
use Xpressengine\User\Repositories\UserEmailRepositoryInterface;
use Xpressengine\User\Repositories\UserGroupRepository;
use Xpressengine\User\Repositories\UserGroupRepositoryInterface;
use Xpressengine\User\Repositories\UserRepository;
use Xpressengine\User\Repositories\UserRepositoryInterface;
use Xpressengine\User\Repositories\VirtualGroupRepository;
use Xpressengine\User\Repositories\VirtualGroupRepositoryInterface;
use Xpressengine\User\TermsHandler;
use Xpressengine\User\UserHandler;
use Xpressengine\User\UserImageHandler;
use Xpressengine\User\UserProvider;

/**
 * laravel 에서 사용하기위해 등록처리를 하는 class
 *
 * @category    User
 * @package     Xpressengine\User
 */
class UserServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        // extend xe auth
        $this->extendAuth();

        // set guest's display name
        Guest::setName($this->app['config']['xe.user.guest.name']);

        // set guest's default profile image
        Guest::setDefaultProfileImage($this->app['config']['xe.user.profileImage.default']);

        // set unknown's display name
        UnknownUser::setName($this->app['config']['xe.user.unknown.name']);

        // set unknown's default profile image
        UnknownUser::setDefaultProfileImage($this->app['config']['xe.user.profileImage.default']);

        $this->setProfileImageResolverOfUser();

        // register admin middleware
        $this->app['router']->aliasMiddleware('admin', Admin::class);

        UserHandler::setContainer($this->app['xe.register']);

        RegisterFormPart::setSkinResolver(function ($key) {
            return $this->app['xe.skin']->getAssigned($key);
        });
        RegisterFormPart::setContainer($this->app);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerHandler();
        $this->registerRepositories();

        $this->registerTokenRepository();
        $this->registerEmailBroker();

        $this->registerImageHandler();

        $this->registerTerms();

        $this->resolving();
    }

    /**
     * Register resolving callbacks.
     *
     * @return void
     */
    protected function resolving()
    {
        $this->app->resolving('validator', function () {
            $this->configValidation();
            // register validation extension for email prefix
            $this->extendValidator();
        });
        $this->app->resolving('xe.settings', function () {
            $this->registerSettingsPermissions();
        });

        $this->app->resolving('xe.user', function () {
            $this->addRegisterFormParts();
            $this->addUserSettingSection();
        });

        $this->app->resolving('mailer', function ($mailer) {
            $config = $this->app['xe.config']->get('user.common');
            if (!empty($config->get('webmasterEmail'))) {
                $mailer->alwaysFrom($config->get('webmasterEmail'), $config->get('webmasterName'));
            }
        });
    }

    /**
     * Register the email broker instance.
     *
     * @return void
     */
    protected function registerEmailBroker()
    {
        $this->app->singleton(
            'xe.auth.email',
            function ($app) {
                return new EmailBroker($app['xe.user']);
            }
        );
    }

    private function registerImageHandler()
    {
        $this->app->singleton(
            'xe.user.image',
            function ($app) {

                $profileImgConfig = config('xe.user.profileImage');

                return new UserImageHandler(
                    $app['xe.storage'], $app['xe.media'], function () {
                    return Thumbnailer::getManager();
                }, $profileImgConfig
                );
            }
        );
    }

    /**
     * registerHandler
     *
     * @return void
     */
    private function registerHandler()
    {
        $this->app->singleton(UserHandler::class, function ($app) {
            $proxyClass = $app['xe.interception']->proxy(UserHandler::class, 'XeUser');

            $userHandler = new $proxyClass(
                $app['xe.users'],
                $app['xe.user.accounts'],
                $app['xe.user.groups'],
                $app['xe.user.emails'],
                $app['xe.user.pendingEmails'],
                $app['xe.user.image'],
                $app['hash'],
                $app['validator']
            );
            return $userHandler;
        });
        $this->app->alias(UserHandler::class, 'xe.user');
    }

    private function registerTerms()
    {
        $this->app->singleton(TermsHandler::class, function ($app) {
            return new TermsHandler($app[TermsRepository::class]);
        });
        $this->app->alias(TermsHandler::class, 'xe.terms');
    }

    /**
     * register Repositories
     *
     * @return void
     */
    protected function registerRepositories()
    {
        $this->registerUserRepository();
        $this->registerAccoutRepository();
        $this->registerGroupRepository();
        $this->registerVirtualGroupRepository();
        $this->registerMailRepository();

        $this->registerTermsRepository();
    }

    protected function registerUserRepository()
    {
        $this->app->singleton(UserRepositoryInterface::class, function ($app) {
            return new UserRepository;
        });
        $this->app->alias(UserRepositoryInterface::class, 'xe.users');
    }

    /**
     * register Accout Repository
     *
     * @return void
     */
    private function registerAccoutRepository()
    {
        $this->app->singleton(UserAccountRepositoryInterface::class, function () {
            return new UserAccountRepository;
        });
        $this->app->alias(UserAccountRepositoryInterface::class, 'xe.user.accounts');
    }

    /**
     * register Group Repository
     *
     * @return void
     */
    protected function registerGroupRepository()
    {
        $this->app->singleton(UserGroupRepositoryInterface::class, function ($app) {
            return new UserGroupRepository;
        });
        $this->app->alias(UserGroupRepositoryInterface::class, 'xe.user.groups');
    }

    /**
     * register Virtual Group Repository
     *
     * @return void
     */
    protected function registerVirtualGroupRepository()
    {
        $this->app->singleton(VirtualGroupRepositoryInterface::class, function ($app) {
            /** @var Closure $vGroups */
            $vGroups = $app['config']->get('xe.group.virtualGroup.all');
            /** @var Closure $getter */
            $getter = $app['config']->get('xe.group.virtualGroup.getByUser');
            return new VirtualGroupRepository($app['xe.users'], $vGroups(), $getter);
        });
        $this->app->alias(VirtualGroupRepositoryInterface::class, 'xe.user.virtualGroups');
    }

    private function registerMailRepository()
    {
        $this->app->singleton(UserEmailRepositoryInterface::class, function ($app) {
            return new UserEmailRepository;
        });
        $this->app->alias(UserEmailRepositoryInterface::class, 'xe.user.emails');

        $this->app->singleton(PendingEmailRepositoryInterface::class, function ($app) {
            return new PendingEmailRepository;
        });
        $this->app->alias(PendingEmailRepositoryInterface::class, 'xe.user.pendingEmails');
    }

    private function registerTermsRepository()
    {
        $this->app->singleton(TermsRepository::class, function ($app) {
            return new TermsRepository;
        });
    }

    /**
     * Register the token repository implementation.
     *
     * @return void
     */
    protected function registerTokenRepository()
    {
        // register register-token repository
        $this->app->singleton(RegisterTokenRepository::class, function ($app) {
            $connection = $app['xe.db']->connection('user');

            // The database token repository is an implementation of the token repository
            // interface, and is responsible for the actual storing of auth tokens and
            // their e-mail addresses. We will inject this table and hash key to it.
            $table = $app['config']['auth.register.table'];

            $keygen = $app['xe.keygen'];

            $expire = $app['config']->get('auth.register.expire', 60);

            return new RegisterTokenRepository($connection, $keygen, $table, $expire);
        });
        $this->app->alias(RegisterTokenRepository::class, 'xe.user.register.tokens');
    }

    /**
     * extendAuth
     *
     * @return void
     */
    private function extendAuth()
    {
        $this->app['auth']->extend('xe', function ($app, $name, $config) {
            $adminAuth = $app['config']->get('auth.admin');
            $proxyClass = $app['xe.interception']->proxy(Guard::class, 'Auth'); // todo: 제거
            $provider = $app['auth']->createUserProvider($config['provider']);
            $guard = new $proxyClass(
                $name, $provider, $app['session.store'], $adminAuth, $app['request']
            );

            if (method_exists($guard, 'setCookieJar')) {
                $guard->setCookieJar($app['cookie']);
            }

            if (method_exists($guard, 'setDispatcher')) {
                $guard->setDispatcher($app['events']);
            }

            if (method_exists($guard, 'setRequest')) {
                $guard->setRequest($app->refresh('request', $guard, 'setRequest'));
            }

            return $guard;
        });

        $this->app['auth']->provider('xe', function ($app, $config) {
            return new UserProvider($app['hash'], $config['model']);
        });
    }

    private function configValidation()
    {
        // set password validation to config
        $passwordLevels =  [
            'weak' => [
                'title' => 'xe::weak',
                'validate' => function ($password) {
                    return strlen($password) >= 4;
                },
                'description' => 'xe::passwordStrengthWeakDescription'
            ],
            'normal' => [
                'title' => 'xe::normal',
                'validate' => function ($password) {
                    if (!preg_match_all(
                        '$\S*(?=\S{6,})(?=\S*[a-zA-Z])(?=\S*[\d])\S*$',
                        $password
                    )
                    ) {
                        return false;
                    }
                    return true;
                },
                'description' => 'xe::passwordStrengthNormalDescription'
            ],
            'strong' => [
                'title' => 'xe::strong',
                'validate' => function ($password) {
                    if (!preg_match_all(
                        '$\S*(?=\S{8,})(?=\S*[a-zA-Z])(?=\S*[\d])(?=\S*[\W])\S*$',
                        $password
                    )
                    ) {
                        return false;
                    }
                    return true;
                },
                'description' => 'xe::passwordStrengthStrongDescription'
            ]
        ];
        app('config')->set('xe.user.password.levels', $passwordLevels);

        // set display name validation to config
        if ($this->isInstalled()) {
            app('config')->set('xe.user.displayName.validate', function ($value) {
                if (!is_string($value) && !is_numeric($value)) {
                    return false;
                }

                if (str_contains($value, "  ")) {
                    return false;
                }

                $byte = strlen($value);
                $multiByte = mb_strlen($value);

                if ($byte === $multiByte) {
                    if ($byte < 3) {
                        return false;
                    }
                } else {
                    if ($multiByte < 2) {
                        return false;
                    }
                }
                return preg_match('/^[\pL\pM\pN][. \pL\pM\pN_-]*[\pL\pM\pN]$/u', $value);
            });
        } else {
            app('config')->set('xe.user.displayName.validate', function ($value) {
                return true;
            });
        }
    }

    /**
     * Is installed
     *
     * @return bool
     */
    protected function isInstalled()
    {
        return file_exists($this->app->storagePath() . '/app/installed');
    }

    /**
     * extendEmailPrefixValidator
     *
     * @return void
     */
    private function extendValidator()
    {
        /** @var Validator $validator */
        $validator = $this->app['validator'];

        // 도메인이 생략된 이메일 validation 추가
        $validator->extend(
            'email_prefix',
            function ($attribute, $value, $parameters) {
                if (!str_contains($value, '@')) {
                    $value .= '@test.com';
                }
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            }
        );

        // 표시이름 validation 추가
        /** @var Closure $displayNameValidate */
        $displayNameValidate = app('config')->get('xe.user.displayName.validate');
        $validator->extend(
            'display_name',
            function ($attribute, $value, $parameters) use ($displayNameValidate) {
                return $displayNameValidate($value);
            }
        );

        $passwordConfig = app('config')->get('xe.user.password');
        $levels = $passwordConfig['levels'];
        $level = $levels[$passwordConfig['default']];
        $validate = $level['validate'];

        $validator->extend(
            'password',
            function ($attribute, $value, $parameters) use ($validate) {
                return $validate($value);
            },
            xe_trans($level['description'])
        );
    }

    private function registerSettingsPermissions()
    {
        $permissions = [
            'user.list' => [
                'title' => '회원정보 보기',
                'tab' => '회원'
            ],
            'user.edit' => [
                'title' => '회원정보 수정',
                'tab' => '회원'
            ]
        ];
        $register = $this->app->make('xe.register');
        foreach ($permissions as $id => $permission) {
            $register->push('settings/permission', $id, $permission);
        }
    }

    private function setProfileImageResolverOfUser()
    {
        User::setProfileImageResolver(
            function ($imageId) {
                try {
                    if($imageId !== null) {
                        $file = $this->app['xe.storage']->find($imageId);

                        if ($file !== null) {
                            $mediaFile = $this->app['xe.media']->make($file);
                            return asset($mediaFile->url());
                        }
                    }
                } catch(\Exception $e) {
                }

                return asset($this->app['config']['xe.user.profileImage.default']);
            }
        );
    }

    protected function addRegisterFormParts()
    {
        UserHandler::addRegisterPart(EmailVerifyPart::class);
        UserHandler::addRegisterPart(DefaultPart::class);
        UserHandler::addRegisterPart(DynamicFieldPart::class);
        UserHandler::addRegisterPart(AgreementPart::class);
        UserHandler::addRegisterPart(CaptchaPart::class);
    }

    protected function addUserSettingSection()
    {
        UserHandler::setSettingsSections('settings', [
            'title' => 'xe::defaultSettings',
            'content' => function ($user) {
                // dynamic field
                $fieldTypes = $this->app['xe.dynamicField']->gets('user');

                // password configuration
                $passwordConfig = $this->app['config']->get('xe.user.password');
                $passwordLevel = array_get($passwordConfig['levels'], $passwordConfig['default']);

                $this->app['xe.frontend']->js(
                    ['assets/core/xe-ui-component/js/xe-form.js', 'assets/core/xe-ui-component/js/xe-page.js']
                )->load();

                $skin = $this->app['xe.skin']->getAssigned('user/settings');

                return $skin->setView('edit')->setData(compact('user', 'fieldTypes', 'passwordLevel'));
            }
        ]);
    }
}
