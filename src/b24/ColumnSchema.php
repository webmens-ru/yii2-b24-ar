<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace wm\b24;

use yii\base\BaseObject;
use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;

/**
 * Класс ColumnSchema описывает метаданные столбца в таблице базы данных.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ColumnSchema extends BaseObject
{

    //-
    // ++crm_category,
    // +crm_currency,
    // +crm_status,
    // +employee,
    // +user,
    // crm_company,
    // crm_contact,
    // crm,
    // address,
    // url,
    // file,
    // iblock_section,
    // iblock_element


//    const TYPE_PK = 'pk';
//    const TYPE_UPK = 'upk';
//    const TYPE_BIGPK = 'bigpk';
//    const TYPE_UBIGPK = 'ubigpk';
//    const TYPE_CHAR = 'char';
    const TYPE_STRING = 'string';
//    const TYPE_TEXT = 'text';
//    const TYPE_TINYINT = 'tinyint';
//    const TYPE_SMALLINT = 'smallint';
    const TYPE_INTEGER = 'integer';
//    const TYPE_BIGINT = 'bigint';
//    const TYPE_FLOAT = 'float';
    const TYPE_DOUBLE = 'double';
//    const TYPE_DECIMAL = 'decimal';
    const TYPE_DATETIME = 'datetime';
//    const TYPE_TIMESTAMP = 'timestamp';
//    const TYPE_TIME = 'time';
    const TYPE_DATE = 'date';
//    const TYPE_BINARY = 'binary';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_MONEY = 'money';
//    const TYPE_JSON = 'json';
    const TYPE_ENUMERATION = 'enumeration';

    /**
     * @var string имя этого столбца (без кавычек).
     */
    public $name;
    /**
     * @var bool может ли этот столбец быть нулевым.
     */
    public $allowNull;
    /**
     * @var string абстрактный тип этого столбца. Возможные абстрактные типы включают:
     * char, string, text, boolean, smallint, integer, bigint, float, decimal, datetime,
     * временная метка, время, дата, двоичный файл и деньги.
     */
    public $type;
    /**
     * @var string тип PHP этого столбца. Возможные типы PHP включают:
     * «строка», «логическое», «целое», «двойное», «массив».
     */
    public $phpType;
    /**
     * @var string тип БД этого столбца. Возможные типы БД зависят от типа СУБД.
     */
    public $dbType;
    /**
     * @var mixed значение по умолчанию для этого столбца
     */
    public $defaultValue;
    /**
     * @var array перечисляемые значения. Это устанавливается, только если столбец объявлен как перечисляемый тип.
     */
    public $enumValues;
    /**
     * @var int отображать размер столбца.
     */
    public $size;
    /**
     * @var int точность данных столбца, если они числовые.
     */
    public $precision;
    /**
     * @var int масштаб данных столбца, если он числовой.
     */
    public $scale;
    /**
     * @var bool является ли этот столбец первичным ключом
     */
    public $isPrimaryKey;
    /**
     * @var bool является ли этот столбец автоинкрементным
     */
    public $autoIncrement = false;
    /**
     * @var bool является ли этот столбец беззнаковым. Это имеет смысл только
     * когда [[type]] имеет значение `smallint`, `integer` или `bigint`
     */
    public $unsigned;
    /**
     * @var string комментарий к этой колонке. Не все СУБД поддерживают это.
     */
    public $comment;


    /**
     * Преобразует входное значение в соответствии с [[phpType]] после извлечения из базы данных.
     * Если значение равно null или [[Expression]], оно не будет преобразовано.
     * @param mixed $value input value
     * @return mixed converted value
     */
    public function phpTypecast($value)
    {
        return $this->typecast($value);
    }

    /**
     * Преобразует входное значение в соответствии с [[type]] и [[dbType]] для использования в запросе к базе данных.
     * Если значение равно null или [[Expression]], оно не будет преобразовано.
     * @param mixed $value input value
     * @return mixed преобразованное значение. Это также может быть массив, содержащий значение в качестве первого элемента.
     * и тип PDO в качестве второго элемента.
     */
    public function dbTypecast($value)
    {
        // Реализация по умолчанию делает то же самое, что и кастинг для PHP, но это должно быть возможно.
        // чтобы переопределить это аннотацией явного типа PDO.
        return $this->typecast($value);
    }

    /**
     * Преобразует входное значение в соответствии с [[phpType]] после извлечения из базы данных.
     * Если значение равно null или [[Expression]], оно не будет преобразовано.
     * @param mixed $value input value
     * @return mixed converted value
     * @since 2.0.3
     */
    protected function typecast($value)
    {
        //TODO переписать для Б24
//        if ($value === ''
//            && !in_array(
//                $this->type,
//                [
//                    Schema::TYPE_TEXT,
//                    Schema::TYPE_STRING,
//                    Schema::TYPE_BINARY,
//                    Schema::TYPE_CHAR
//                ],
//                true)
//        ) {
//            return null;
//        }
//
//        if ($value === null
//            || gettype($value) === $this->phpType
//            || $value instanceof ExpressionInterface
//            || $value instanceof Query
//        ) {
//            return $value;
//        }
//
//        if (is_array($value)
//            && count($value) === 2
//            && isset($value[1])
//            && in_array($value[1], $this->getPdoParamTypes(), true)
//        ) {
//            return new PdoValue($value[0], $value[1]);
//        }
//
//        switch ($this->phpType) {
//            case 'resource':
//            case 'string':
//                if (is_resource($value)) {
//                    return $value;
//                }
//                if (is_float($value)) {
//                    // ensure type cast always has . as decimal separator in all locales
//                    return StringHelper::floatToString($value);
//                }
//                if (is_numeric($value)
//                    && ColumnSchemaBuilder::CATEGORY_NUMERIC === ColumnSchemaBuilder::$typeCategoryMap[$this->type]
//                ) {
//                    // https://github.com/yiisoft/yii2/issues/14663
//                    return $value;
//                }
//
//                return (string) $value;
//            case 'integer':
//                return (int) $value;
//            case 'boolean':
//                // treating a 0 bit value as false too
//                // https://github.com/yiisoft/yii2/issues/9006
//                return (bool) $value && $value !== "\0";
//            case 'double':
//                return (float) $value;
//        }

        switch ($this->phpType) {
            //case 'resource':
            case 'string':
//                if (is_resource($value)) {
//                    return $value;
//                }
//                if (is_float($value)) {
//                    // ensure type cast always has . as decimal separator in all locales
//                    return StringHelper::floatToString($value);
//                }
//                if (is_numeric($value)
//                    && ColumnSchemaBuilder::CATEGORY_NUMERIC === ColumnSchemaBuilder::$typeCategoryMap[$this->type]
//                ) {
//                    // https://github.com/yiisoft/yii2/issues/14663
//                    return $value;
//                }

                return (string) $value;
            case 'integer':
                return (int) $value;
            case 'boolean':
                // treating a 0 bit value as false too
                // https://github.com/yiisoft/yii2/issues/9006
                return (bool) $value;// && $value !== "\0";
            case 'double':
                return (float) $value;
            case 'array':
                if ($this->type == self::TYPE_ENUMERATION){
                    $value = [
                        'value' => $value,
                        'title' => ArrayHelper::getValue($this->enumValues, $value)
                    ];
                }
                return $value;
        }

        return $value;
    }

    /**
     * @return int[] array of numbers that represent possible PDO parameter types
     */
