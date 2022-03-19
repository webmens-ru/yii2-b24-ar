<?php

namespace wm\yii2-b24-ar;

use yii\base\BaseObject;
use yii\base\InvalidArgumentException;
use yii\helpers\ArrayHelper;
use Yii;

/**
 * TableSchema представляет метаданные таблицы базы данных.
 *
 * @property-read array $columnNames List of column names.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class TableSchema extends BaseObject
{
    /**
     * @var string имя схемы, которой принадлежит эта таблица
     */
    public $schemaName;
    /**
     * @var string имя этой таблицы. Имя схемы не указано. Используйте [[fullName]], чтобы получить имя с префиксом имени схемы.
     */
    public $name;
    /**
     * @var string полное имя этой таблицы, которое включает префикс имени схемы, если таковой имеется.
     * Обратите внимание, что если имя схемы совпадает с [[Schema::defaultSchema|имя схемы по умолчанию]],
     * имя схемы не будет включено.
     */
    public $fullName;
    /**
     * @var string[] первичные ключи этой таблицы.
     */
    public $primaryKey = [];
    /**
     * @var string имя последовательности для первичного ключа. Null, если нет последовательности.
     */
    public $sequenceName;
    /**
     * @var array внешние ключи этой таблицы. Каждый элемент массива имеет следующую структуру:
     *
     * ```php
     * [
     *  'ForeignTableName',
     *  'fk1' => 'pk1',  // pk1 is in foreign table
     *  'fk2' => 'pk2',  // if composite foreign key
     * ]
     * ```
     */
    public $foreignKeys = [];
    /**
     * @var ColumnSchema[] метаданные столбца этой таблицы. Каждый элемент массива представляет собой объект
     * [[ColumnSchema]], индексированный по именам столбцов.
     */
    public $columns = [];

    public function __construct($schemaData, $config = [])
    {
        $this->columns = $this->prepareColumns($schemaData);

        parent::__construct($config);
    }


    /**
     * Получает метаданные именованного столбца.
     * Это удобный метод получения именованного столбца, даже если он не существует.
     * @param string $name column name
     * @return ColumnSchema|null метаданные именованного столбца. Null, если именованный столбец не существует.
     */
    public function getColumn($name)
    {
        return isset($this->columns[$name]) ? $this->columns[$name] : null;
    }

    /**
     * Возвращает имена всех столбцов в этой таблице.
     * @return array список имен столбцов
     */
    public function getColumnNames()
    {
        return array_keys($this->columns);
    }

    /**
     * Вручную указывает первичный ключ для этой таблицы.
     * @param string|array $keys первичный ключ (может быть составным)
     * @throws InvalidArgumentException если указанный ключ не может быть найден в таблице.
     */
    public function fixPrimaryKey($keys)
    {
        $keys = (array)$keys;
        $this->primaryKey = $keys;
        foreach ($this->columns as $column) {
            $column->isPrimaryKey = false;
        }
        foreach ($keys as $key) {
            if (isset($this->columns[$key])) {
                $this->columns[$key]->isPrimaryKey = true;
            } else {
                throw new InvalidArgumentException("Primary key '$key' cannot be found in table '{$this->name}'.");
            }
        }
    }

    protected function prepareColumns($columnsData)
    {
        $columns = [];
        foreach ($columnsData as $key => $columnData) {
            $column = new ColumnSchema();
            $column->prepare($key, $columnData);
//            $column = ColumnSchema::prepare($key, $columnData);
            $columns[$key] = $column;
        }

        return $columns;
    }
}
