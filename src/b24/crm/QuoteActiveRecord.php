<?php


namespace wm\yii2-b24-ar\crm;


use Yii;


class QuoteActiveRecord extends \wm\yii2-b24-ar\ActiveRecord
{
    public static function fieldsMethod()
    {
        return 'crm.quote.fields';
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
        return Yii::createObject(QuoteActiveQuery::className(), [get_called_class()]);
    }
}