<?php

namespace wm\yii2-b24-ar;

use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\db\ActiveQueryInterface;
use yii\helpers\ArrayHelper;
use yii\db\ActiveRecordInterface;

//Код не универсален а направлен на смарт процессы стоит перенести в другой класс
class ActiveQuery extends Query implements ActiveQueryInterface
{
    const EVENT_INIT = 'init';

    /**
     * @var bool следует ли возвращать каждую запись в виде массива. Если false (по умолчанию), объект
     * of [[modelClass]] будет создан для представления каждой записи.
     */
    public $asArray;

    public $dataSelector = 'result';

    /**
     * @var string имя отношения, обратного этому отношению.
     * Например, у заказа есть клиент, что означает обратное отношение «клиент».
     * — это «заказы», а отношение, обратное отношению «заказы», — это «клиент».
     * Если это свойство установлено, на первичные записи будут ссылаться через указанное отношение.
     * Например, `$customer->orders[0]->customer` и `$customer` будут одним и тем же объектом,
     * и доступ к покупателю заказа не вызовет новый запрос к БД.
     * Это свойство используется только в реляционном контексте.
     */
    public $inverseOf;

    /**
     * @var array the columns of the primary and foreign tables that establish a relation.
     * The array keys must be columns of the table for this relation, and the array values
     * must be the corresponding columns from the primary table.
     * Do not prefix or quote the column names as this will be done automatically by Yii.
     * This property is only used in relational context.
     */
    public $link;

//    public $listDataSelector = 'result';

    /**
     * @var string the name of the ActiveRecord class.
     */
    public $modelClass;

    /**
     * @var bool whether this query represents a relation to more than one record.
     * This property is only used in relational context. If true, this relation will
     * populate all query results into AR instances using [[Query::all()|all()]].
     * If false, only the first row of the results will be retrieved using [[Query::one()|one()]].
     */
    public $multiple;

    public $params = [];

    /**
     * @var ActiveRecord the primary model of a relational query.
     * This is used only in lazy loading with dynamic query options.
     */
    public $primaryModel;

    /**
     * @var array|object the query associated with the junction table. Please call [[via()]]
     * to set this property instead of directly setting it.
     * This property is only used in relational context.
     * @see via()
     */
    public $via;

    /**
     * @var array a list of relations that this query should be performed with
     */
    public $with;

    protected $listMethodName;

    protected $oneMethodName;

    protected $listDataSelector = 'result';

    protected $oneDataSelector = 'result';

//    public $sql;
//    public $on;
//    public $joinWith;

    //public $params = [];

    //private $_method = '';

    //public $method = '';

    //private $_start = 0;

    //private $_limit = 0;

    public function __construct($modelClass, $config = [])
    {
        $this->modelClass = $modelClass;
        $this->select = $this->modelClass::attributes();
        //$this->listMethod = $modelClass::listMethod();
        parent::__construct($config);
    }

    /**
     * Clones internal objects.
     */
    public function __clone()
    {
        parent::__clone();
        // make a clone of "via" object so that the same query object can be reused multiple times
        if (is_object($this->via)) {
            $this->via = clone $this->via;
        } elseif (is_array($this->via)) {
            $this->via = [$this->via[0], clone $this->via[1], $this->via[2]];
        }
    }

    public function all($auth = null)
    {
        return parent::all($auth);
    }

    /**
     * Sets the [[asArray]] property.
     * @param bool $value whether to return the query results in terms of arrays instead of Active Records.
     * @return $this the query object itself
     */
    public function asArray($value = true)
    {
        $this->asArray = $value;
        return $this;
    }

    /**
     * Finds the related records for the specified primary record.
     * This method is invoked when a relation of an ActiveRecord is being accessed lazily.
     * @param string $name the relation name
     * @param ActiveRecordInterface|BaseActiveRecord $model the primary model
     * @return mixed the related record(s)
     * @throws InvalidArgumentException if the relation is invalid
     */
    public function findFor($name, $model)
    {
        if (method_exists($model, 'get' . $name)) {
            $method = new \ReflectionMethod($model, 'get' . $name);
            $realName = lcfirst(substr($method->getName(), 3));
            if ($realName !== $name) {
                throw new InvalidArgumentException('Relation names are case sensitive. ' . get_class($model) . " has a relation named \"$realName\" instead of \"$name\".");
            }
        }

        return $this->multiple ? $this->all() : $this->one();
    }

    /**
     * Finds records corresponding to one or multiple relations and populates them into the primary models.
     * @param array $with a list of relations that this query should be performed with. Please
     * refer to [[with()]] for details about specifying this parameter.
     * @param array|ActiveRecord[] $models the primary models (can be either AR instances or arrays)
     */
    public function findWith($with, &$models)
    {
        $primaryModel = reset($models);
        if (!$primaryModel instanceof ActiveRecordInterface) {
            /* @var $modelClass ActiveRecordInterface */
            $modelClass = $this->modelClass;
            $primaryModel = $modelClass::instance();
        }
        $relations = $this->normalizeRelations($primaryModel, $with);
        /* @var $relation ActiveQuery */
        foreach ($relations as $name => $relation) {
            if ($relation->asArray === null) {
                // inherit asArray from primary query
                $relation->asArray($this->asArray);
            }
            $relation->populateRelation($name, $models);
        }
    }