//    private function getPdoParamTypes()
//    {
//        return [\PDO::PARAM_BOOL, \PDO::PARAM_INT, \PDO::PARAM_STR, \PDO::PARAM_LOB, \PDO::PARAM_NULL, \PDO::PARAM_STMT];
//    }

    public function prepare($key, $columnData){
        $this->name = $key;
        $this->allowNull = !ArrayHelper::getValue($columnData, 'isRequired');
        $this->type = ArrayHelper::getValue($columnData, 'type');
        $this->phpType = $this->getPhpType();
//                    'dbType',
        $this->defaultValue= ArrayHelper::getValue($columnData, 'settings.DEFAULT_VALUE');
//                    'enumValues',
//                    'size',
//                    'precision',
//                    'scale',
//                    'isPrimaryKey',
//                    'autoIncrement',
//                    'unsigned',
        $this->comment = ArrayHelper::getValue($columnData, 'title');
        $this->enumValues = ArrayHelper::map(ArrayHelper::getValue($columnData, 'items'),'ID', 'VALUE');
//        ----------------------------------------------------------------------
        /*
         * isDynamic
         * isImmutable
         * isMultiple
         * isReadOnly
         * upperName
         * ---settings
         * parentEntityTypeId
         * isMyCompany
         * DEFAULT_VALUE
         * MAX_LENGTH
         * MIN_LENGTH
         * REGEXP
         * ROWS
         * SIZE
         *********DYNAMIC_189
         * ENTITY_TYPE
         * *ENTITY_TYPE_ID
         * *ID
         * *NAME
         * *SEMANTIC_INFO
         * **FINAL_SORT
         * **FINAL_SUCCESS_FIELD
         * **FINAL_UNSUCCESS_FIELD
         * **START_FIELD
         * -----statusType
         * ENTITY_TYPE_ID
         * ID
         * NAME
         */

    }

    protected function getPhpType()
    {
        static $typeMap = [
            self::TYPE_STRING => 'string',
            self::TYPE_INTEGER => 'integer',
            self::TYPE_DOUBLE => 'double',
            self::TYPE_DATETIME => 'datetime',
            self::TYPE_DATE => 'date',
            self::TYPE_BOOLEAN => 'boolean',
            self::TYPE_MONEY => 'money',
            self::TYPE_ENUMERATION => 'array',
        ];
        if (isset($typeMap[$this->type])) {
            return $typeMap[$this->type];
        }
        return 'string';
    }
}
