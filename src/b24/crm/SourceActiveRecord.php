<?php

namespace wm\yii2-b24-ar\crm;

//use yii\base\Model;
use Bitrix24\B24Object;
use phpDocumentor\Reflection\DocBlock\Tags\Source;
use wm\yii2-b24-artools\b24Tools;
use Yii;
use yii\helpers\ArrayHelper;


class SourceActiveRecord extends \wm\yii2-b24-ar\ActiveRecord
{
    public static function entityId()
    {
        return 'SOURCE';
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
        return Yii::createObject(SourceActiveQuery::className(), [get_called_class()]);
    }
}
