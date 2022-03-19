<?php

namespace wm\yii2-b24-ar;

use Bitrix24\B24Object;
use wm\yii2-b24-artools\b24Tools;
use Yii;
use yii\base\Component;
use yii\base\NotSupportedException;
use yii\helpers\ArrayHelper;
use yii\db\QueryInterface;

//Код не универсален а направлен на смарт процессы стоит перенести в другой класс
class Query extends Component implements QueryInterface {

//    public $selectOption;
//    public $distinct;
//    public $from;
//    public $groupBy;
//    public $join;
//    public $having;
//    public $union;
//    public $withQueries;
//    public $queryCacheDuration;
//    public $queryCacheDependency;
	protected $oneDataSelectorName;

    public $select;
    /**
     * Список массивов @var значений параметров запроса, проиндексированных заполнителями параметров.
     * Например, `[':name' => 'Дэн', ':age' => 31]`.
     */
    public $params = [];

    /**
     * @var string|array|ExpressionInterface|пустое условие запроса. Это относится к предложению WHERE в операторе SQL.
     * Например, `['возраст' => 31, 'команда' => 1]`.
     * @see where() для корректного синтаксиса при указании этого значения.
     */
    public $where;
    /**
     * @var int|ExpressionInterface|null maximum number of records to be returned. May be an instance of [[ExpressionInterface]].
     * If not set or less than 0, it means no limit.
     */
    public $limit;
    /**
     * @var int|ExpressionInterface|null zero-based offset from where the records are to be returned.
     * May be an instance of [[ExpressionInterface]]. If not set or less than 0, it means starting from the beginning.
     */
    public $offset;
    /**
     * @var array|null how to sort the query results. This is used to construct the ORDER BY clause in a SQL statement.
     * The array keys are the columns to be sorted by, and the array values are the corresponding sort directions which
     * can be either [SORT_ASC](https://www.php.net/manual/en/array.constants.php#constant.sort-asc)
     * or [SORT_DESC](https://www.php.net/manual/en/array.constants.php#constant.sort-desc).
     * The array may also contain [[ExpressionInterface]] objects. If that is the case, the expressions
     * will be converted into strings without any change.
     */
    public $orderBy;
    /**
     * @var string|callable|null the name of the column by which the query results should be indexed by.
     * This can also be a callable (e.g. anonymous function) that returns the index value based on the given
     * row data. For more details, see [[indexBy()]]. This property is only used by [[QueryInterface::all()|all()]].
     */
    public $indexBy;
    /**
     * @var bool whether to emulate the actual query execution, returning empty or false results.
     * @see emulateExecution()
     * @since 2.0.11
     */
    public $emulateExecution = false;

    const EVENT_INIT = 'init';

    public function init()
    {
        parent::init();
        $this->trigger(self::EVENT_INIT);
    }

    public $method = '';



    public function all($auth = null){
        if ($this->emulateExecution) {
            return [];
        }
        $this->prepairParams();
        //TODO вынести часть логики
        $component = new b24Tools();
        $b24App = null;// $component->connectFromUser($auth);
        if($auth === null){
            $b24App = $component->connectFromAdmin();
        }else{
            $b24App = $component->connectFromUser($auth);
        }
        $obB24 = new B24Object($b24App);
        $rows = [];
        //TODO Исправить
        if(!$this->limit){
            //TODO передавать в функцию limit и ofset
            $rows = $this->getFullData($obB24);
        }else{
            $rows = $this->getData($obB24);
        }
        //TODO Нужно ли здесь делать populate
        return $this->populate($rows);
    }

    public function count($q = '*', $auth = null){
        if ($this->emulateExecution) {
            return [];
        }
        $this->prepairParams();
        //TODO вынести часть логики
        $component = new b24Tools();
        $b24App = null;// $component->connectFromUser($auth);
        if($auth === null){
            $b24App = $component->connectFromAdmin();
        }else{
            $b24App = $component->connectFromUser($auth);
        }
        $obB24 = new B24Object($b24App);
        $request = $obB24->client->call($this->listMethodName, $this->params);
        return (int)ArrayHelper::getValue($request, 'total');
    }