    public function getData($obB24)
    {
        $this->listDataSelector = $this->getListDataSelector();
        $request = $obB24->client->call($this->listMethodName, $this->params);
        return ArrayHelper::getValue($request, $this->listDataSelector);
    }

    public function getFullData($obB24)
    {
        $this->listDataSelector = $this->getListDataSelector();
        $request = $obB24->client->call($this->listMethodName, $this->params);
        $countCalls = (int)ceil($request['total'] / $obB24->client::MAX_BATCH_CALLS);
        $data = ArrayHelper::getValue($request, $this->listDataSelector);
        if (count($data) != $request['total']) {
            for ($i = 1; $i < $countCalls; $i++)
                $obB24->client->addBatchCall($this->listMethodName,
                    array_merge($this->params, ['start' => $obB24->client::MAX_BATCH_CALLS * $i]),
                    function ($result) use (&$data) {
                        $data = array_merge($data, ArrayHelper::getValue($result, $this->listDataSelector));
                    }
                );
            $obB24->client->processBatchCalls();
        }
        return $data; //Добавить вывод дополнительной информации
    }

    public function getListDataSelector()
    {
        if (method_exists($this->modelClass, 'listDataSelector')) {
            return call_user_func([$this->modelClass, 'listDataSelector']); //'result.items'
        } else {
            return $this->listDataSelector;
        }
    }

    public function init()
    {
        parent::init();
        $this->trigger(self::EVENT_INIT);
    }

    /**
     * Sets the name of the relation that is the inverse of this relation.
     * For example, a customer has orders, which means the inverse of the "orders" relation is the "customer".
     * If this property is set, the primary record(s) will be referenced through the specified relation.
     * For example, `$customer->orders[0]->customer` and `$customer` will be the same object,
     * and accessing the customer of an order will not trigger a new DB query.
     *
     * Use this method when declaring a relation in the [[ActiveRecord]] class, e.g. in Customer model:
     *
     * ```php
     * public function getOrders()
     * {
     *     return $this->hasMany(Order::class, ['customer_id' => 'id'])->inverseOf('customer');
     * }
     * ```
     *
     * This also may be used for Order model, but with caution:
     *
     * ```php
     * public function getCustomer()
     * {
     *     return $this->hasOne(Customer::class, ['id' => 'customer_id'])->inverseOf('orders');
     * }
     * ```
     *
     * in this case result will depend on how order(s) was loaded.
     * Let's suppose customer has several orders. If only one order was loaded:
     *
     * ```php
     * $orders = Order::find()->where(['id' => 1])->all();
     * $customerOrders = $orders[0]->customer->orders;
     * ```
     *
     * variable `$customerOrders` will contain only one order. If orders was loaded like this:
     *
     * ```php
     * $orders = Order::find()->with('customer')->where(['customer_id' => 1])->all();
     * $customerOrders = $orders[0]->customer->orders;
     * ```
     *
     * variable `$customerOrders` will contain all orders of the customer.
     *
     * @param string $relationName the name of the relation that is the inverse of this relation.
     * @return $this the relation object itself.
     */
    public function inverseOf($relationName)
    {
        $this->inverseOf = $relationName;
        return $this;
    }

    public function one($auth = null)
    {
        $row = parent::one($auth);
        if ($row !== false) {
            $models = $this->populate([$row]);
            return reset($models) ?: null;
        }

        return null;
    }

    public function populate($rows)
    {
        if (empty($rows)) {
            return [];
        }

        $models = $this->createModels($rows);
        if (!empty($this->join) && $this->indexBy === null) {
            $models = $this->removeDuplicatedModels($models);
        }
        if (!empty($this->with)) {
            $this->findWith($this->with, $models);
        }

        if ($this->inverseOf !== null) {
            $this->addInverseRelations($models);
        }

        if (!$this->asArray) {
            foreach ($models as $model) {
                $model->afterFind();
            }
        }

        return parent::populate($models);
    }

