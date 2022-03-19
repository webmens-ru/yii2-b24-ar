<?php

namespace wm\b24\crm;

//Код не универсален а направлен на смарт процессы стоит перенести в другой класс
use yii\helpers\ArrayHelper;
use wm\b24\ActiveQuery;

class CategoryActiveQuery extends ActiveQuery {
    public $entityTypeId;

    protected $listMethodName = 'crm.category.list';

    protected $oneMethodName = 'crm.category.get';

    protected $listDataSelector = 'result.categories';

    protected $oneDataSelector = 'result.category';

    public function getEntityTypeIdUsedInFrom()
    {
        if (empty($this->entityTypeId)) {
            $this->entityTypeId = $this->modelClass::entityTypeId();
        }

        return $this->entityTypeId;
    }

//    protected function getPrimaryTableName()
//    {
//        $modelClass = $this->modelClass;
//        //return $modelClass::tableName();
//        return $modelClass::entityTypeId();
//    }

    protected function prepairParams(){
        $this->getEntityTypeIdUsedInFrom();
        $data = [
            'entityTypeId' => $this->entityTypeId,
            'filter' => $this->where,
            'order' => $this->orderBy,
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
            'entityTypeId' => $this->entityTypeId,
            'id' => $id
        ];
        $this->params = $data;
    }
}
