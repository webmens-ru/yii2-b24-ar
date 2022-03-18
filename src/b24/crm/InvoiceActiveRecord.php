<?php


namespace wm\b24\crm;


use Yii;


class InvoiceActiveRecord extends \wm\b24\ActiveRecord
{
    public static function entityTypeId()
    {
        return 31;
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

    public static function fieldsMethod()
    {
        return 'crm.item.fields';
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
        return Yii::createObject(InvoiceActiveQuery::className(), [get_called_class()]);
    }
}