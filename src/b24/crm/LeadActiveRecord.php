<?php


namespace wm\b24\crm;


use Yii;


class LeadActiveRecord extends \wm\b24\ActiveRecord
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