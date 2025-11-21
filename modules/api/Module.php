<?php

namespace app\modules\api;

use yii\base\Module as BaseModule;

/**
 * API module for organizing REST controllers and resources.
 */
class Module extends BaseModule
{
    /** @var string the namespace that controller classes are in */
    public $controllerNamespace = 'app\\modules\\api\\controllers';

    public function init()
    {
        parent::init();
        // Keep defaults minimal; controllers will handle content negotiation and auth behaviors.
    }
}
