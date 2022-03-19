<?php

namespace wm\yii2-b24-ar\crm;

use yii\helpers\ArrayHelper;

class SpActiveQuery extends \wm\yii2-b24-ar\ActiveQuery
{
    public $entityTypeId;

    protected $listMethodName = 'crm.item.list';

    protected $oneMethodName = 'crm.item.get';

    protected $listDataSelector = 'result.items';

    public function getEntityTypeIdUsedInFrom()
    {
        if (empty($this->entityTypeId)) {
            $this->entityTypeId = $this->modelClass::entityTypeId();
        }

        return $this->entityTypeId;
    }

    protected function prepairParams(){
        $this->getEntityTypeIdUsedInFrom();
        $data = [
            'entityTypeId' => $this->entityTypeId,
            'filter' => $this->where,
            'order' => $this->orderBy,
            'select' => $this->select,
            'start' => $this->offset,
        ];
        $this->params = $data;
    }

    protected function prepairOneParams(){
        $this->getEntityTypeIdUsedInFrom();
        $id = null;
        if(ArrayHelper::getValue($this->where, 'id')){
            $id = ArrayHelper::getValue($this->where, 'id');
        }
        if($this->link){

        }
        $data = [
            'entityTypeId' => $this->entityTypeId,
            'id' => $id
        ];
        $this->params = $data;
    }
}