    /**
     * Находит связанные записи и заполняет их первичными моделями.
     * @param string $name имя отношения
     * @param array $primaryModels первичные модели
     * @return array соответствующие модели
     * @throws InvalidConfigException if [[link]] is invalid
     */
    public function populateRelation($name, &$primaryModels)
    {
        if (!is_array($this->link)) {
            throw new InvalidConfigException('Invalid link: it must be an array of key-value pairs.');
        }

        if ($this->via instanceof self) {
            // via junction table
//            $viaQuery = $this->via;
//            $viaModels = $viaQuery->findJunctionRows($primaryModels);
//            $this->filterByModels($viaModels);
        } elseif (is_array($this->via)) {
            // via relation
            list($viaName, $viaQuery) = $this->via;
            if ($viaQuery->asArray === null) {
                // inherit asArray from primary query
                $viaQuery->asArray($this->asArray);
            }
            $viaQuery->primaryModel = null;
            $viaModels = array_filter($viaQuery->populateRelation($viaName, $primaryModels));
            $this->filterByModels($viaModels);
        } else {
            $this->filterByModels($primaryModels);
        }

        if (!$this->multiple && count($primaryModels) === 1) {
            $model = $this->one();
            $primaryModel = reset($primaryModels);
            if ($primaryModel instanceof ActiveRecordInterface) {
                $primaryModel->populateRelation($name, $model);
            } else {
                $primaryModels[key($primaryModels)][$name] = $model;
            }
            if ($this->inverseOf !== null) {
                $this->populateInverseRelation($primaryModels, [$model], $name, $this->inverseOf);
            }

            return [$model];
        }

        $indexBy = $this->indexBy;
        $this->indexBy = null;
        $models = $this->all();

        if (isset($viaModels, $viaQuery)) {
            $buckets = $this->buildBuckets($models, $this->link, $viaModels, $viaQuery);
        } else {
            $buckets = $this->buildBuckets($models, $this->link);
        }

        $this->indexBy = $indexBy;
        if ($this->indexBy !== null && $this->multiple) {
            $buckets = $this->indexBuckets($buckets, $this->indexBy);
        }

        $link = array_values($this->link);
        if (isset($viaQuery)) {
            $deepViaQuery = $viaQuery;
            while ($deepViaQuery->via) {
                $deepViaQuery = is_array($deepViaQuery->via) ? $deepViaQuery->via[1] : $deepViaQuery->via;
            };
            $link = array_values($deepViaQuery->link);
        }
        foreach ($primaryModels as $i => $primaryModel) {
            $keys = null;
            if ($this->multiple && count($link) === 1) {
                $primaryModelKey = reset($link);
                $keys = isset($primaryModel[$primaryModelKey]) ? $primaryModel[$primaryModelKey] : null;
            }
            if (is_array($keys)) {
                $value = [];
                foreach ($keys as $key) {
                    $key = $this->normalizeModelKey($key);
                    if (isset($buckets[$key])) {
                        if ($this->indexBy !== null) {
                            // if indexBy is set, array_merge will cause renumbering of numeric array
                            foreach ($buckets[$key] as $bucketKey => $bucketValue) {
                                $value[$bucketKey] = $bucketValue;
                            }
                        } else {
                            $value = array_merge($value, $buckets[$key]);
                        }
                    }
                }
            } else {
                $key = $this->getModelKey($primaryModel, $link);
                $value = isset($buckets[$key]) ? $buckets[$key] : ($this->multiple ? [] : null);
            }
            if ($primaryModel instanceof ActiveRecordInterface) {
                $primaryModel->populateRelation($name, $value);
            } else {
                $primaryModels[$i][$name] = $value;
            }
        }
        if ($this->inverseOf !== null) {
            $this->populateInverseRelation($primaryModels, $models, $name, $this->inverseOf);
        }

        return $models;
    }

    /**
     * Specifies the relation associated with the junction table.
     *
     * Use this method to specify a pivot record/table when declaring a relation in the [[ActiveRecord]] class:
     *
     * ```php
     * class Order extends ActiveRecord
     * {
     *    public function getOrderItems() {
     *        return $this->hasMany(OrderItem::class, ['order_id' => 'id']);
     *    }
     *
     *    public function getItems() {
     *        return $this->hasMany(Item::class, ['id' => 'item_id'])
     *                    ->via('orderItems');
     *    }
     * }
     * ```
     *
     * @param string $relationName the relation name. This refers to a relation declared in [[primaryModel]].
     * @param callable $callable a PHP callback for customizing the relation associated with the junction table.
     * Its signature should be `function($query)`, where `$query` is the query to be customized.
     * @return $this the relation object itself.
     */
    public function via($relationName, callable $callable = null)
    {
        $relation = $this->primaryModel->getRelation($relationName);
        $callableUsed = $callable !== null;
        $this->via = [$relationName, $relation, $callableUsed];
        if ($callable !== null) {
            call_user_func($callable, $relation);
        }

        return $this;
    }

