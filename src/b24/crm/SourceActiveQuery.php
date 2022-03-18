<?php

namespace wm\b24\crm;

//Код не универсален а направлен на смарт процессы стоит перенести в другой класс
use yii\helpers\ArrayHelper;
use wm\b24\ActiveQuery;

class SourceActiveQuery extends ActiveQuery {

    public $entityId;

    protected $listMethodName = 'crm.status.list';

    protected $oneMethodName = 'crm.status.get';

    public function getEntityIdUsedInFrom()
    {
        if (empty($this->entityId)) {
            $this->entityId = $this->modelClass::entityId();
        }

        return $this->entityId;
    }

//    protected function getPrimaryTableName()
//    {
//        $modelClass = $this->modelClass;
//        //return $modelClass::tableName();
//        return $modelClass::entityTypeId();
//    }

    protected function prepairParams(){
        $this->getEntityIdUsedInFrom();
        $data = [
            'filter' => array_merge($this->where, ['ENTITY_ID' => $this->entityId]),
            'order' => $this->orderBy,
            //'select' => $this->select,
            //Остальные параметры
        ];
        $this->params = $data;
    }

    protected function prepairOneParams(){
        $id = null;
        if(ArrayHelper::getValue($this->where, 'id')){
            $id = ArrayHelper::getValue($this->where, 'id');
        }
        if(ArrayHelper::getValue($this->link, 'id')){
            $id = ArrayHelper::getValue($this->where, 'inArray.0');
        }
        $data = [
            'id' => $id
        ];
        $this->params = $data;
    }
}
