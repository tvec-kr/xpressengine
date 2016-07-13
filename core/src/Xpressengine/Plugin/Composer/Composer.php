<?php
/**
 *  This file is part of the Xpressengine package.
 *
 * PHP version 5
 *
 * @category
 * @package     Xpressengine\
 * @author      XE Team (developers) <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        http://www.xpressengine.com
 */
namespace Xpressengine\Plugin\Composer;

use Composer\Plugin\CommandEvent;
use Composer\Script\Event;
use Xpressengine\Plugin\MetaFileReader;
use Xpressengine\Plugin\PluginScanner;

/**
 * @category
 * @package     Xpressengine\Plugin
 * @author      XE Team (developers) <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        http://www.xpressengine.com
 */
class Composer
{
    protected static $metaFileName = 'composer.json';

    protected static $pluginsDir = 'plugins';

    protected static $packagistUrl = 'https://xpressengine.io';

    protected static $composerFile = 'storage/app/composer.plugins.json';

    protected static $installedFlagPath = 'storage/app/installed';

    public static $basePlugins = [
        'xpressengine-plugin/alice' => '0.9.0',
        'xpressengine-plugin/claim' => '0.9.0',
        'xpressengine-plugin/board' => '0.9.0',
        'xpressengine-plugin/ckeditor' => '0.9.0',
        'xpressengine-plugin/comment' => '0.9.0',
        'xpressengine-plugin/page' => '0.9.0',
        'xpressengine-plugin/news_client' => '0.9.0',
        "xpressengine-plugin/google_analytics" => "0.9.0",
        "xpressengine-plugin/orientator" => "0.9.0",
        "xpressengine-plugin/external_page" => "0.9.0",
        "xpressengine-plugin/social_login" => "0.9.0",
    ];

    public static function init(CommandEvent $event)
    {
        $path = static::$composerFile;
        $writer = self::getWriter($path);

        // composer.plugins.json 파일이 존재하지 않을 경우 초기화
        $writer->resolvePlugins()->write();

        // XE가 설치돼 있지 않을 경우, base plugin require에 추가
        if(!file_exists(static::$installedFlagPath)) {
            foreach (static::$basePlugins as $plugin => $version) {
                $writer->install($plugin, $version);
            }
            static::applyRequire($writer);
            $writer->setFixMode();
            $event->getOutput()->writeln("xpressengine-installer: running in update mode");
        } else {
            static::applyRequire($writer);
            if(static::isUpdateMode($event)) {
                $writer->setUpdateMode();
                $event->getOutput()->writeln("xpressengine-installer: running in update mode");
            } else {
                $writer->setFixMode();
                $event->getOutput()->writeln("xpressengine-installer: running in fix mode");
            }
        }
        $writer->write();

        $event->getOutput()->writeln("xpressengine-installer: Plugin composer file[$path] is written");
    }

    public static function postUpdate(Event $event)
    {
        $path = static::$composerFile;

        // XE가 설치돼 있지 않을 경우, resolve plugins
        $writer = self::getWriter($path);
        $writer->resolvePlugins()->setFixMode()->write();
    }

    /**
     * getWriter
     *
     * @param string $path
     *
     * @return ComposerFileWriter
     * @throws \Exception
     */
    protected static function getWriter($path)
    {
        $reader = new MetaFileReader(static::$metaFileName);
        $scanner = new PluginScanner($reader, static::$pluginsDir);
        $writer = new ComposerFileWriter($path, $scanner, static::$packagistUrl);

        return $writer;
    }

    private static function applyRequire(ComposerFileWriter $writer)
    {
            $installs = $writer->get('xpressengine-plugin.install', []);
            foreach ($installs as $name => $version) {
                $writer->addRequire($name, $version);
            }
            $updates = $writer->get('xpressengine-plugin.update', []);
            foreach ($updates as $name => $version) {
                $writer->addRequire($name, $version);
            }
            $uninstalls = $writer->get('xpressengine-plugin.uninstall', []);
            foreach ($uninstalls as $name) {
                $writer->removeRequire($name);
            }

    }

    private static function isUpdateMode(CommandEvent $event)
    {
        if($event->getInput()->hasArgument('packages')) {
            $packages = $event->getInput()->getArgument('packages');
            $packages = array_shift($packages);
            return ($packages && strpos($packages, 'xpressengine-plugin') === 0);
        } else {
            return false;
        }
    }
}