<?php


namespace wm\b24\crm;


use yii\helpers\ArrayHelper;

class CompanyActiveQuery extends \wm\b24\ActiveQuery
{
    //    public $entityTypeId;

    protected $listMethodName = 'crm.company.list';

    protected $oneMethodName = 'crm.company.get';



    public function getEntityTypeIdUsedInFrom()
    {
//        if (empty($this->entityTypeId)) {
//            $this->entityTypeId = $this->modelClass::entityTypeId();
//        }

        return '';
    }

//    protected function getPrimaryTableName()
//    {
//        $modelClass = $this->modelClass;
//        //return $modelClass::tableName();
//        return $modelClass::entityTypeId();
//    }

    protected function prepairParams(){
//        $this->getEntityTypeIdUsedInFrom();
        $data = [
//            'entityTypeId' => $this->entityTypeId,
            'filter' => $this->where,
            'order' => $this->orderBy?$this->orderBy:null,
            'select' => $this->select,
            //Остальные параметры
        ];
        $this->params = $data;
    }

    protected function prepairOneParams(){
        $this->getEntityTypeIdUsedInFrom();
        $id = null;
        if(ArrayHelper::getValue($this->where, 'id')){
            $id = ArrayHelper::getValue($this->where, 'id');
        }
        if(ArrayHelper::getValue($this->link, 'id')){
            $id = ArrayHelper::getValue($this->where, 'inArray.0');
        }
        $data = [
//            'entityTypeId' => $this->entityTypeId,
            'id' => $id
        ];
        $this->params = $data;
    }
}