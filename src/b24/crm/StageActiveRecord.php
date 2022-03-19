<?php

namespace wm\yii2-b24-ar\crm;

//use yii\base\Model;
use Bitrix24\B24Object;
use wm\yii2-b24-artools\b24Tools;
use Yii;
use yii\helpers\ArrayHelper;


class StageActiveRecord extends \wm\yii2-b24-ar\ActiveRecord
{
    public static function entityTypeId()
    {
        return null;
    }

    public static function fieldsMethod()
    {
        return 'crm.status.fields';
    }

    public function fields()
    {
        return $this->attributes();
    }

    public static function getFooter($models)
    {
        return [];
    }

    public static function find()
    {
        return Yii::createObject(StageActiveQuery::className(), [get_called_class()]);
    }
}
