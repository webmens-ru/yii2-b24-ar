<?php

namespace wm\b24\crm;

//use yii\base\Model;
use Bitrix24\B24Object;
use phpDocumentor\Reflection\DocBlock\Tags\Source;
use wm\b24tools\b24Tools;
use Yii;
use yii\helpers\ArrayHelper;


class SourceActiveRecord extends \wm\b24\ActiveRecord
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
