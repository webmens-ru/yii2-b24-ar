<?php


namespace wm\yii2-b24-ar\crm;


use Yii;


class LeadActiveRecord extends \wm\yii2-b24-ar\ActiveRecord
{
    public static function fieldsMethod()
    {
        return 'crm.lead.fields';
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
        return Yii::createObject(LeadActiveQuery::className(), [get_called_class()]);
    }
}