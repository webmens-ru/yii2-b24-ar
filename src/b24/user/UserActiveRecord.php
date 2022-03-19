<?php

namespace wm\yii2-b24-ar\user;

//use yii\base\Model;
use Bitrix24\B24Object;
use wm\yii2-b24-artools\b24Tools;
use Yii;
use yii\helpers\ArrayHelper;
use wm\yii2-b24-ar\TableSchema;


class UserActiveRecord extends \wm\yii2-b24-ar\ActiveRecord
{
    public static function fieldsMethod()
    {
        return 'user.fields';
    }

    public function fields()
    {
        return $this->attributes();
    }
//TODO getFooter($models) точно нужно? тут
    public static function getFooter($models)
    {
        return [];
    }

    public static function find()
    {
        return Yii::createObject(UserActiveQuery::className(), [get_called_class()]);
    }
}
