<?php
/**
 *  This file is part of the Xpressengine package.
 *
 * PHP version 5
 *
 * @category    Log
 * @package     Xpressengine\Log
 * @author      XE Team (developers) <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        http://www.xpressengine.com
 */
namespace Xpressengine\Log\Loggers;

use Illuminate\Contracts\Foundation\Application;
use Xpressengine\Http\Request;
use Xpressengine\Log\AbstractLogger;
use Xpressengine\Log\Models\Log;

/**
 * @category    Log
 * @package     Xpressengine\Log
 * @author      XE Team (developers) <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/lgpl-3.0-standalone.html LGPL
 * @link        http://www.xpressengine.com
 */
class UserLogger extends AbstractLogger
{
    const ID = 'user';

    const TITLE = '회원';

    protected $app;

    /**
     * Logger Init 세팅
     *
     * @param Application $app app
     *
     * @return void
     */
    public function initLogger(Application $app)
    {
        $this->app = $app;

        $app['events']->listen('Illuminate\Foundation\Http\Events\RequestHandled', function ($result) {
            if ($result->request->route() == null) {
                return;
            }

            $summary = self::getSummary($result->request);

            self::writeLog($result->request, $summary);
        });

        self::registerIntercept();
    }

    /**
     * route name 확인해서 로그 종류 검색
     *
     * @param Request $request request
     *
     * @return string|null
     */
    protected function getSummary(Request $request)
    {
        $list = [
                'settings.user.index' => '회원목록 열람',
                'settings.user.edit' => '회원상세정보 열람',
                'settings.user.create' => '회원 추가',
                'settings.user.mail.add' => '회원 이메일 추가',
                'settings.user.mail.delete' => '회원 이메일 삭제',
                'settings.user.mail.confirm' => '회원 이메일 승인',
                'settings.user.update' => '회원정보 수정',
                'settings.user.destroy' => '회원정보 삭제',
            ];

        return array_get($list, $request->route()->getName(), []);
    }

    /**
     * 회원 권한 변경 로그 작성 인터셉트 등록
     *
     * @return void
     */
    protected function registerIntercept()
    {
        intercept(
            'Xpressengine\User\UserHandler@update',
            'UserLogger::user.update',
            function ($target, $user, $userData) {
                $updateUser['beforeRating'] = $user['rating'];
                $target($user, $userData);
                $updateUser['afterRating'] = $user['rating'];
                $updateUser['userDisplayName'] = $user['display_name'];

                if ($updateUser['beforeRating'] != $updateUser['afterRating']) {
                    $request = request();

                    $ratingNames = [
                        'member' => xe_trans('xe::memberRatingNormal'),
                        'manager' => xe_trans('xe::memberRatingManager'),
                        'super' => xe_trans('xe::memberRatingAdministrator'),
                    ];

                    $summary = '회원 권한 수정 (' . $updateUser['userDisplayName'] . ' : '
                        . $ratingNames[$updateUser['beforeRating']] . '=>'
                        . $ratingNames[$updateUser['afterRating']] . ')';

                    self::writeLog($request, $summary);
                }
            }
        );
    }

    /**
     * 로그 작성 대상 route가 맞는지 확인
     * 로그 작성 전 관리자 확인
     *
     * @param Request $request request
     * @param string  $summary log 요약
     *
     * @return void
     */
    protected function writeLog(Request $request, $summary)
    {
        if ($summary == null) {
            return;
        }

        if (!$this->isAdmin($request)) {
            return;
        }

        self::storeLog($request, $summary);
    }

    /**
     * 로그 작성
     *
     * @param Request $request request
     * @param string  $summary log 요약
     *
     * @return void
     */
    protected function storeLog(Request $request, $summary)
    {
        $data = $this->loadRequest($request);
        array_set($data['data'], 'route', $request->route()->getName());
        array_forget($data['parameters'], 'password');
        array_set($data['data'], 'user_id', $request->route()->parameter('id'));
        $data['summary'] = $summary;

        $this->log($data);
    }

    /**
     * render log entity to html
     *
     * @param Log $log log entity
     *
     * @return string|null
     */
    public function renderDetail(Log $log)
    {
        return null;
    }
}
