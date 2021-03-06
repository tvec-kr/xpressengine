<?php

/**
 * RegisterFormPart.php
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

namespace Xpressengine\User\Parts;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\HtmlString;
use Xpressengine\Http\Request;
use Xpressengine\Skin\SkinEntity;
use Xpressengine\Support\ValidatesRequestsTrait;

/**
 * Abstract class RegisterFormPart
 *
 * @category    User
 * @package     Xpressengine\User
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL-2.1
 * @link        https://xpressengine.io
 */
abstract class RegisterFormPart
{
    use ValidatesRequestsTrait {
        ValidatesRequestsTrait::validate as traitValidate;
    }

    const ID = '';

    const NAME = '';

    const DESCRIPTION = '';

    /**
     * Request instance
     *
     * @var Request
     */
    protected $request;

    /**
     * Indicates if the form part is implicit
     *
     * @var bool
     */
    protected static $implicit = false;

    /**
     * The view for the form part
     *
     * @var string
     */
    protected static $view = '';

    /**
     * The skin resolver
     *
     * @var \Xpressengine\Skin\SkinHandler
     */
    protected static $resolver;

    /**
     * The service container
     *
     * @var Container
     */
    protected static $container;

    /**
     * The skin key
     *
     * @var string
     */
    protected $skinKey = 'user/auth';

    /**
     * RegisterFormPart constructor.
     *
     * @param Request $request request instance
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Get the html string of the form part
     *
     * @return HtmlString|string
     */
    public function render()
    {
        return new HtmlString($this->getSkin()->setView(static::$view)->setData($this->data())->render());
    }

    /**
     * Determine if form part is implicit
     *
     * @return bool
     */
    public static function isImplicit()
    {
        return static::$implicit;
    }

    /**
     * Get data for form part view
     *
     * @return array
     */
    protected function data()
    {
        return [];
    }

    /**
     * Get validation rules of the form part
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    /**
     * Validate the request with the form part rules.
     *
     * @return array
     */
    public function validate()
    {
        return $this->traitValidate($this->request, $this->rules());
    }

    /**
     * Set the view to the form part
     *
     * @param string $view view
     * @return void
     */
    public static function setView($view)
    {
        static::$view = $view;
    }

    /**
     * Get the view of the form part
     *
     * @return string
     */
    public static function getView()
    {
        return static::$view;
    }

    /**
     * Get the skin of the form part
     *
     * @return SkinEntity
     */
    public function getSkin()
    {
        return static::resolveSkin($this->getSkinKey());
    }

    /**
     * Get skin key to the form part
     *
     * @return string
     */
    public function getSkinKey()
    {
        return $this->skinKey;
    }

    /**
     * Set skin key to the form part
     *
     * @param string $key key
     * @return void
     */
    public function setSkinKey($key)
    {
        $this->skinKey = $key;
    }

    /**
     * Resolve a skin
     *
     * @param string $key key
     * @return SkinEntity
     */
    public static function resolveSkin($key)
    {
        return static::$resolver->getAssigned($key);
    }

    /**
     * Set the skin resolver instance
     *
     * @param \Xpressengine\Skin\SkinHandler $resolver resolver
     * @return void
     */
    public static function setSkinResolver($resolver)
    {
        static::$resolver = $resolver;
    }

    /**
     * Get the skin resolver instance
     *
     * @return \Xpressengine\Skin\SkinHandler
     */
    public static function getSkinResolver()
    {
        return static::$resolver;
    }

    /**
     * Get a service from the container
     *
     * @param string $abstract service name
     * @return mixed
     */
    public function service($abstract)
    {
        return static::$container->make($abstract);
    }

    /**
     * Set the container
     *
     * @param Container $container container
     * @return void
     */
    public static function setContainer(Container $container)
    {
        static::$container = $container;
    }
}