    /**
     * Задает отношения, с которыми должен выполняться этот запрос.
     *
     * Параметрами этого метода могут быть одна или несколько строк или один массив
     * имен отношений и необязательных обратных вызовов для настройки отношений.
     *
     * Имя отношения может ссылаться на отношение, определенное в [[modelClass]]
     * или подотношение, обозначающее отношение связанной записи.
     * Например, `orders.address` означает отношение `адрес`, определенное
     * в модельном классе, соответствующем отношению `orders`.
     *
     * Ниже приведены некоторые примеры использования:
     *
     * ```php
     * // find customers together with their orders and country
     * Customer::find()->with('orders', 'country')->all();
     * // find customers together with their orders and the orders' shipping address
     * Customer::find()->with('orders.address')->all();
     * // find customers together with their country and orders of status 1
     * Customer::find()->with([
     *     'orders' => function (\yii\db\ActiveQuery $query) {
     *         $query->andWhere('status = 1');
     *     },
     *     'country',
     * ])->all();
     * ```
     *
     * You can call `with()` multiple times. Each call will add relations to the existing ones.
     * For example, the following two statements are equivalent:
     *
     * ```php
     * Customer::find()->with('orders', 'country')->all();
     * Customer::find()->with('orders')->with('country')->all();
     * ```
     *
     * @return $this the query object itself
     */
    public function with()
    {
        $with = func_get_args();
        if (isset($with[0]) && is_array($with[0])) {
            // the parameter is given as an array
            $with = $with[0];
        }

        if (empty($this->with)) {
            $this->with = $with;
        } elseif (!empty($with)) {
            foreach ($with as $name => $value) {
                if (is_int($name)) {
                    // repeating relation is fine as normalizeRelations() handle it well
                    $this->with[] = $value;
                } else {
                    $this->with[$name] = $value;
                }
            }
        }
        return $this;
    }

    /**
     * Converts found rows into model instances.
     * @param array $rows
     * @return array|ActiveRecord[]
     * @since 2.0.11
     */
    protected function createModels($rows)
    {
        if ($this->asArray) {
            return $rows;
        } else {
            $models = [];
            /* @var $class ActiveRecord */
            $class = $this->modelClass;
            foreach ($rows as $row) {

                $model = $class::instantiate($row);
                //$model->load($row, '');
                $modelClass = get_class($model);
                $modelClass::populateRecord($model, $row);//
                $models[] = $model;
            }
            return $models;
        }
    }

    /**
     * Если применимо, заполните первичную модель запроса обратной связью связанных записей.
     * @param array $result массив связанных записей, сгенерированный [[populate()]]
     * @since 2.0.9
     */
    private function addInverseRelations(&$result)
    {
        if ($this->inverseOf === null) {
            return;
        }
        foreach ($result as $i => $relatedModel) {
            if ($relatedModel instanceof ActiveRecordInterface) {
                if (!isset($inverseRelation)) {
                    $inverseRelation = $relatedModel->getRelation($this->inverseOf);
                }
                $relatedModel->populateRelation($this->inverseOf, $inverseRelation->multiple ? [$this->primaryModel] : $this->primaryModel);
            } else {
                if (!isset($inverseRelation)) {
                    /* @var $modelClass ActiveRecordInterface */
                    $modelClass = $this->modelClass;
                    $inverseRelation = $modelClass::instance()->getRelation($this->inverseOf);
                }
                $result[$i][$this->inverseOf] = $inverseRelation->multiple ? [$this->primaryModel] : $this->primaryModel;
            }
        }
    }

    /**
     * @param array $models
     * @param array $link
     * @param array $viaModels
     * @param null|self $viaQuery
     * @param bool $checkMultiple
     * @return array
     */
    private function buildBuckets($models, $link, $viaModels = null, $viaQuery = null, $checkMultiple = true)
    {
        if ($viaModels !== null) {
            $map = [];
            $viaLink = $viaQuery->link;
            $viaLinkKeys = array_keys($viaLink);
            $linkValues = array_values($link);
            foreach ($viaModels as $viaModel) {
                $key1 = $this->getModelKey($viaModel, $viaLinkKeys);
                $key2 = $this->getModelKey($viaModel, $linkValues);
                $map[$key2][$key1] = true;
            }

            $viaQuery->viaMap = $map;

            $viaVia = $viaQuery->via;
            while ($viaVia) {
                $viaViaQuery = is_array($viaVia) ? $viaVia[1] : $viaVia;
                $map = $this->mapVia($map, $viaViaQuery->viaMap);

                $viaVia = $viaViaQuery->via;
            };
        }

        $buckets = [];
        $linkKeys = array_keys($link);

        if (isset($map)) {
            foreach ($models as $model) {
                $key = $this->getModelKey($model, $linkKeys);
                if (isset($map[$key])) {
                    foreach (array_keys($map[$key]) as $key2) {
                        $buckets[$key2][] = $model;
                    }
                }
            }
        } else {
            foreach ($models as $model) {
                $key = $this->getModelKey($model, $linkKeys);
                $buckets[$key][] = $model;
            }
        }

        if ($checkMultiple && !$this->multiple) {
            foreach ($buckets as $i => $bucket) {
                $buckets[$i] = reset($bucket);
            }
        }

        return $buckets;
    }