    public function populate($rows)
    {
        //$result = $rows;
        if ($this->indexBy === null) {
            return $rows;
        }
        $result = [];
        foreach ($rows as $row) {
            $result[ArrayHelper::getValue($row, $this->indexBy)] = $row;

        }

        return $result;
    }

    public function andFilterCompare($name, $value, $defaultOperator = '=')
    {
        if (preg_match('/^(<>|>=|>|<=|<|=)/', (string)$value, $matches)) {
            $operator = $matches[1];
            $value = substr($value, strlen($operator));
        } else {
            $operator = $defaultOperator;
        }

        return $this->andFilterWhere([$operator, $name, $value]);
    }


    protected function prepairParams(){
        //$this->getEntityTypeIdUsedInFrom();/
        $data = [
            //'entityTypeId' => $this->entityTypeId,
            'filter' => $this->where,
            'order' => $this->orderBy

//            Остальные параметры
        ];
        $this->params = $data;
    }

    protected function prepairOneParams(){
        //$this->getEntityTypeIdUsedInFrom();/
        $data = [
        ];
        $this->params = $data;
    }

    public function __toString()
    {
        return serialize($this);
    }



    public function addParams($params)
    {
        //TODO Проверить
        if (!empty($params)) {
            if (empty($this->params)) {
                $this->params = $params;
            } else {
                foreach ($params as $name => $value) {
                    if (is_int($name)) {
                        $this->params[] = $value;
                    } else {
                        $this->params[$name] = $value;
                    }
                }
            }
        }

        return $this;
    }

    public function where($condition, $params = [])
    {
        $this->where = $this->conditionPrepare($condition);
        $this->addParams($params);
        return $this;
    }

    public function exists($db = null)
    {
        //TODO Переписать
        if ($this->emulateExecution) {
            return false;
        }
        $command = $this->createCommand($db);
        $params = $command->params;
        $command->setSql($command->db->getQueryBuilder()->selectExists($command->getSql()));
        $command->bindValues($params);
        return (bool) $command->queryScalar();
    }

//    public function count($q = '*', $db = null)
//    {
//        //TODO Переписать
//        if ($this->emulateExecution) {
//            return 0;
//        }
//
//        return $this->queryScalar("COUNT($q)", $db);
//    }

    public function one($auth = null)
    {
        if ($this->emulateExecution) {
            return false;
        }

        $this->prepairOneParams();

        $component = new b24Tools();
        $b24App = null;// $component->connectFromUser($auth);
        if($auth === null){
            $b24App = $component->connectFromAdmin();
        }else{
            $b24App = $component->connectFromUser($auth);
        }
        $obB24 = new B24Object($b24App);

        $this->method = $this->oneMethodName;
        $data = $obB24->client->call($this->method, $this->params);
        $row = ArrayHelper::getValue($data, $this->oneDataSelectorName);
        return $row;
    }

    /**
     * Sets the [[indexBy]] property.
     * @param string|callable $column the name of the column by which the query results should be indexed by.
     * This can also be a callable (e.g. anonymous function) that returns the index value based on the given
     * row data. The signature of the callable should be:
     *
     * ```php
     * function ($row)
     * {
     *     // return the index value corresponding to $row
     * }
     * ```
     *
     * @return $this the query object itself
     */
    public function indexBy($column)
    {
        $this->indexBy = $column;
        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the 'AND' operator.
     * @param string|array|ExpressionInterface $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @return $this the query object itself
     * @see where()
     * @see orWhere()
     */
    public function andWhere($condition)
    {
        $condition = $this->conditionPrepare($condition);
        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where = array_merge($this->where, $condition);
        }
        return $this;
    }

    public function conditionPrepare($condition){
        if(array_key_exists(0, $condition)){
            if(count($condition)==3){
                $arr = [];
                $operator = array_shift($condition);
                $arr[$operator.$condition[0]] = $condition[1];
                return $arr;
            }
            return [];
        }else{
            return $condition;
        }

    }

    /**
     * Adds an additional WHERE condition to the existing one.
     * The new condition and the existing one will be joined using the 'OR' operator.
     * @param string|array|ExpressionInterface $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @return $this the query object itself
     * @see where()
     * @see andWhere()
     */
    public function orWhere($condition)
    {
        if ($this->where === null) {
            $this->where = $condition;
        } else {
            $this->where = ['or', $this->where, $condition];
        }

        return $this;
    }

