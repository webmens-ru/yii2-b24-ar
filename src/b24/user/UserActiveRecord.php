<?php

namespace wm\b24\user;

//use yii\base\Model;
use Bitrix24\B24Object;
use wm\b24tools\b24Tools;
use Yii;
use yii\helpers\ArrayHelper;
use wm\b24\TableSchema;


class UserActiveRecord extends \wm\b24\ActiveRecord
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