    /**
     * @param array $models
     */
    private function filterByModels($models)
    {
        $attributes = array_keys($this->link);

        $attributes = $this->prefixKeyColumns($attributes);

        $values = [];
        if (count($attributes) === 1) {
            // single key
            $attribute = reset($this->link);
            foreach ($models as $model) {
                $value = isset($model[$attribute]) ? $model[$attribute] : null;
                if ($value !== null) {
                    if (is_array($value)) {
                        $values = array_merge($values, $value);
                    } elseif ($value instanceof ArrayExpression && $value->getDimension() === 1) {
                        $values = array_merge($values, $value->getValue());
                    } else {
                        $values[] = $value;
                    }
                }
            }
            if (empty($values)) {
                $this->emulateExecution();
            }
        } else {
            // composite keys

            // ensure keys of $this->link are prefixed the same way as $attributes
            $prefixedLink = array_combine($attributes, $this->link);
            foreach ($models as $model) {
                $v = [];
                foreach ($prefixedLink as $attribute => $link) {
                    $v[$attribute] = $model[$link];
                }
                $values[] = $v;
                if (empty($v)) {
                    $this->emulateExecution();
                }
            }
        }

        if (!empty($values)) {
            $scalarValues = [];
            $nonScalarValues = [];
            foreach ($values as $value) {
                if (is_scalar($value)) {
                    $scalarValues[] = $value;
                } else {
                    $nonScalarValues[] = $value;
                }
            }

            $scalarValues = array_unique($scalarValues);
            $values = array_merge($scalarValues, $nonScalarValues);
        }

        $this->andWhere(['in', $attributes, $values]);
    }

    /**
     * @param array $primaryModels либо массив экземпляров AR, либо массивы
     * @return array
     */
    private function findJunctionRows($primaryModels)
    {
        if (empty($primaryModels)) {
            return [];
        }
        $this->filterByModels($primaryModels);
        /* @var $primaryModel ActiveRecord */
        $primaryModel = reset($primaryModels);
        if (!$primaryModel instanceof ActiveRecordInterface) {
            // when primaryModels are array of arrays (asArray case)
            $primaryModel = $this->modelClass;
        }

        return $this->asArray()->all($primaryModel::getDb());
    }

    /**
     * @param ActiveRecordInterface|array $model
     * @param array $attributes
     * @return string|false
     */
    private function getModelKey($model, $attributes)
    {
        $key = [];
        foreach ($attributes as $attribute) {
            if (isset($model[$attribute])) {
                $key[] = $this->normalizeModelKey($model[$attribute]);
            }
        }
        if (count($key) > 1) {
            return serialize($key);
        }
        return reset($key);
    }

    /**
     * Индексирует сегменты по имени столбца.
     *
     * @param array $buckets
     * @param string|callable $indexBy имя столбца, по которому должны быть проиндексированы результаты запроса.
     * Это также может быть вызываемая функция (например, анонимная функция),
     * которая возвращает значение индекса на основе заданных данных строки.
     * @return array
     */
    private function indexBuckets($buckets, $indexBy)
    {
        $result = [];
        foreach ($buckets as $key => $models) {
            $result[$key] = [];
            foreach ($models as $model) {
                $index = is_string($indexBy) ? $model[$indexBy] : call_user_func($indexBy, $model);
                $result[$key][$index] = $model;
            }
        }

        return $result;
    }

    /**
     * @param array $map
     * @param array $viaMap
     * @return array
     */
    private function mapVia($map, $viaMap)
    {
        $resultMap = [];
        foreach ($map as $key => $linkKeys) {
            foreach (array_keys($linkKeys) as $linkKey) {
                $resultMap[$key] = $viaMap[$linkKey];
            }
        }
        return $resultMap;
    }

    /**
     * @param mixed $value raw key value. Since 2.0.40 non-string values must be convertible to string (like special
     * objects for cross-DBMS relations, for example: `|MongoId`).
     * @return string normalized key value.
     */
    private function normalizeModelKey($value)
    {
        try {
            return (string)$value;
        } catch (\Exception $e) {
            throw new InvalidConfigException('Value must be convertable to string.');
        } catch (\Throwable $e) {
            throw new InvalidConfigException('Value must be convertable to string.');
        }
    }

    /**
     * @param ActiveRecord $model
     * @param array $with
     * @return ActiveQueryInterface[]
     */
    private function normalizeRelations($model, $with)
    {
        $relations = [];
        foreach ($with as $name => $callback) {
            if (is_int($name)) {
                $name = $callback;
                $callback = null;
            }
            if (($pos = strpos($name, '.')) !== false) {
                // with sub-relations
                $childName = substr($name, $pos + 1);
                $name = substr($name, 0, $pos);
            } else {
                $childName = null;
            }

            if (!isset($relations[$name])) {
                $relation = $model->getRelation($name);
                $relation->primaryModel = null;
                $relations[$name] = $relation;
            } else {
                $relation = $relations[$name];
            }

            if (isset($childName)) {
                $relation->with[$childName] = $callback;
            } elseif ($callback !== null) {
                call_user_func($callback, $relation);
            }
        }

        return $relations;
    }