    /**
     * Sets the WHERE part of the query but ignores [[isEmpty()|empty operands]].
     *
     * This method is similar to [[where()]]. The main difference is that this method will
     * remove [[isEmpty()|empty query operands]]. As a result, this method is best suited
     * for building query conditions based on filter values entered by users.
     *
     * The following code shows the difference between this method and [[where()]]:
     *
     * ```php
     * // WHERE `age`=:age
     * $query->filterWhere(['name' => null, 'age' => 20]);
     * // WHERE `age`=:age
     * $query->where(['age' => 20]);
     * // WHERE `name` IS NULL AND `age`=:age
     * $query->where(['name' => null, 'age' => 20]);
     * ```
     *
     * Note that unlike [[where()]], you cannot pass binding parameters to this method.
     *
     * @param array $condition the conditions that should be put in the WHERE part.
     * See [[where()]] on how to specify this parameter.
     * @return $this the query object itself
     * @see where()
     * @see andFilterWhere()
     * @see orFilterWhere()
     */
    public function filterWhere(array $condition)
    {
        $condition = $this->filterCondition($condition);
        if ($condition !== []) {
            $this->where($condition);
        }

        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one but ignores [[isEmpty()|empty operands]].
     * The new condition and the existing one will be joined using the 'AND' operator.
     *
     * This method is similar to [[andWhere()]]. The main difference is that this method will
     * remove [[isEmpty()|empty query operands]]. As a result, this method is best suited
     * for building query conditions based on filter values entered by users.
     *
     * @param array $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @return $this the query object itself
     * @see filterWhere()
     * @see orFilterWhere()
     */
    public function andFilterWhere(array $condition)
    {
        $condition = $this->filterCondition($condition);
        if ($condition !== []) {
            $this->andWhere($condition);
        }

        return $this;
    }

    /**
     * Adds an additional WHERE condition to the existing one but ignores [[isEmpty()|empty operands]].
     * The new condition and the existing one will be joined using the 'OR' operator.
     *
     * This method is similar to [[orWhere()]]. The main difference is that this method will
     * remove [[isEmpty()|empty query operands]]. As a result, this method is best suited
     * for building query conditions based on filter values entered by users.
     *
     * @param array $condition the new WHERE condition. Please refer to [[where()]]
     * on how to specify this parameter.
     * @return $this the query object itself
     * @see filterWhere()
     * @see andFilterWhere()
     */
    public function orFilterWhere(array $condition)
    {
        $condition = $this->filterCondition($condition);
        if ($condition !== []) {
            $this->orWhere($condition);
        }

        return $this;
    }

    /**
     * Removes [[isEmpty()|empty operands]] from the given query condition.
     *
     * @param array $condition the original condition
     * @return array the condition with [[isEmpty()|empty operands]] removed.
     * @throws NotSupportedException if the condition operator is not supported
     */
    protected function filterCondition($condition)
    {
        if (!is_array($condition)) {
            return $condition;
        }

        if (!isset($condition[0])) {
            // hash format: 'column1' => 'value1', 'column2' => 'value2', ...
            foreach ($condition as $name => $value) {
                if ($this->isEmpty($value)) {
                    unset($condition[$name]);
                }
            }

            return $condition;
        }

        // operator format: operator, operand 1, operand 2, ...

        $operator = array_shift($condition);

        switch (strtoupper($operator)) {
            case 'NOT':
            case 'AND':
            case 'OR':
                foreach ($condition as $i => $operand) {
                    $subCondition = $this->filterCondition($operand);
                    if ($this->isEmpty($subCondition)) {
                        unset($condition[$i]);
                    } else {
                        $condition[$i] = $subCondition;
                    }
                }

                if (empty($condition)) {
                    return [];
                }
                break;
            case 'BETWEEN':
            case 'NOT BETWEEN':
                if (array_key_exists(1, $condition) && array_key_exists(2, $condition)) {
                    if ($this->isEmpty($condition[1]) || $this->isEmpty($condition[2])) {
                        return [];
                    }
                }
                break;
            default:
                if (array_key_exists(1, $condition) && $this->isEmpty($condition[1])) {
                    return [];
                }
        }

        array_unshift($condition, $operator);

        return $condition;
    }

    /**
     * Returns a value indicating whether the give value is "empty".
     *
     * The value is considered "empty", if one of the following conditions is satisfied:
     *
     * - it is `null`,
     * - an empty string (`''`),
     * - a string containing only whitespace characters,
     * - or an empty array.
     *
     * @param mixed $value
     * @return bool if the value is empty
     */
    protected function isEmpty($value)
    {
        return $value === '' || $value === [] || $value === null || is_string($value) && trim($value) === '';
    }

    /**
     * Sets the ORDER BY part of the query.
     * @param string|array|ExpressionInterface $columns the columns (and the directions) to be ordered by.
     * Columns can be specified in either a string (e.g. `"id ASC, name DESC"`) or an array
     * (e.g. `['id' => SORT_ASC, 'name' => SORT_DESC]`).
     *
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression).
     *
     * Note that if your order-by is an expression containing commas, you should always use an array
     * to represent the order-by information. Otherwise, the method will not be able to correctly determine
     * the order-by columns.
     *
     * Since version 2.0.7, an [[ExpressionInterface]] object can be passed to specify the ORDER BY part explicitly in plain SQL.
     * @return $this the query object itself
     * @see addOrderBy()
     */
    public function orderBy($columns)
    {
        $this->orderBy = $this->normalizeOrderBy($columns);
        return $this;
    }

    /**
     * Adds additional ORDER BY columns to the query.
     * @param string|array|ExpressionInterface $columns the columns (and the directions) to be ordered by.
     * Columns can be specified in either a string (e.g. "id ASC, name DESC") or an array
     * (e.g. `['id' => SORT_ASC, 'name' => SORT_DESC]`).
     *
     * The method will automatically quote the column names unless a column contains some parenthesis
     * (which means the column contains a DB expression).
     *
     * Note that if your order-by is an expression containing commas, you should always use an array
     * to represent the order-by information. Otherwise, the method will not be able to correctly determine
     * the order-by columns.
     *
     * Since version 2.0.7, an [[ExpressionInterface]] object can be passed to specify the ORDER BY part explicitly in plain SQL.
     * @return $this the query object itself
     * @see orderBy()
     */
    public function addOrderBy($columns)//['id'=>4]
    {
        $columns = $this->normalizeOrderBy($columns);
        foreach ($columns as $key=>$value){
            $temp = [];
            if($value == SORT_ASC){
                $temp[$key] = 'ASC';
            }elseif($value == SORT_DESC){
                $temp[$key] = 'DESC';
            }
            $columns = array_merge($columns, $temp);
        }
        if ($this->orderBy === null) {
            $this->orderBy = $columns;
        } else {
            $this->orderBy = array_merge($this->orderBy, $columns);
        }

        return $this;
    }

    /**
     * Normalizes format of ORDER BY data.
     *
     * @param array|string|ExpressionInterface $columns the columns value to normalize. See [[orderBy]] and [[addOrderBy]].
     * @return array
     */
    protected function normalizeOrderBy($columns)
    {
        if ($columns instanceof ExpressionInterface) {
            return [$columns];
        } elseif (is_array($columns)) {
            return $columns;
        }

        $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        foreach ($columns as $column) {
            if (preg_match('/^(.*?)\s+(asc|desc)$/i', $column, $matches)) {
                $result[$matches[1]] = strcasecmp($matches[2], 'desc') ? SORT_ASC : SORT_DESC;
            } else {
                $result[$column] = SORT_ASC;
            }
        }

        return $result;
    }

    /**
     * Sets the LIMIT part of the query.
     * @param int|ExpressionInterface|null $limit the limit. Use null or negative value to disable limit.
     * @return $this the query object itself
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Устанавливает часть запроса OFFSET.
     * @param int|ExpressionInterface|null $offset смещение. Используйте нулевое или отрицательное значение, чтобы отключить смещение.
     * @return $это сам объект запроса
     */
    public function offset($offset)
    {
        //TODO Переписать-проверить
        $this->offset = $offset;
        return $this;
    }

    /**
     * Sets whether to emulate query execution, preventing any interaction with data storage.
     * After this mode is enabled, methods, returning query results like [[QueryInterface::one()]],
     * [[QueryInterface::all()]], [[QueryInterface::exists()]] and so on, will return empty or false values.
     * You should use this method in case your program logic indicates query should not return any results, like
     * in case you set false where condition like `0=1`.
     * @param bool $value whether to prevent query execution.
     * @return $this the query object itself.
     * @since 2.0.11
     */
    public function emulateExecution($value = true)
    {
        $this->emulateExecution = $value;
        return $this;
    }

//    public function prepare($builder)
//    {
//        return $this;
//    }
//
//    public function createCommand($db = null)
//    {
//        if ($db === null) {
//            $db = Yii::$app->getDb();
//        }
//        list($sql, $params) = $db->getQueryBuilder()->build($this);
//
//        $command = $db->createCommand($sql, $params);
//        $this->setCommandCache($command);
//
//        return $command;
//    }
//    public function conditionPrepare($condition){
//        if(count($condition)==3){
//            $operator = array_shift($condition);
//            $condition[0] = $operator.$condition[0];
//        }
//        return $condition;
//    }
//    public function column($db = null)
//    {
//        if ($this->emulateExecution) {
//            return [];
//        }
//
//        if ($this->indexBy === null) {
//            return $this->createCommand($db)->queryColumn();
//        }
//
//        if (is_string($this->indexBy) && is_array($this->select) && count($this->select) === 1) {
//            if (strpos($this->indexBy, '.') === false && count($tables = $this->getTablesUsedInFrom()) > 0) {
//                $this->select[] = key($tables) . '.' . $this->indexBy;
//            } else {
//                $this->select[] = $this->indexBy;
//            }
//        }
//        $rows = $this->createCommand($db)->queryAll();
//        $results = [];
//        $column = null;
//        if (is_string($this->indexBy)) {
//            if (($dotPos = strpos($this->indexBy, '.')) === false) {
//                $column = $this->indexBy;
//            } else {
//                $column = substr($this->indexBy, $dotPos + 1);
//            }
//        }
//        foreach ($rows as $row) {
//            $value = reset($row);
//
//            if ($this->indexBy instanceof \Closure) {
//                $results[call_user_func($this->indexBy, $row)] = $value;
//            } else {
//                $results[$row[$column]] = $value;
//            }
//        }
//
//        return $results;
//    }
//
//    public function scalar($db = null)
//    {
//        if ($this->emulateExecution) {
//            return null;
//        }
//
//        return $this->createCommand($db)->queryScalar();
//    }
//    public function max($q, $db = null)
//    {
//        return $this->queryScalar("MAX($q)", $db);
//    }
//
//    public function min($q, $db = null)
//    {
//        return $this->queryScalar("MIN($q)", $db);
//    }
//
//    public function average($q, $db = null)
//    {
//        if ($this->emulateExecution) {
//            return 0;
//        }
//
//        return $this->queryScalar("AVG($q)", $db);
//    }
//
//    public function sum($q, $db = null)
//    {
//        if ($this->emulateExecution) {
//            return 0;
//        }
//
//        return $this->queryScalar("SUM($q)", $db);
//    }
//    public function from($tables)
//    {
//        if ($tables instanceof ExpressionInterface) {
//            $tables = [$tables];
//        }
//        if (is_string($tables)) {
//            $tables = preg_split('/\s*,\s*/', trim($tables), -1, PREG_SPLIT_NO_EMPTY);
//        }
//        $this->from = $tables;
//        return $this;
//    }
//
//    public function distinct($value = true)
//    {
//        $this->distinct = $value;
//        return $this;
//    }
//
//    protected function getUnaliasedColumnsFromSelect()
//    {
//        $result = [];
//        if (is_array($this->select)) {
//            foreach ($this->select as $name => $value) {
//                if (is_int($name)) {
//                    $result[] = $value;
//                }
//            }
//        }
//        return array_unique($result);
//    }
//
//    protected function getUniqueColumns($columns)
//    {
//        $unaliasedColumns = $this->getUnaliasedColumnsFromSelect();
//
//        $result = [];
//        foreach ($columns as $columnAlias => $columnDefinition) {
//            if (!$columnDefinition instanceof Query) {
//                if (is_string($columnAlias)) {
//                    $existsInSelect = isset($this->select[$columnAlias]) && $this->select[$columnAlias] === $columnDefinition;
//                    if ($existsInSelect) {
//                        continue;
//                    }
//                } elseif (is_int($columnAlias)) {
//                    $existsInSelect = in_array($columnDefinition, $unaliasedColumns, true);
//                    $existsInResultSet = in_array($columnDefinition, $result, true);
//                    if ($existsInSelect || $existsInResultSet) {
//                        continue;
//                    }
//                }
//            }
//
//            $result[$columnAlias] = $columnDefinition;
//        }
//        return $result;
//    }
//
//    protected function normalizeSelect($columns)
//    {
//        if ($columns instanceof ExpressionInterface) {
//            $columns = [$columns];
//        } elseif (!is_array($columns)) {
//            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
//        }
//        $select = [];
//        foreach ($columns as $columnAlias => $columnDefinition) {
//            if (is_string($columnAlias)) {
//                // Already in the normalized format, good for them
//                $select[$columnAlias] = $columnDefinition;
//                continue;
//            }
//            if (is_string($columnDefinition)) {
//                if (
//                    preg_match('/^(.*?)(?i:\s+as\s+|\s+)([\w\-_\.]+)$/', $columnDefinition, $matches) &&
//                    !preg_match('/^\d+$/', $matches[2]) &&
//                    strpos($matches[2], '.') === false
//                ) {
//                    // Using "columnName as alias" or "columnName alias" syntax
//                    $select[$matches[2]] = $matches[1];
//                    continue;
//                }
//                if (strpos($columnDefinition, '(') === false) {
//                    // Normal column name, just alias it to itself to ensure it's not selected twice
//                    $select[$columnDefinition] = $columnDefinition;
//                    continue;
//                }
//            }
//            // Either a string calling a function, DB expression, or sub-query
//            $select[] = $columnDefinition;
//        }
//        return $select;
//    }
//
//    public function addSelect($columns)
//    {
//        if ($this->select === null) {
//            return $this->select($columns);
//        }
//        if (!is_array($this->select)) {
//            $this->select = $this->normalizeSelect($this->select);
//        }
//        $this->select = array_merge($this->select, $this->normalizeSelect($columns));
//
//        return $this;
//    }
//
//    public function select($columns, $option = null)
//    {
//        $this->select = $this->normalizeSelect($columns);
//        $this->selectOption = $option;
//        return $this;
//    }
//
//    public function union($sql, $all = false)
//    {
//        $this->union[] = ['query' => $sql, 'all' => $all];
//        return $this;
//    }
//
//    public function addGroupBy($columns)
//    {
//        if ($columns instanceof ExpressionInterface) {
//            $columns = [$columns];
//        } elseif (!is_array($columns)) {
//            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
//        }
//        if ($this->groupBy === null) {
//            $this->groupBy = $columns;
//        } else {
//            $this->groupBy = array_merge($this->groupBy, $columns);
//        }
//
//        return $this;
//    }
//
//    public function groupBy($columns)
//    {
//        if ($columns instanceof ExpressionInterface) {
//            $columns = [$columns];
//        } elseif (!is_array($columns)) {
//            $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);
//        }
//        $this->groupBy = $columns;
//        return $this;
//    }
//
//    public function rightJoin($table, $on = '', $params = [])
//    {
//        $this->join[] = ['RIGHT JOIN', $table, $on];
//        return $this->addParams($params);
//    }
//
//    public function leftJoin($table, $on = '', $params = [])
//    {
//        $this->join[] = ['LEFT JOIN', $table, $on];
//        return $this->addParams($params);
//    }
//
//    public function innerJoin($table, $on = '', $params = [])
//    {
//        $this->join[] = ['INNER JOIN', $table, $on];
//        return $this->addParams($params);
//    }
//
//    public function join($type, $table, $on = '', $params = [])
//    {
//        $this->join[] = [$type, $table, $on];
//        return $this->addParams($params);
//    }
//
//    public function orWhere($condition, $params = [])
//    {
//        if ($this->where === null) {
//            $this->where = $condition;
//        } else {
//            $this->where = ['or', $this->where, $condition];
//        }
//        $this->addParams($params);
//        return $this;
//    }
//
//    public function andFilterWhere($params){
//        if($params[2]){
//            $this->filter[$params[0].$params[1]] = $params[2];
//        }
//
//        return $this;
//
//    }

}
