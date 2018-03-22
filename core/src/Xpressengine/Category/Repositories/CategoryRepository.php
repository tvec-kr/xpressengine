<?php

/**
 * CategoryRepository.php
 *
 * PHP version 5
 *
 * @category    Category
 * @package     Xpressengine\Category
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL-2.1
 * @link        https://xpressengine.io
 */

namespace Xpressengine\Category\Repositories;

use Xpressengine\Category\Models\Category;
use Xpressengine\Support\EloquentRepositoryTrait;

/**
 * Class CategoryRepository
 *
 * @category    Category
 * @package     Xpressengine\Category
 * @author      XE Developers <developers@xpressengine.com>
 * @copyright   2015 Copyright (C) NAVER Corp. <http://www.navercorp.com>
 * @license     http://www.gnu.org/licenses/old-licenses/lgpl-2.1.html LGPL-2.1
 * @link        https://xpressengine.io
 */
class CategoryRepository
{
    use EloquentRepositoryTrait;

    /**
     * The model name for the repository
     *
     * @var string
     */
    protected $model = Category::class;
}