    /**
     * @param ActiveRecordInterface[] $primaryModels первичные модели
     * @param ActiveRecordInterface[] $models модели
     * @param string $primaryName имя основного отношения
     * @param string $name имя отношения
     */
    private function populateInverseRelation(&$primaryModels, $models, $primaryName, $name)
    {
        if (empty($models) || empty($primaryModels)) {
            return;
        }
        $model = reset($models);
        /* @var $relation ActiveQueryInterface|ActiveQuery */
        if ($model instanceof ActiveRecordInterface) {
            $relation = $model->getRelation($name);
        } else {
            /* @var $modelClass ActiveRecordInterface */
            $modelClass = $this->modelClass;
            $relation = $modelClass::instance()->getRelation($name);
        }

        if ($relation->multiple) {
            $buckets = $this->buildBuckets($primaryModels, $relation->link, null, null, false);
            if ($model instanceof ActiveRecordInterface) {
                foreach ($models as $model) {
                    $key = $this->getModelKey($model, $relation->link);
                    $model->populateRelation($name, isset($buckets[$key]) ? $buckets[$key] : []);
                }
            } else {
                foreach ($primaryModels as $i => $primaryModel) {
                    if ($this->multiple) {
                        foreach ($primaryModel as $j => $m) {
                            $key = $this->getModelKey($m, $relation->link);
                            $primaryModels[$i][$j][$name] = isset($buckets[$key]) ? $buckets[$key] : [];
                        }
                    } elseif (!empty($primaryModel[$primaryName])) {
                        $key = $this->getModelKey($primaryModel[$primaryName], $relation->link);
                        $primaryModels[$i][$primaryName][$name] = isset($buckets[$key]) ? $buckets[$key] : [];
                    }
                }
            }
        } elseif ($this->multiple) {
            foreach ($primaryModels as $i => $primaryModel) {
                foreach ($primaryModel[$primaryName] as $j => $m) {
                    if ($m instanceof ActiveRecordInterface) {
                        $m->populateRelation($name, $primaryModel);
                    } else {
                        $primaryModels[$i][$primaryName][$j][$name] = $primaryModel;
                    }
                }
            }
        } else {
            foreach ($primaryModels as $i => $primaryModel) {
                if ($primaryModels[$i][$primaryName] instanceof ActiveRecordInterface) {
                    $primaryModels[$i][$primaryName]->populateRelation($name, $primaryModel);
                } elseif (!empty($primaryModels[$i][$primaryName])) {
                    $primaryModels[$i][$primaryName][$name] = $primaryModel;
                }
            }
        }
    }

    /**
     * @param array $attributes атрибуты для префикса
     * @return array
     */
    private function prefixKeyColumns($attributes)
    {
        if ($this instanceof ActiveQuery && (!empty($this->join) || !empty($this->joinWith))) {
            if (empty($this->from)) {
                /* @var $modelClass ActiveRecord */
                $modelClass = $this->modelClass;
                $alias = $modelClass::tableName();
            } else {
                foreach ($this->from as $alias => $table) {
                    if (!is_string($alias)) {
                        $alias = $table;
                    }
                    break;
                }
            }
            if (isset($alias)) {
                foreach ($attributes as $i => $attribute) {
                    $attributes[$i] = "$alias.$attribute";
                }
            }
        }

        return $attributes;
    }

    public function andFilterCompare($name, $value, $defaultOperator = '=') {
        //$filter = [];
        //убираем '[' и ']' в начале и в конце строки в запросе
        if ((substr($value, 0, 1) == '[') && (substr($value, -1, 1) == ']')) {
            $data = substr($value, 1, -1);
            $arr = explode(',', $data);
            foreach ($arr as $var) {
                $this->andFilterCompare($name, $var);
            }
            return $this;
        } else {
            if (preg_match('/^(>=|>|<=|<|=)/', $value, $matches)) {
                $operator = $matches[1];
                $value = substr($value, strlen($operator));
            }
            elseif (preg_match('/^(<>)/', $value, $matches)) {
                $operator = '!=';
                $value = substr($value, strlen($operator));
            }
//            elseif ($str == 'isNull') {
//                return $this->andWhere([$name => null]);
//            } elseif (preg_match('/^(%%)/', $str, $matches)) {
//                $operator = $matches[1];
//                $value = substr($str, strlen($operator));
//                $operator = 'like';
//            } elseif (preg_match('/^(in\[.*\])/', $str, $matches)) {
//                $operator = 'in';
//                $value = explode(',', mb_substr($str, 3, -1));
//            }
            else {
                $operator = $defaultOperator;
            }
//            $c = $operator.$name." ".$value;
            $this->andFilterWhere([$operator, $name, $value]);
            return $this;

        }
    }

//    public function andFilterWhere($params){
//        if($params[2]){
//            $this->filter[$params[0].$params[1]] = $params[2];
//        }
//
//        return $this;
//
//    }

