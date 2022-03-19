<?php

namespace wm\yii2-b24-ar\crm;

//use yii\base\Model;
use Bitrix24\B24Object;
use wm\yii2-b24-artools\b24Tools;
use Yii;
use yii\helpers\ArrayHelper;


class SpActiveRecord extends \wm\yii2-b24-ar\ActiveRecord
{
    public static function entityTypeId()
    {
        return null;
    }

    public static function fieldsMethod()
    {
        return 'crm.item.fields';
    }

    public static function tableSchemaCaheKey()
    {
        return static::fieldsMethod()._.static::entityTypeId();
    }

    public static function getValueKey()
    {
        return 'result.fields';
    }

    public static function callAdditionalParameters()
    {
        return ['entityTypeId' => static::entityTypeId()];
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
        return Yii::createObject(SpActiveQuery::className(), [get_called_class()]);
    }

//    public static function listDataSelector()
//    {
//        return 'result.items';
//    }
}