    private function removeDuplicatedModels($models)
    {
        $hash = [];
        /* @var $class ActiveRecord */
        $class = $this->modelClass;
        $pks = $class::primaryKey();

        if (count($pks) > 1) {
            // composite primary key
            foreach ($models as $i => $model) {
                $key = [];
                foreach ($pks as $pk) {
                    if (!isset($model[$pk])) {
                        // do not continue if the primary key is not part of the result set
                        break 2;
                    }
                    $key[] = $model[$pk];
                }
                $key = serialize($key);
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } else {
                    $hash[$key] = true;
                }
            }
        } elseif (empty($pks)) {
            throw new InvalidConfigException("Primary key of '{$class}' can not be empty.");
        } else {
            // single column primary key
            $pk = reset($pks);
            foreach ($models as $i => $model) {
                if (!isset($model[$pk])) {
                    // do not continue if the primary key is not part of the result set
                    break;
                }
                $key = $model[$pk];
                if (isset($hash[$key])) {
                    unset($models[$i]);
                } elseif ($key !== null) {
                    $hash[$key] = true;
                }
            }
        }

        return array_values($models);
    }


// Исключенные функции
//    public function createCommand($db = null)
//    {
//        /* @var $modelClass ActiveRecord */
//        $modelClass = $this->modelClass;
//        if ($db === null) {
//            $db = $modelClass::getDb();
//        }
//
//        if ($this->sql === null) {
//            list($sql, $params) = $db->getQueryBuilder()->build($this);
//        } else {
//            $sql = $this->sql;
//            $params = $this->params;
//        }
//
//        $command = $db->createCommand($sql, $params);
//        $this->setCommandCache($command);
//
//        return $command;
//    }
//
//    protected function queryScalar($selectExpression, $db)
//    {
//        /* @var $modelClass ActiveRecord */
//        $modelClass = $this->modelClass;
//        if ($db === null) {
//            $db = $modelClass::getDb();
//        }
//
//        if ($this->sql === null) {
//            return parent::queryScalar($selectExpression, $db);
//        }
//
//        $command = (new Query())->select([$selectExpression])
//            ->from(['c' => "({$this->sql})"])
//            ->params($this->params)
//            ->createCommand($db);
//        $this->setCommandCache($command);
//
//        return $command->queryScalar();
//    }
//
//    public function joinWith($with, $eagerLoading = true, $joinType = 'LEFT JOIN')
//    {
//        $relations = [];
//        foreach ((array) $with as $name => $callback) {
//            if (is_int($name)) {
//                $name = $callback;
//                $callback = null;
//            }
//
//            if (preg_match('/^(.*?)(?:\s+AS\s+|\s+)(\w+)$/i', $name, $matches)) {
//                // relation is defined with an alias, adjust callback to apply alias
//                list(, $relation, $alias) = $matches;
//                $name = $relation;
//                $callback = function ($query) use ($callback, $alias) {
//                    /* @var $query ActiveQuery */
//                    $query->alias($alias);
//                    if ($callback !== null) {
//                        call_user_func($callback, $query);
//                    }
//                };
//            }
//
//            if ($callback === null) {
//                $relations[] = $name;
//            } else {
//                $relations[$name] = $callback;
//            }
//        }
//        $this->joinWith[] = [$relations, $eagerLoading, $joinType];
//        return $this;
//    }
//
//    public function innerJoinWith($with, $eagerLoading = true)
//    {
//        return $this->joinWith($with, $eagerLoading, 'INNER JOIN');
//    }
//
//    private function joinWithRelations($model, $with, $joinType)
//    {
//        $relations = [];
//
//        foreach ($with as $name => $callback) {
//            if (is_int($name)) {
//                $name = $callback;
//                $callback = null;
//            }
//
//            $primaryModel = $model;
//            $parent = $this;
//            $prefix = '';
//            while (($pos = strpos($name, '.')) !== false) {
//                $childName = substr($name, $pos + 1);
//                $name = substr($name, 0, $pos);
//                $fullName = $prefix === '' ? $name : "$prefix.$name";
//                if (!isset($relations[$fullName])) {
//                    $relations[$fullName] = $relation = $primaryModel->getRelation($name);
//                    $this->joinWithRelation($parent, $relation, $this->getJoinType($joinType, $fullName));
//                } else {
//                    $relation = $relations[$fullName];
//                }
//                /* @var $relationModelClass ActiveRecordInterface */
//                $relationModelClass = $relation->modelClass;
//                $primaryModel = $relationModelClass::instance();
//                $parent = $relation;
//                $prefix = $fullName;
//                $name = $childName;
//            }
//
//            $fullName = $prefix === '' ? $name : "$prefix.$name";
//            if (!isset($relations[$fullName])) {
//                $relations[$fullName] = $relation = $primaryModel->getRelation($name);
//                if ($callback !== null) {
//                    call_user_func($callback, $relation);
//                }
//                if (!empty($relation->joinWith)) {
//                    $relation->buildJoinWith();
//                }
//                $this->joinWithRelation($parent, $relation, $this->getJoinType($joinType, $fullName));
//            }
//        }
//    }
//
//    private function getJoinType($joinType, $name)
//    {
//        if (is_array($joinType) && isset($joinType[$name])) {
//            return $joinType[$name];
//        }
//
//        return is_string($joinType) ? $joinType : 'INNER JOIN';
//    }
//
//    protected function getTableNameAndAlias()
//    {
//        if (empty($this->from)) {
//            $tableName = $this->getPrimaryTableName();
//        } else {
//            $tableName = '';
//            // if the first entry in "from" is an alias-tablename-pair return it directly
//            foreach ($this->from as $alias => $tableName) {
//                if (is_string($alias)) {
//                    return [$tableName, $alias];
//                }
//                break;
//            }
//        }
//
//        if (preg_match('/^(.*?)\s+({{\w+}}|\w+)$/', $tableName, $matches)) {
//            $alias = $matches[2];
//        } else {
//            $alias = $tableName;
//        }
//
//        return [$tableName, $alias];
//    }
//
//    private function joinWithRelation($parent, $child, $joinType)
//    {
//        $via = $child->via;
//        $child->via = null;
//        if ($via instanceof self) {
//            // via table
//            $this->joinWithRelation($parent, $via, $joinType);
//            $this->joinWithRelation($via, $child, $joinType);
//            return;
//        } elseif (is_array($via)) {
//            // via relation
//            $this->joinWithRelation($parent, $via[1], $joinType);
//            $this->joinWithRelation($via[1], $child, $joinType);
//            return;
//        }
//
//        list($parentTable, $parentAlias) = $parent->getTableNameAndAlias();
//        list($childTable, $childAlias) = $child->getTableNameAndAlias();
//
//        if (!empty($child->link)) {
//            if (strpos($parentAlias, '{{') === false) {
//                $parentAlias = '{{' . $parentAlias . '}}';
//            }
//            if (strpos($childAlias, '{{') === false) {
//                $childAlias = '{{' . $childAlias . '}}';
//            }
//
//            $on = [];
//            foreach ($child->link as $childColumn => $parentColumn) {
//                $on[] = "$parentAlias.[[$parentColumn]] = $childAlias.[[$childColumn]]";
//            }
//            $on = implode(' AND ', $on);
//            if (!empty($child->on)) {
//                $on = ['and', $on, $child->on];
//            }
//        } else {
//            $on = $child->on;
//        }
//        $this->join($joinType, empty($child->from) ? $childTable : $child->from, $on);
//
//        if (!empty($child->where)) {
//            $this->andWhere($child->where);
//        }
//        if (!empty($child->having)) {
//            $this->andHaving($child->having);
//        }
//        if (!empty($child->orderBy)) {
//            $this->addOrderBy($child->orderBy);
//        }
//        if (!empty($child->groupBy)) {
//            $this->addGroupBy($child->groupBy);
//        }
//        if (!empty($child->params)) {
//            $this->addParams($child->params);
//        }
//        if (!empty($child->join)) {
//            foreach ($child->join as $join) {
//                $this->join[] = $join;
//            }
//        }
//        if (!empty($child->union)) {
//            foreach ($child->union as $union) {
//                $this->union[] = $union;
//            }
//        }
//    }
//
//    public function onCondition($condition, $params = [])
//    {
//        $this->on = $condition;
//        $this->addParams($params);
//        return $this;
//    }
//
//    public function andOnCondition($condition, $params = [])
//    {
//        if ($this->on === null) {
//            $this->on = $condition;
//        } else {
//            $this->on = ['and', $this->on, $condition];
//        }
//        $this->addParams($params);
//        return $this;
//    }
//
//    public function orOnCondition($condition, $params = [])
//    {
//        if ($this->on === null) {
//            $this->on = $condition;
//        } else {
//            $this->on = ['or', $this->on, $condition];
//        }
//        $this->addParams($params);
//        return $this;
//    }
//
//    public function viaTable($tableName, $link, callable $callable = null)
//    {
//        $modelClass = $this->primaryModel ? get_class($this->primaryModel) : $this->modelClass;
//        $relation = new self($modelClass, [
//            'from' => [$tableName],
//            'link' => $link,
//            'multiple' => true,
//            'asArray' => true,
//        ]);
//        $this->via = $relation;
//        if ($callable !== null) {
//            call_user_func($callable, $relation);
//        }
//
//        return $this;
//    }
//
//    public function alias($alias)
//    {
//        if (empty($this->from) || count($this->from) < 2) {
//            list($tableName) = $this->getTableNameAndAlias();
//            $this->from = [$alias => $tableName];
//        } else {
//            $tableName = $this->getPrimaryTableName();
//
//            foreach ($this->from as $key => $table) {
//                if ($table === $tableName) {
//                    unset($this->from[$key]);
//                    $this->from[$alias] = $tableName;
//                }
//            }
//        }
//
//        return $this;
//    }
//
//    public function getTablesUsedInFrom()
//    {
//        if (empty($this->from)) {
//            return $this->cleanUpTableNames([$this->getPrimaryTableName()]);
//        }
//
//        return parent::getTablesUsedInFrom();
//    }
//
//    protected function getPrimaryTableName()
//    {
//        /* @var $modelClass ActiveRecord */
//        $modelClass = $this->modelClass;
//        return $modelClass::tableName();
//    }
}
