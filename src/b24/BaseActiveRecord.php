<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace wm\yii2-b24-ar;

use Yii;
use yii\base\DynamicModel;
use yii\base\InvalidArgumentException;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\base\Model;
use yii\base\ModelEvent;
use yii\base\NotSupportedException;
use yii\base\UnknownMethodException;
use yii\helpers\ArrayHelper;
use yii\db\ActiveRecordInterface;
use yii\db\ActiveQueryInterface;

/**
 * ActiveRecord is the base class for classes representing relational data in terms of objects.
 *
 * See [[\yii\db\ActiveRecord]] for a concrete implementation.
 *
 * @property-read array $dirtyAttributes Измененные значения атрибутов (пары имя-значение).
 * @property bool $isNewRecord Является ли запись новой и должна ли она быть вставлена при вызове [[save()]].
 * @property array $oldAttributes Старые значения атрибутов (пары имя-значение). Обратите внимание, что тип этого
 * свойство отличается в геттере и сеттере. Подробнее см. [[getOldAttributes()]] и [[setOldAttributes()]].
 * @property-read mixed $oldPrimaryKey The old primary key value. An array (column name => column value) is
 * returned if the primary key is composite. A string is returned otherwise (null will be returned if the key
 * value is null).
 * @property-read mixed $primaryKey Старое значение первичного ключа. Массив (имя столбца => значение столбца)
 * возвращается, если первичный ключ составной. В противном случае возвращается строка (будет возвращено значение null,
 * если значение ключа равно null).
 * @property-read array $relatedRecords Массив связанных записей, индексированных по именам отношений.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
abstract class BaseActiveRecord extends Model implements ActiveRecordInterface
{
    /**
     * @event Event событие, которое запускается, когда запись инициализируется с помощью [[init()]].
     */
    const EVENT_INIT = 'init';
    /**
     * @event Event событие, которое запускается после создания записи и заполнения ее результатом запроса.
     */
    const EVENT_AFTER_FIND = 'afterFind';
    /**
     * @event ModelEvent событие, которое срабатывает перед вставкой записи.
    * Вы можете установить для [[ModelEvent::isValid]] значение `false`, чтобы остановить вставку.
     */
    const EVENT_BEFORE_INSERT = 'beforeInsert';
    /**
     * @event AfterSaveEvent событие, которое запускается после вставки записи.
     */
    const EVENT_AFTER_INSERT = 'afterInsert';
    /**
     * @event ModelEvent событие, которое запускается перед обновлением записи.
     * Вы можете установить для [[ModelEvent::isValid]] значение `false`, чтобы остановить обновление.
     */
    const EVENT_BEFORE_UPDATE = 'beforeUpdate';
    /**
     * @event AfterSaveEvent событие, которое запускается после обновления записи.
     */
    const EVENT_AFTER_UPDATE = 'afterUpdate';
    /**
     * @event ModelEvent событие, которое срабатывает перед удалением записи.
     * Вы можете установить для [[ModelEvent::isValid]] значение `false`, чтобы остановить удаление.
     */
    const EVENT_BEFORE_DELETE = 'beforeDelete';
    /**
     * @event Event событие, которое запускается после удаления записи.
     */
    const EVENT_AFTER_DELETE = 'afterDelete';
    /**
     * @event Event событие, которое запускается после обновления записи.
     * @since 2.0.8
     */
    const EVENT_AFTER_REFRESH = 'afterRefresh';

    /**
     * @var array значения атрибутов, индексированные по именам атрибутов
     */
    private $_attributes = [];
    /**
     * @var array|null старые значения атрибутов, индексированные по именам атрибутов.
     * Это `null`, если запись [[isNewRecord|новая]].
     */
    private $_oldAttributes;
    /**
     * @var array связанные модели, индексированные по именам отношений
     */
    private $_related = [];
    /**
     * @var array имена отношений, индексированные их атрибутами ссылки
     */
    private $_relationsDependencies = [];


    /**
     * {@inheritdoc}
     * @return static|null Экземпляр ActiveRecord, соответствующий условию, или `null`, если ничего не соответствует.
     */
    // TODO findOne($condition) нужно ли? так как использует админские доступы
    public static function findOne($condition)
    {
        return static::findByCondition($condition)->one();
    }

    /**
     * {@inheritdoc}
     * @return static[] массив экземпляров ActiveRecord или пустой массив, если ничего не совпадает.
     */
    public static function findAll($condition)
    {
        return static::findByCondition($condition)->all();
    }

    /**
     * Находит экземпляры ActiveRecord по заданному условию.
     * Этот метод вызывается внутренними функциями [[findOne()]] и [[findAll()]]..
     * @param mixed $condition пожалуйста, обратитесь к [[findOne()]] для объяснения этого параметра
     * @return ActiveQueryInterface вновь созданный экземпляр [[ActiveQueryInterface|ActiveQuery]].
     * @throws InvalidConfigException если первичный ключ не определен
     * @internal
     */
    protected static function findByCondition($condition)
    {
        $query = static::find();
        // TODO Возможно данный код ненужен
        if (!ArrayHelper::isAssociative($condition) && !$condition instanceof ExpressionInterface) {
            // запрос по первичному ключу
            $primaryKey = static::primaryKey();
            if (isset($primaryKey[0])) {
                // если условие скалярное, ищите один первичный ключ, если это массив, ищите несколько значений первичного ключа
                $condition = [$primaryKey[0] => is_array($condition) ? array_values($condition) : $condition];
            } else {
                throw new InvalidConfigException('"' . get_called_class() . '" must have a primary key.');
            }
        }

        return $query->andWhere($condition);
    }

    /**
     * Обновляет всю таблицу, используя предоставленные значения атрибутов и условия.
     *
     * Например, чтобы изменить статус на 1 для всех клиентов со статусом 2:
     *
     * ```php
     * Customer::updateAll(['status' => 1], 'status = 2');
     * ```
     *
     * @param array $attributes значения атрибутов (пары имя-значение) для сохранения в таблицу
     * @param string|array $condition условия, которые будут помещены в WHERE часть UPDATE SQL.
     * Пожалуйста, обратитесь к [[Query::where()]], чтобы узнать, как указать этот параметр.
     * @return int количество обновленных строк
     * @throws NotSupportedException если не переопределить
     */
    public static function updateAll($attributes, $condition = '')
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported.');
    }

    /**
     * Обновляет всю таблицу, используя предоставленные изменения счетчика и условия.
     *
     * Например, чтобы увеличить возраст всех клиентов на 1,
     *
     * ```php
     * Customer::updateAllCounters(['age' => 1]);
     * ```
     *
     * @param array $counters счетчики, которые необходимо обновить (имя атрибута => значение приращения).
     * Используйте отрицательные значения, если вы хотите уменьшить счетчики.
     * @param string|array $condition условия, которые будут помещены в WHERE часть UPDATE SQL.
     * Пожалуйста, обратитесь к [[Query::where()]], чтобы узнать, какуказать этот параметр.
     * @return int  количество обновленных строк
     * @throws NotSupportedException если не переопределить
     */
    public static function updateAllCounters($counters, $condition = '')
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported.');
    }

    /**
     * Deletes rows in the table using the provided conditions.
     * WARNING: If you do not specify any condition, this method will delete ALL rows in the table.
     *
     * For example, to delete all customers whose status is 3:
     *
     * ```php
     * Customer::deleteAll('status = 3');
     * ```
     *
     * @param string|array $condition условия, которые будут помещены в часть WHERE оператора DELETE SQL.
     * Пожалуйста, обратитесь к [[Query::where()]], чтобы узнать, как указать этот параметр.
     * @return int количество удаленных строк
     * @throws NotSupportedException если не переопределить.
     */
    public static function deleteAll($condition = null)
    {
        throw new NotSupportedException(__METHOD__ . ' is not supported.');
    }

    /**
     * Возвращает имя столбца, в котором хранится версия блокировки для реализации оптимистичной блокировки.
     *
     * Оптимистическая блокировка позволяет нескольким пользователям получать доступ к одной и той же записи для редактирования и избегает
     * возможные конфликты. В случае, когда пользователь пытается сохранить запись с некоторыми устаревшими данными
     * (поскольку другой пользователь изменил данные), будет выдано исключение [[StaleObjectException]],
     * и обновление или удаление пропускается.
     *
     * Optimistic locking is only supported by [[update()]] and [[delete()]].
     *
     * To use Optimistic locking:
     *
     * 1. Create a column to store the version number of each row. The column type should be `BIGINT DEFAULT 0`.
     *    Override this method to return the name of this column.
     * 2. Ensure the version value is submitted and loaded to your model before any update or delete.
     *    Or add [[\yii\behaviors\OptimisticLockBehavior|OptimisticLockBehavior]] to your model
     *    class in order to automate the process.
     * 3. In the Web form that collects the user input, add a hidden field that stores
     *    the lock version of the record being updated.
     * 4. In the controller action that does the data updating, try to catch the [[StaleObjectException]]
     *    and implement necessary business logic (e.g. merging the changes, prompting stated data)
     *    to resolve the conflict.
     *
     * @return string the column name that stores the lock version of a table row.
     * If `null` is returned (default implemented), optimistic locking will not be supported.
     */
    // TODO optimisticLock() не поддерживается Битрикс24 исключить везде по коду
    public function optimisticLock()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     * Проверка на возможность получить значение свойства
     */
    public function canGetProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        if (parent::canGetProperty($name, $checkVars, $checkBehaviors)) {
            return true;
        }

        try {
            return $this->hasAttribute($name);
        } catch (\Exception $e) {
            // `hasAttribute()` может не работать с базовыми/абстрактными классами, если используется автоматическая выборка списка атрибутов.
            return false;
        }
    }

    /**
     * {@inheritdoc}
     * * Проверка на возможность записатб значение свойства
     */
    public function canSetProperty($name, $checkVars = true, $checkBehaviors = true)
    {
        if (parent::canSetProperty($name, $checkVars, $checkBehaviors)) {
            return true;
        }

        try {
            return $this->hasAttribute($name);
        } catch (\Exception $e) {
            // `hasAttribute()` может не работать с базовыми/абстрактными классами, если используется автоматическая выборка списка атрибутов
            return false;
        }
    }

    /**
     * PHP Магический метод геттера.
     * Этот метод переопределен, чтобы к атрибутам и связанным объектам можно было получить доступ как к свойствам.
     *
     * @param string $name Имя свойства
     * @throws InvalidArgumentException если имя отношения неверно
     * @return mixed property value
     * @see getAttribute()
     */
    public function __get($name)
    {
        if (isset($this->_attributes[$name]) || array_key_exists($name, $this->_attributes)) {
            return $this->_attributes[$name];
        }

        if ($this->hasAttribute($name)) {
            return null;
        }

        if (isset($this->_related[$name]) || array_key_exists($name, $this->_related)) {
            return $this->_related[$name];
        }
        $value = parent::__get($name);
        if ($value instanceof ActiveQueryInterface) {
            $this->setRelationDependencies($name, $value);
            return $this->_related[$name] = $value->findFor($name, $this);
        }

        return $value;
    }

    /**
     * PHP Магический метод сеттера.
     * Этот метод переопределен, чтобы к атрибутам AR можно было получить доступ как к свойствам.
     * @param string $name property name
     * @param mixed $value property value
     */
    public function __set($name, $value)
    {
        if ($this->hasAttribute($name)) {
            if (
                !empty($this->_relationsDependencies[$name])
                && (!array_key_exists($name, $this->_attributes) || $this->_attributes[$name] !== $value)
            ) {
                $this->resetDependentRelations($name);
            }
            $this->_attributes[$name] = $value;
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * Проверяет, является ли значение свойства null.
     * Этот метод переопределяет родительскую реализацию, проверяя, является ли именованный атрибут нулевым или нет.
     * @param string $name имя свойства или имя события
     * @return bool является ли значение свойства нулевым
     */
    public function __isset($name)
    {
        try {
            return $this->__get($name) !== null;
        } catch (\Exception $t) {
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Устанавливает свойство компонента в значение null.
     * Этот метод переопределяет родительскую реализацию, очищая указанное значение атрибута.
     * @param string $name имя свойства или имя события
     */
    public function __unset($name)
    {
        if ($this->hasAttribute($name)) {
            unset($this->_attributes[$name]);
            if (!empty($this->_relationsDependencies[$name])) {
                $this->resetDependentRelations($name);
            }
        } elseif (array_key_exists($name, $this->_related)) {
            unset($this->_related[$name]);
        } elseif ($this->getRelation($name, false) === null) {
            parent::__unset($name);
        }
    }

    /**
     * Объявляет отношение has-one.
     * Объявление возвращается в терминах реляционного экземпляра [[ActiveQuery]].
     * через который связанная запись может быть запрошена и получена обратно.
     *
     * Отношение «имеет один» означает, что существует не более одной соответствующей записи.
     * критерии, установленные этим отношением, например, клиент имеет одну страну.
     *
     * Например, чтобы объявить отношение «страна» для класса «Клиент», мы можем написать
     * следующий код в классе `Customer`:
     *
     * ```php
     * public function getCountry()
     * {
     *     return $this->hasOne(Country::class, ['id' => 'country_id']);
     * }
     * ```
     *
     * Обратите внимание, что в приведенном выше примере ключ «id» в параметре «$link» относится к имени атрибута.
     * в родственном классе `Country`, тогда как значение 'country_id' относится к имени атрибута
     * в текущем классе AR.
     *
     * Вызывайте методы, объявленные в [[ActiveQuery]], для дальнейшей настройки отношения.
     *
     * @param string $class имя класса связанной записи
     * @param array $link ограничение первичного внешнего ключа. Ключи массива относятся к
     * атрибуты записи, связанные с моделью `$class`, в то время как значения
     * Массив ссылается на соответствующие атрибуты в **этом** классе AR.
     * @return ActiveQueryInterface объект реляционного запроса.
     */
    public function hasOne($class, $link)
    {
        return $this->createRelationQuery($class, $link, false);
    }

    /**
     * Объявляет отношение has-many.
     * Объявление возвращается в терминах реляционного экземпляра [[ActiveQuery]].
     * через который связанная запись может быть запрошена и получена обратно.
     *
     * A `has-many` relation means that there are multiple related records matching
     * the criteria set by this relation, e.g., a customer has many orders.
     *
     * For example, to declare the `orders` relation for `Customer` class, we can write
     * the following code in the `Customer` class:
     *
     * ```php
     * public function getOrders()
     * {
     *     return $this->hasMany(Order::class, ['customer_id' => 'id']);
     * }
     * ```
     *
     * Note that in the above, the 'customer_id' key in the `$link` parameter refers to
     * an attribute name in the related class `Order`, while the 'id' value refers to
     * an attribute name in the current AR class.
     *
     * Call methods declared in [[ActiveQuery]] to further customize the relation.
     *
     * @param string $class the class name of the related record
     * @param array $link the primary-foreign key constraint. The keys of the array refer to
     * the attributes of the record associated with the `$class` model, while the values of the
     * array refer to the corresponding attributes in **this** AR class.
     * @return ActiveQueryInterface the relational query object.
     */
    public function hasMany($class, $link)
    {
        return $this->createRelationQuery($class, $link, true);
    }

    /**
     * Создает экземпляр запроса для отношения `has-one` или `has-many`.
     * @param string $class имя класса связанной записи.
     * @param array $link ограничение первичного внешнего ключа.
     * @param bool $multiple представляет ли этот запрос отношение к более чем одной записи.
     * @return ActiveQueryInterface the relational query object.
     * @since 2.0.12
     * @see hasOne()
     * @see hasMany()
     */
    protected function createRelationQuery($class, $link, $multiple)
    {
        /* @var $class ActiveRecordInterface */
        /* @var $query ActiveQuery */
        $query = $class::find();
        $query->primaryModel = $this;
        $query->link = $link;
        $query->multiple = $multiple;
        return $query;
    }

    /**
     * Заполняет именованное отношение связанными записями.
     * Обратите внимание, что этот метод не проверяет, существует ли отношение или нет.
     * @param string $name имя отношения, например. `orders` для отношения, определенного с помощью метода `getOrders()` (с учетом регистра).
     * @param ActiveRecordInterface|array|null $records связанные записи, которые должны быть заполнены в отношении.
     * @see getRelation()
     */
    public function populateRelation($name, $records)
    {
        foreach ($this->_relationsDependencies as &$relationNames) {
            unset($relationNames[$name]);
        }

        $this->_related[$name] = $records;
    }

    /**
     * Проверяет, заполнено ли именованное отношение записями.
     * @param string $name имя отношения, например. `orders` для отношения, определенного с помощью метода `getOrders()` (с учетом регистра).
     * @return bool было ли отношение заполнено записями.
     * @see getRelation()
     */
    public function isRelationPopulated($name)
    {
        return array_key_exists($name, $this->_related);
    }

    /**
     * Возвращает все заполненные связанные записи.
     * @return array массив связанных записей, индексированных по именам отношений.
     * @see getRelation()
     */
    public function getRelatedRecords()
    {
        return $this->_related;
    }

    /**
     * Возвращает значение, указывающее, есть ли в модели атрибут с указанным именем.
     * @param string $name имя атрибута
     * @return bool есть ли в модели атрибут с указанным именем.
     */
    public function hasAttribute($name)
    {
        return isset($this->_attributes[$name]) || in_array($name, $this->attributes(), true);
    }

    /**
     * Возвращает значение именованного атрибута.
     * Если эта запись является результатом запроса и атрибут не загружен,
     * будет возвращен `null`.
     * @param string $name имя атрибута
     * @return mixed значение атрибута. `null`, если атрибут не установлен или не существует.
     * @see hasAttribute()
     */
    public function getAttribute($name)
    {
        return isset($this->_attributes[$name]) ? $this->_attributes[$name] : null;
    }

    /**
     * Задает значение именованного атрибута.
     * @param string $name the attribute name
     * @param mixed $value the attribute value.
     * @throws InvalidArgumentException если указанный атрибут не существует.
     * @see hasAttribute()
     */
    public function setAttribute($name, $value)
    {
        if ($this->hasAttribute($name)) {
            if (
                !empty($this->_relationsDependencies[$name])
                && (!array_key_exists($name, $this->_attributes) || $this->_attributes[$name] !== $value)
            ) {
                $this->resetDependentRelations($name);
            }
            $this->_attributes[$name] = $value;
        } else {
            throw new InvalidArgumentException(get_class($this) . ' has no attribute named "' . $name . '".');
        }
    }

    /**
     * Возвращает старые значения атрибутов.
     * @return array старые значения атрибутов (пары имя-значение)
*/
    public function getOldAttributes()
    {
        return $this->_oldAttributes === null ? [] : $this->_oldAttributes;
    }

    /**
     * Устанавливает старые значения атрибутов.
     * Все существующие старые значения атрибутов будут удалены.
     * @param array|null $values установить старые значения атрибутов.
     * Если установлено значение `null`, эта запись считается [[isNewRecord|новой]].
     */
    public function setOldAttributes($values)
    {
        $this->_oldAttributes = $values;
    }

    /**
     * Возвращает старое значение именованного атрибута.
     * Если эта запись является результатом запроса и атрибут не загружен,
     * будет возвращен `null`.
     * @param string $name the attribute name
     * @return mixed старое значение атрибута. `null`, если атрибут не был загружен ранее
     * или не существует.
     * @see hasAttribute()
     */
    public function getOldAttribute($name)
    {
        return isset($this->_oldAttributes[$name]) ? $this->_oldAttributes[$name] : null;
    }

    /**
     * Устанавливает старое значение именованного атрибута.
     * @param string $name the attribute name
     * @param mixed $value the old attribute value.
     * @throws InvalidArgumentException if the named attribute does not exist.
     * @see hasAttribute()
     */
    public function setOldAttribute($name, $value)
    {
        if (isset($this->_oldAttributes[$name]) || $this->hasAttribute($name)) {
            $this->_oldAttributes[$name] = $value;
        } else {
            throw new InvalidArgumentException(get_class($this) . ' has no attribute named "' . $name . '".');
        }
    }

    /**
     * Помечает атрибут как грязный.
     * Этот метод может вызываться для принудительного обновления записи при вызове [[update()]],
     * даже если в запись не вносятся изменения.
     * @param string $name the attribute name
     */
    public function markAttributeDirty($name)
    {
        unset($this->_oldAttributes[$name]);
    }

    /**
     * Возвращает значение, указывающее, был ли изменен именованный атрибут.
     * @param string $name the name of the attribute.
     * @param bool $identical производится ли сравнение нового и старого значения для
     * идентичные значения с использованием `===`, по умолчанию `true`. В противном случае для сравнения используется `==`.
     * Этот параметр доступен с версии 2.0.4.
     * @return bool был ли изменен атрибут
     */
    public function isAttributeChanged($name, $identical = true)
    {
        if (isset($this->_attributes[$name], $this->_oldAttributes[$name])) {
            if ($identical) {
                return $this->_attributes[$name] !== $this->_oldAttributes[$name];
            }

            return $this->_attributes[$name] != $this->_oldAttributes[$name];
        }

        return isset($this->_attributes[$name]) || isset($this->_oldAttributes[$name]);
    }

    /**
     * Возвращает значения атрибутов, которые были изменены с момента их последней загрузки или сохранения.
     *
     * Сравнение новых и старых значений производится для идентичных значений с помощью `===`.
     *
     * @param string[]|null $names имена атрибутов, значения которых могут быть возвращены, если они были
     * недавно изменены. Если null, будет использоваться [[attributes()]].
     * @return array измененные значения атрибутов (пары имя-значение)
     */
    public function getDirtyAttributes($names = null)
    {
        if ($names === null) {
            $names = $this->attributes();
        }
        $names = array_flip($names);
        $attributes = [];
        if ($this->_oldAttributes === null) {
            foreach ($this->_attributes as $name => $value) {
                if (isset($names[$name])) {
                    $attributes[$name] = $value;
                }
            }
        } else {
            foreach ($this->_attributes as $name => $value) {
                if (isset($names[$name]) && (!array_key_exists($name, $this->_oldAttributes) || $value !== $this->_oldAttributes[$name])) {
                    $attributes[$name] = $value;
                }
            }
        }

        return $attributes;
    }

    /**
     * Сохраняет текущую запись.
     *
     * This method will call [[insert()]] when [[isNewRecord]] is `true`, or [[update()]]
     * when [[isNewRecord]] is `false`.
     *
     * For example, to save a customer record:
     *
     * ```php
     * $customer = new Customer; // or $customer = Customer::findOne($id);
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->save();
     * ```
     *
     * @param bool $runValidation выполнять ли проверку (вызов [[validate()]])
     * перед сохранением записи. По умолчанию «истина». Если проверка не пройдена, запись
     * не будет сохранен в базе данных, и этот метод вернет false.
     * @param array $attributeNames список имен атрибутов, которые необходимо сохранить. По умолчанию ноль,
     * означает, что все атрибуты, загруженные из БД, будут сохранены.
     * @return bool успешно ли выполнено сохранение (т. е. ошибок проверки не произошло)
     */
    public function save($runValidation = true, $attributeNames = null)
    {
        if ($this->getIsNewRecord()) {
            return $this->insert($runValidation, $attributeNames);
        }

        return $this->update($runValidation, $attributeNames) !== false;
    }

    /**
     * Сохраняет изменения этой активной записи в связанной таблице базы данных.
     *
     * Этот метод выполняет следующие шаги по порядку:
     *
     * 1. call [[beforeValidate()]] when `$runValidation` is `true`. If [[beforeValidate()]]
     *    returns `false`, the rest of the steps will be skipped;
     * 2. call [[afterValidate()]] when `$runValidation` is `true`. If validation
     *    failed, the rest of the steps will be skipped;
     * 3. call [[beforeSave()]]. If [[beforeSave()]] returns `false`,
     *    the rest of the steps will be skipped;
     * 4. save the record into database. If this fails, it will skip the rest of the steps;
     * 5. call [[afterSave()]];
     *
     * In the above step 1, 2, 3 and 5, events [[EVENT_BEFORE_VALIDATE]],
     * [[EVENT_AFTER_VALIDATE]], [[EVENT_BEFORE_UPDATE]], and [[EVENT_AFTER_UPDATE]]
     * will be raised by the corresponding methods.
     *
     * Only the [[dirtyAttributes|changed attribute values]] will be saved into database.
     *
     * For example, to update a customer record:
     *
     * ```php
     * $customer = Customer::findOne($id);
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->update();
     * ```
     *
     * Обратите внимание, что обновление может не повлиять ни на одну строку в таблице.
     * В этом случае этот метод вернет 0. По этой причине вы должны использовать следующее
     * код для проверки успешности update():
     *
     * ```php
     * if ($customer->update() !== false) {
     *     // update successful
     * } else {
     *     // update failed
     * }
     * ```
     *
     * @param bool $runValidation whether to perform validation (calling [[validate()]])
     * before saving the record. Defaults to `true`. If the validation fails, the record
     * will not be saved to the database and this method will return `false`.
     * @param array $attributeNames list of attribute names that need to be saved. Defaults to null,
     * meaning all attributes that are loaded from DB will be saved.
     * @return int|false the number of rows affected, or `false` if validation fails
     * or [[beforeSave()]] stops the updating process.
     * @throws StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
     * being updated is outdated.
     * @throws Exception in case update failed.
     */
    public function update($runValidation = true, $attributeNames = null)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            return false;
        }

        return $this->updateInternal($attributeNames);
    }

    /**
     * Обновляет указанные атрибуты.
     *
     * This method is a shortcut to [[update()]] when data validation is not needed
     * and only a small set attributes need to be updated.
     *
     * You may specify the attributes to be updated as name list or name-value pairs.
     * If the latter, the corresponding attribute values will be modified accordingly.
     * The method will then save the specified attributes into database.
     *
     * Note that this method will **not** perform data validation and will **not** trigger events.
     *
     * @param array $attributes the attributes (names or name-value pairs) to be updated
     * @return int the number of rows affected.
     */
    public function updateAttributes($attributes)
    {
        $attrs = [];
        foreach ($attributes as $name => $value) {
            if (is_int($name)) {
                $attrs[] = $value;
            } else {
                $this->$name = $value;
                $attrs[] = $name;
            }
        }

        $values = $this->getDirtyAttributes($attrs);
        if (empty($values) || $this->getIsNewRecord()) {
            return 0;
        }

        $rows = static::updateAll($values, $this->getOldPrimaryKey(true));

        foreach ($values as $name => $value) {
            $this->_oldAttributes[$name] = $this->_attributes[$name];
        }

        return $rows;
    }

    /**
     * @see update()
     * @param array $attributes attributes to update
     * @return int|false the number of rows affected, or false if [[beforeSave()]] stops the updating process.
     * @throws StaleObjectException
     */
    protected function updateInternal($attributes = null)
    {
        if (!$this->beforeSave(false)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        if (empty($values)) {
            $this->afterSave(false, $values);
            return 0;
        }
        $condition = $this->getOldPrimaryKey(true);
        $lock = $this->optimisticLock();
        if ($lock !== null) {
            $values[$lock] = $this->$lock + 1;
            $condition[$lock] = $this->$lock;
        }
        // We do not check the return value of updateAll() because it's possible
        // that the UPDATE statement doesn't change anything and thus returns 0.
        $rows = static::updateAll($values, $condition);

        if ($lock !== null && !$rows) {
            throw new StaleObjectException('The object being updated is outdated.');
        }

        if (isset($values[$lock])) {
            $this->$lock = $values[$lock];
        }

        $changedAttributes = [];
        foreach ($values as $name => $value) {
            $changedAttributes[$name] = isset($this->_oldAttributes[$name]) ? $this->_oldAttributes[$name] : null;
            $this->_oldAttributes[$name] = $value;
        }
        $this->afterSave(false, $changedAttributes);

        return $rows;
    }

    /**
     * Updates one or several counter columns for the current AR object.
     * Note that this method differs from [[updateAllCounters()]] in that it only
     * saves counters for the current AR object.
     *
     * An example usage is as follows:
     *
     * ```php
     * $post = Post::findOne($id);
     * $post->updateCounters(['view_count' => 1]);
     * ```
     *
     * @param array $counters the counters to be updated (attribute name => increment value)
     * Use negative values if you want to decrement the counters.
     * @return bool whether the saving is successful
     * @see updateAllCounters()
     */
    public function updateCounters($counters)
    {
        if (static::updateAllCounters($counters, $this->getOldPrimaryKey(true)) > 0) {
            foreach ($counters as $name => $value) {
                if (!isset($this->_attributes[$name])) {
                    $this->_attributes[$name] = $value;
                } else {
                    $this->_attributes[$name] += $value;
                }
                $this->_oldAttributes[$name] = $this->_attributes[$name];
            }

            return true;
        }

        return false;
    }

    /**
     * Deletes the table row corresponding to this active record.
     *
     * This method performs the following steps in order:
     *
     * 1. call [[beforeDelete()]]. If the method returns `false`, it will skip the
     *    rest of the steps;
     * 2. delete the record from the database;
     * 3. call [[afterDelete()]].
     *
     * In the above step 1 and 3, events named [[EVENT_BEFORE_DELETE]] and [[EVENT_AFTER_DELETE]]
     * will be raised by the corresponding methods.
     *
     * @return int|false the number of rows deleted, or `false` if the deletion is unsuccessful for some reason.
     * Note that it is possible the number of rows deleted is 0, even though the deletion execution is successful.
     * @throws StaleObjectException if [[optimisticLock|optimistic locking]] is enabled and the data
     * being deleted is outdated.
     * @throws Exception in case delete failed.
     */
    public function delete()
    {
        $result = false;
        if ($this->beforeDelete()) {
            // we do not check the return value of deleteAll() because it's possible
            // the record is already deleted in the database and thus the method will return 0
            $condition = $this->getOldPrimaryKey(true);
            $lock = $this->optimisticLock();
            if ($lock !== null) {
                $condition[$lock] = $this->$lock;
            }
            $result = static::deleteAll($condition);
            if ($lock !== null && !$result) {
                throw new StaleObjectException('The object being deleted is outdated.');
            }
            $this->_oldAttributes = null;
            $this->afterDelete();
        }

        return $result;
    }

    /**
     * Returns a value indicating whether the current record is new.
     * @return bool whether the record is new and should be inserted when calling [[save()]].
     */
    public function getIsNewRecord()
    {
        return $this->_oldAttributes === null;
    }

    /**
     * Sets the value indicating whether the record is new.
     * @param bool $value whether the record is new and should be inserted when calling [[save()]].
     * @see getIsNewRecord()
     */
    public function setIsNewRecord($value)
    {
        $this->_oldAttributes = $value ? null : $this->_attributes;
    }

    /**
     * Initializes the object.
     * This method is called at the end of the constructor.
     * The default implementation will trigger an [[EVENT_INIT]] event.
     */
    public function init()
    {
        parent::init();
        $this->trigger(self::EVENT_INIT);
    }

    /**
     * This method is called when the AR object is created and populated with the query result.
     * The default implementation will trigger an [[EVENT_AFTER_FIND]] event.
     * When overriding this method, make sure you call the parent implementation to ensure the
     * event is triggered.
     */
    public function afterFind()
    {
        $this->trigger(self::EVENT_AFTER_FIND);
    }

    /**
     * This method is called at the beginning of inserting or updating a record.
     *
     * The default implementation will trigger an [[EVENT_BEFORE_INSERT]] event when `$insert` is `true`,
     * or an [[EVENT_BEFORE_UPDATE]] event if `$insert` is `false`.
     * When overriding this method, make sure you call the parent implementation like the following:
     *
     * ```php
     * public function beforeSave($insert)
     * {
     *     if (!parent::beforeSave($insert)) {
     *         return false;
     *     }
     *
     *     // ...custom code here...
     *     return true;
     * }
     * ```
     *
     * @param bool $insert whether this method called while inserting a record.
     * If `false`, it means the method is called while updating a record.
     * @return bool whether the insertion or updating should continue.
     * If `false`, the insertion or updating will be cancelled.
     */
    public function beforeSave($insert)
    {
        $event = new ModelEvent();
        $this->trigger($insert ? self::EVENT_BEFORE_INSERT : self::EVENT_BEFORE_UPDATE, $event);

        return $event->isValid;
    }

    /**
     * This method is called at the end of inserting or updating a record.
     * The default implementation will trigger an [[EVENT_AFTER_INSERT]] event when `$insert` is `true`,
     * or an [[EVENT_AFTER_UPDATE]] event if `$insert` is `false`. The event class used is [[AfterSaveEvent]].
     * When overriding this method, make sure you call the parent implementation so that
     * the event is triggered.
     * @param bool $insert whether this method called while inserting a record.
     * If `false`, it means the method is called while updating a record.
     * @param array $changedAttributes The old values of attributes that had changed and were saved.
     * You can use this parameter to take action based on the changes made for example send an email
     * when the password had changed or implement audit trail that tracks all the changes.
     * `$changedAttributes` gives you the old attribute values while the active record (`$this`) has
     * already the new, updated values.
     *
     * Note that no automatic type conversion performed by default. You may use
     * [[\yii\behaviors\AttributeTypecastBehavior]] to facilitate attribute typecasting.
     * See http://www.yiiframework.com/doc-2.0/guide-db-active-record.html#attributes-typecasting.
     */
    public function afterSave($insert, $changedAttributes)
    {
        $this->trigger($insert ? self::EVENT_AFTER_INSERT : self::EVENT_AFTER_UPDATE, new AfterSaveEvent([
            'changedAttributes' => $changedAttributes,
        ]));
    }

    /**
     * This method is invoked before deleting a record.
     *
     * The default implementation raises the [[EVENT_BEFORE_DELETE]] event.
     * When overriding this method, make sure you call the parent implementation like the following:
     *
     * ```php
     * public function beforeDelete()
     * {
     *     if (!parent::beforeDelete()) {
     *         return false;
     *     }
     *
     *     // ...custom code here...
     *     return true;
     * }
     * ```
     *
     * @return bool whether the record should be deleted. Defaults to `true`.
     */
    public function beforeDelete()
    {
        $event = new ModelEvent();
        $this->trigger(self::EVENT_BEFORE_DELETE, $event);

        return $event->isValid;
    }

    /**
     * This method is invoked after deleting a record.
     * The default implementation raises the [[EVENT_AFTER_DELETE]] event.
     * You may override this method to do postprocessing after the record is deleted.
     * Make sure you call the parent implementation so that the event is raised properly.
     */
    public function afterDelete()
    {
        $this->trigger(self::EVENT_AFTER_DELETE);
    }

    /**
     * Заполняет эту активную запись последними данными.
     *
     * Если обновление прошло успешно, будет запущено событие [[EVENT_AFTER_REFRESH]].
     * Это событие доступно с версии 2.0.8.
     *
     * @return bool whether the row still exists in the database. If `true`, the latest data
     * will be populated to this active record. Otherwise, this record will remain unchanged.
     */
    public function refresh()
    {
        /* @var $record BaseActiveRecord */
        $record = static::findOne($this->getPrimaryKey(true));
        return $this->refreshInternal($record);
    }

    /**
     * Repopulates this active record with the latest data from a newly fetched instance.
     * @param BaseActiveRecord $record the record to take attributes from.
     * @return bool whether refresh was successful.
     * @see refresh()
     * @since 2.0.13
     */
    protected function refreshInternal($record)
    {
        if ($record === null) {
            return false;
        }
        foreach ($this->attributes() as $name) {
            $this->_attributes[$name] = isset($record->_attributes[$name]) ? $record->_attributes[$name] : null;
        }
        $this->_oldAttributes = $record->_oldAttributes;
        $this->_related = [];
        $this->_relationsDependencies = [];
        $this->afterRefresh();

        return true;
    }

    /**
     * This method is called when the AR object is refreshed.
     * The default implementation will trigger an [[EVENT_AFTER_REFRESH]] event.
     * When overriding this method, make sure you call the parent implementation to ensure the
     * event is triggered.
     * @since 2.0.8
     */
    public function afterRefresh()
    {
        $this->trigger(self::EVENT_AFTER_REFRESH);
    }

    /**
     * Возвращает значение, указывающее, совпадает ли данная активная запись с текущей.
     * Сравнение выполняется путем сравнения имен таблиц и значений первичного ключа двух активных записей.
     * Если одна из записей [[isNewRecord|новая]] также считается не равной.
     * @param ActiveRecordInterface $record record to compare to
     * @return bool whether the two active records refer to the same row in the same database table.
     */
    public function equals($record)
    {
        if ($this->getIsNewRecord() || $record->getIsNewRecord()) {
            return false;
        }

        return get_class($this) === get_class($record) && $this->getPrimaryKey() === $record->getPrimaryKey();
    }

    /**
     * Возвращает значение(я) первичного ключа.
     * @param bool $asArray следует ли возвращать значение первичного ключа в виде массива. Если "правда",
     * возвращаемое значение будет массивом с именами столбцов в качестве ключей и значениями столбцов в качестве значений.
     * Обратите внимание, что для составных первичных ключей всегда будет возвращаться массив независимо от значения этого параметра.
     * @property mixed The значение первичного ключа. Массив (имя столбца => значение столбца) возвращается, если
     * первичный ключ составной. В противном случае возвращается строка (если
     * значение ключа равно null).
     * @return mixed значение первичного ключа. Массив (имя столбца => значение столбца) возвращается, если первичный ключ
     * является составным или `$asArray` равно `true`. В противном случае возвращается строка (если
     * значение ключа равно null).
     */
    public function getPrimaryKey($asArray = false)
    {
        $keys = static::primaryKey();
        if (!$asArray && count($keys) === 1) {
            return isset($this->_attributes[$keys[0]]) ? $this->_attributes[$keys[0]] : null;
        }

        $values = [];
        foreach ($keys as $name) {
            $values[$name] = isset($this->_attributes[$name]) ? $this->_attributes[$name] : null;
        }

        return $values;
    }

    /**
     * Возвращает старое значение(я) первичного ключа.
     * This refers to the primary key value that is populated into the record
     * after executing a find method (e.g. find(), findOne()).
     * The value remains unchanged even if the primary key attribute is manually assigned with a different value.
     * @param bool $asArray whether to return the primary key value as an array. If `true`,
     * the return value will be an array with column name as key and column value as value.
     * If this is `false` (default), a scalar value will be returned for non-composite primary key.
     * @property mixed The old primary key value. An array (column name => column value) is
     * returned if the primary key is composite. A string is returned otherwise (null will be
     * returned if the key value is null).
     * @return mixed the old primary key value. An array (column name => column value) is returned if the primary key
     * is composite or `$asArray` is `true`. A string is returned otherwise (null will be returned if
     * the key value is null).
     * @throws Exception if the AR model does not have a primary key
     */
    public function getOldPrimaryKey($asArray = false)
    {
        $keys = static::primaryKey();
        if (empty($keys)) {
            throw new Exception(get_class($this) . ' does not have a primary key. You should either define a primary key for the corresponding table or override the primaryKey() method.');
        }
        if (!$asArray && count($keys) === 1) {
            return isset($this->_oldAttributes[$keys[0]]) ? $this->_oldAttributes[$keys[0]] : null;
        }

        $values = [];
        foreach ($keys as $name) {
            $values[$name] = isset($this->_oldAttributes[$name]) ? $this->_oldAttributes[$name] : null;
        }

        return $values;
    }

    /**
     * Заполняет объект активной записи, используя строку данных из базы данных/хранилища.
     *
     * This is an internal method meant to be called to create active record objects after
     * fetching data from the database. It is mainly used by [[ActiveQuery]] to populate
     * the query results into active records.
     *
     * When calling this method manually you should call [[afterFind()]] on the created
     * record to trigger the [[EVENT_AFTER_FIND|afterFind Event]].
     *
     * @param BaseActiveRecord $record the record to be populated. In most cases this will be an instance
     * created by [[instantiate()]] beforehand.
     * @param array $row attribute values (name => value)
     */
    // TODO Изучить populateRecord($record, $row) возможно даже оставить не меняя
//    оригинал
//    public static function populateRecord($record, $row)
//    {
//        $columns = array_flip($record->attributes());
//        foreach ($row as $name => $value) {
//            if (isset($columns[$name])) {
//                $record->_attributes[$name] = $value;
//            } elseif ($record->canSetProperty($name)) {
//                $record->$name = $value;
//            }
//        }
//        $record->_oldAttributes = $record->_attributes;
//        $record->_related = [];
//        $record->_relationsDependencies = [];
//    }


    public static function populateRecord($record, $row)
    {
        $columns = array_flip($record->attributes());
        foreach ($row as $name => $value) {
            if (isset($columns[$name])) {
                $record->_attributes[$name] = $value;
            } elseif ($record->canSetProperty($name)) {
                $record->$name = $value;
            }
        }
        $record->_oldAttributes = $record->_attributes;
        $record->_related = [];
        $record->_relationsDependencies = [];
    }

    /**
     * Создает экземпляр активной записи.
     *
     * его метод вызывается вместе с [[populateRecord()]] с помощью [[ActiveQuery]].
     * Он не предназначен для непосредственного создания новых записей.
     *
     * Вы можете переопределить этот метод, если создаваемый экземпляр
     * зависит от данных строки, которые должны быть заполнены в записи.
     * Например, создав запись на основе значения столбца,
     * вы можете реализовать так называемое отображение наследования одной таблицы.
     * @param array $row данные строки, которые должны быть заполнены в записи.
     * @return static вновь созданная активная запись
     */
    public static function instantiate($row)
    {
        return new static();
    }

    /**
     * Возвращает, есть ли элемент по указанному смещению.
     * This method is required by the interface [[\ArrayAccess]].
     * @param mixed $offset the offset to check on
     * @return bool whether there is an element at the specified offset.
     */
    #[\ReturnTypeWillChange]
    // TODO Изучить что это вообще такое offsetExists($offset)
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * Возвращает объект отношения с указанным именем.
     * Отношение определяется методом получения, который возвращает объект [[ActiveQueryInterface]].
     * Он может быть объявлен либо в самом классе Active Record, либо в одном из его поведений.
     * @param string $name the relation name, e.g. `orders` for a relation defined via `getOrders()` method (case-sensitive).
     * @param bool $throwException whether to throw exception if the relation does not exist.
     * @return ActiveQueryInterface|ActiveQuery the relational query object. If the relation does not exist
     * and `$throwException` is `false`, `null` will be returned.
     * @throws InvalidArgumentException if the named relation does not exist.
     */
    public function getRelation($name, $throwException = true)
    {
        $getter = 'get' . $name;
        try {
            // the relation could be defined in a behavior
            $relation = $this->$getter();
        } catch (UnknownMethodException $e) {
            if ($throwException) {
                throw new InvalidArgumentException(get_class($this) . ' has no relation named "' . $name . '".', 0, $e);
            }

            return null;
        }
//        if (!$relation instanceof ActiveQueryInterface) {
//            if ($throwException) {
//                throw new InvalidArgumentException(get_class($this) . ' has no relation named "' . $name . '".');
//            }
//
//            return null;
//        }
        if (method_exists($this, $getter)) {
            // relation name is case sensitive, trying to validate it when the relation is defined within this class
            $method = new \ReflectionMethod($this, $getter);
            $realName = lcfirst(substr($method->getName(), 3));
            if ($realName !== $name) {
                if ($throwException) {
                    throw new InvalidArgumentException('Relation names are case sensitive. ' . get_class($this) . " has a relation named \"$realName\" instead of \"$name\".");
                }

                return null;
            }
        }

        return $relation;
    }

    /**
     * Устанавливает связь между двумя моделями.
     *
     * Связь устанавливается путем установки значений внешнего ключа в одной модели.
     * быть соответствующим значением первичного ключа в другой модели.
     * Модель с внешним ключом будет сохранена в базе данных **без** проверки
     * и **без** событий/поведений.
     *
     * If the relationship involves a junction table, a new row will be inserted into the
     * junction table which contains the primary key values from both models.
     *
     * Note that this method requires that the primary key value is not null.
     *
     * @param string $name the case sensitive name of the relationship, e.g. `orders` for a relation defined via `getOrders()` method.
     * @param ActiveRecordInterface $model the model to be linked with the current one.
     * @param array $extraColumns additional column values to be saved into the junction table.
     * This parameter is only meaningful for a relationship involving a junction table
     * @throws InvalidCallException if the method is unable to link two models.
     */
    public function link($name, $model, $extraColumns = [])
    {
        /* @var $relation ActiveQueryInterface|ActiveQuery */
        $relation = $this->getRelation($name);

        if ($relation->via !== null) {
            if ($this->getIsNewRecord() || $model->getIsNewRecord()) {
                throw new InvalidCallException('Unable to link models: the models being linked cannot be newly created.');
            }
            if (is_array($relation->via)) {
                /* @var $viaRelation ActiveQuery */
                list($viaName, $viaRelation) = $relation->via;
                $viaClass = $viaRelation->modelClass;
                // unset $viaName so that it can be reloaded to reflect the change
                unset($this->_related[$viaName]);
            } else {
                $viaRelation = $relation->via;
                $viaTable = reset($relation->via->from);
            }
            $columns = [];
            foreach ($viaRelation->link as $a => $b) {
                $columns[$a] = $this->$b;
            }
            foreach ($relation->link as $a => $b) {
                $columns[$b] = $model->$a;
            }
            foreach ($extraColumns as $k => $v) {
                $columns[$k] = $v;
            }
            if (is_array($relation->via)) {
                /* @var $viaClass ActiveRecordInterface */
                /* @var $record ActiveRecordInterface */
                $record = Yii::createObject($viaClass);
                foreach ($columns as $column => $value) {
                    $record->$column = $value;
                }
                $record->insert(false);
            } else {
                /* @var $viaTable string */
                static::getDb()->createCommand()->insert($viaTable, $columns)->execute();
            }
        } else {
            $p1 = $model->isPrimaryKey(array_keys($relation->link));
            $p2 = static::isPrimaryKey(array_values($relation->link));
            if ($p1 && $p2) {
                if ($this->getIsNewRecord()) {
                    if ($model->getIsNewRecord()) {
                        throw new InvalidCallException('Unable to link models: at most one model can be newly created.');
                    }
                    $this->bindModels(array_flip($relation->link), $this, $model);
                } else {
                    $this->bindModels($relation->link, $model, $this);
                }
            } elseif ($p1) {
                $this->bindModels(array_flip($relation->link), $this, $model);
            } elseif ($p2) {
                $this->bindModels($relation->link, $model, $this);
            } else {
                throw new InvalidCallException('Unable to link models: the link defining the relation does not involve any primary key.');
            }
        }

        // update lazily loaded related objects
        if (!$relation->multiple) {
            $this->_related[$name] = $model;
        } elseif (isset($this->_related[$name])) {
            if ($relation->indexBy !== null) {
                if ($relation->indexBy instanceof \Closure) {
                    $index = call_user_func($relation->indexBy, $model);
                } else {
                    $index = $model->{$relation->indexBy};
                }
                $this->_related[$name][$index] = $model;
            } else {
                $this->_related[$name][] = $model;
            }
        }
    }

    /**
     * Разрушает отношения между двумя моделями.
     *
     * The model with the foreign key of the relationship will be deleted if `$delete` is `true`.
     * Otherwise, the foreign key will be set `null` and the model will be saved without validation.
     *
     * @param string $name the case sensitive name of the relationship, e.g. `orders` for a relation defined via `getOrders()` method.
     * @param ActiveRecordInterface $model the model to be unlinked from the current one.
     * You have to make sure that the model is really related with the current model as this method
     * does not check this.
     * @param bool $delete whether to delete the model that contains the foreign key.
     * If `false`, the model's foreign key will be set `null` and saved.
     * If `true`, the model containing the foreign key will be deleted.
     * @throws InvalidCallException if the models cannot be unlinked
     * @throws Exception
     * @throws StaleObjectException
     */
    public function unlink($name, $model, $delete = false)
    {
        /* @var $relation ActiveQueryInterface|ActiveQuery */
        $relation = $this->getRelation($name);

        if ($relation->via !== null) {
            if (is_array($relation->via)) {
                /* @var $viaRelation ActiveQuery */
                list($viaName, $viaRelation) = $relation->via;
                $viaClass = $viaRelation->modelClass;
                unset($this->_related[$viaName]);
            } else {
                $viaRelation = $relation->via;
                $viaTable = reset($relation->via->from);
            }
            $columns = [];
            foreach ($viaRelation->link as $a => $b) {
                $columns[$a] = $this->$b;
            }
            foreach ($relation->link as $a => $b) {
                $columns[$b] = $model->$a;
            }
            $nulls = [];
            foreach (array_keys($columns) as $a) {
                $nulls[$a] = null;
            }
            if (property_exists($viaRelation, 'on') && $viaRelation->on !== null) {
                $columns = ['and', $columns, $viaRelation->on];
            }
            if (is_array($relation->via)) {
                /* @var $viaClass ActiveRecordInterface */
                if ($delete) {
                    $viaClass::deleteAll($columns);
                } else {
                    $viaClass::updateAll($nulls, $columns);
                }
            } else {
                /* @var $viaTable string */
                /* @var $command Command */
                $command = static::getDb()->createCommand();
                if ($delete) {
                    $command->delete($viaTable, $columns)->execute();
                } else {
                    $command->update($viaTable, $nulls, $columns)->execute();
                }
            }
        } else {
            $p1 = $model->isPrimaryKey(array_keys($relation->link));
            $p2 = static::isPrimaryKey(array_values($relation->link));
            if ($p2) {
                if ($delete) {
                    $model->delete();
                } else {
                    foreach ($relation->link as $a => $b) {
                        $model->$a = null;
                    }
                    $model->save(false);
                }
            } elseif ($p1) {
                foreach ($relation->link as $a => $b) {
                    if (is_array($this->$b)) { // relation via array valued attribute
                        if (($key = array_search($model->$a, $this->$b, false)) !== false) {
                            $values = $this->$b;
                            unset($values[$key]);
                            $this->$b = array_values($values);
                        }
                    } else {
                        $this->$b = null;
                    }
                }
                $delete ? $this->delete() : $this->save(false);
            } else {
                throw new InvalidCallException('Unable to unlink models: the link does not involve any primary key.');
            }
        }

        if (!$relation->multiple) {
            unset($this->_related[$name]);
        } elseif (isset($this->_related[$name])) {
            /* @var $b ActiveRecordInterface */
            foreach ($this->_related[$name] as $a => $b) {
                if ($model->getPrimaryKey() === $b->getPrimaryKey()) {
                    unset($this->_related[$name][$a]);
                }
            }
        }
    }

    /**
     * Уничтожает отношения в текущей модели.
     *
     * The model with the foreign key of the relationship will be deleted if `$delete` is `true`.
     * Otherwise, the foreign key will be set `null` and the model will be saved without validation.
     *
     * Note that to destroy the relationship without removing records make sure your keys can be set to null
     *
     * @param string $name the case sensitive name of the relationship, e.g. `orders` for a relation defined via `getOrders()` method.
     * @param bool $delete whether to delete the model that contains the foreign key.
     *
     * Note that the deletion will be performed using [[deleteAll()]], which will not trigger any events on the related models.
     * If you need [[EVENT_BEFORE_DELETE]] or [[EVENT_AFTER_DELETE]] to be triggered, you need to [[find()|find]] the models first
     * and then call [[delete()]] on each of them.
     */
    public function unlinkAll($name, $delete = false)
    {
        /* @var $relation ActiveQueryInterface|ActiveQuery */
        $relation = $this->getRelation($name);

        if ($relation->via !== null) {
            if (is_array($relation->via)) {
                /* @var $viaRelation ActiveQuery */
                list($viaName, $viaRelation) = $relation->via;
                $viaClass = $viaRelation->modelClass;
                unset($this->_related[$viaName]);
            } else {
                $viaRelation = $relation->via;
                $viaTable = reset($relation->via->from);
            }
            $condition = [];
            $nulls = [];
            foreach ($viaRelation->link as $a => $b) {
                $nulls[$a] = null;
                $condition[$a] = $this->$b;
            }
            if (!empty($viaRelation->where)) {
                $condition = ['and', $condition, $viaRelation->where];
            }
            if (property_exists($viaRelation, 'on') && !empty($viaRelation->on)) {
                $condition = ['and', $condition, $viaRelation->on];
            }
            if (is_array($relation->via)) {
                /* @var $viaClass ActiveRecordInterface */
                if ($delete) {
                    $viaClass::deleteAll($condition);
                } else {
                    $viaClass::updateAll($nulls, $condition);
                }
            } else {
                /* @var $viaTable string */
                /* @var $command Command */
                $command = static::getDb()->createCommand();
                if ($delete) {
                    $command->delete($viaTable, $condition)->execute();
                } else {
                    $command->update($viaTable, $nulls, $condition)->execute();
                }
            }
        } else {
            /* @var $relatedModel ActiveRecordInterface */
            $relatedModel = $relation->modelClass;
            if (!$delete && count($relation->link) === 1 && is_array($this->{$b = reset($relation->link)})) {
                // relation via array valued attribute
                $this->$b = [];
                $this->save(false);
            } else {
                $nulls = [];
                $condition = [];
                foreach ($relation->link as $a => $b) {
                    $nulls[$a] = null;
                    $condition[$a] = $this->$b;
                }
                if (!empty($relation->where)) {
                    $condition = ['and', $condition, $relation->where];
                }
                if (property_exists($relation, 'on') && !empty($relation->on)) {
                    $condition = ['and', $condition, $relation->on];
                }
                if ($delete) {
                    $relatedModel::deleteAll($condition);
                } else {
                    $relatedModel::updateAll($nulls, $condition);
                }
            }
        }

        unset($this->_related[$name]);
    }

    /**
     * @param array $link
     * @param ActiveRecordInterface $foreignModel
     * @param ActiveRecordInterface $primaryModel
     * @throws InvalidCallException
     */
    private function bindModels($link, $foreignModel, $primaryModel)
    {
        foreach ($link as $fk => $pk) {
            $value = $primaryModel->$pk;
            if ($value === null) {
                throw new InvalidCallException('Unable to link models: the primary key of ' . get_class($primaryModel) . ' is null.');
            }
            if (is_array($foreignModel->$fk)) { // relation via array valued attribute
                $foreignModel->{$fk}[] = $value;
            } else {
                $foreignModel->{$fk} = $value;
            }
        }
        $foreignModel->save(false);
    }

    /**
     * Возвращает значение, указывающее, представляет ли данный набор атрибутов первичный ключ для этой модели.
     * @param array $keys the set of attributes to check
     * @return bool whether the given set of attributes represents the primary key for this model
     */
    public static function isPrimaryKey($keys)
    {
        $pks = static::primaryKey();
        if (count($keys) === count($pks)) {
            return count(array_intersect($keys, $pks)) === count($pks);
        }

        return false;
    }

    /**
     * Возвращает текстовую метку для указанного атрибута.
     * Если атрибут выглядит как `relatedModel.attribute`, то атрибут будет получен из связанной модели.
     * @param string $attribute the attribute name
     * @return string the attribute label
     * @see generateAttributeLabel()
     * @see attributeLabels()
     */
    public function getAttributeLabel($attribute)
    {
        $labels = $this->attributeLabels();
        if (isset($labels[$attribute])) {
            return $labels[$attribute];
        } elseif (strpos($attribute, '.')) {
            $attributeParts = explode('.', $attribute);
            $neededAttribute = array_pop($attributeParts);

            $relatedModel = $this;
            foreach ($attributeParts as $relationName) {
                if ($relatedModel->isRelationPopulated($relationName) && $relatedModel->$relationName instanceof self) {
                    $relatedModel = $relatedModel->$relationName;
                } else {
                    try {
                        $relation = $relatedModel->getRelation($relationName);
                    } catch (InvalidParamException $e) {
                        return $this->generateAttributeLabel($attribute);
                    }
                    /* @var $modelClass ActiveRecordInterface */
                    $modelClass = $relation->modelClass;
                    $relatedModel = $modelClass::instance();
                }
            }

            $labels = $relatedModel->attributeLabels();
            if (isset($labels[$neededAttribute])) {
                return $labels[$neededAttribute];
            }
        }

        return $this->generateAttributeLabel($attribute);
    }

    /**
     * Возвращает текстовую подсказку для указанного атрибута.
     * Если атрибут выглядит как `relatedModel.attribute`, то атрибут будет получен из связанной модели.
     * @param string $attribute the attribute name
     * @return string the attribute hint
     * @see attributeHints()
     * @since 2.0.4
     */
    public function getAttributeHint($attribute)
    {
        $hints = $this->attributeHints();
        if (isset($hints[$attribute])) {
            return $hints[$attribute];
        } elseif (strpos($attribute, '.')) {
            $attributeParts = explode('.', $attribute);
            $neededAttribute = array_pop($attributeParts);

            $relatedModel = $this;
            foreach ($attributeParts as $relationName) {
                if ($relatedModel->isRelationPopulated($relationName) && $relatedModel->$relationName instanceof self) {
                    $relatedModel = $relatedModel->$relationName;
                } else {
                    try {
                        $relation = $relatedModel->getRelation($relationName);
                    } catch (InvalidParamException $e) {
                        return '';
                    }
                    /* @var $modelClass ActiveRecordInterface */
                    $modelClass = $relation->modelClass;
                    $relatedModel = $modelClass::instance();
                }
            }

            $hints = $relatedModel->attributeHints();
            if (isset($hints[$neededAttribute])) {
                return $hints[$neededAttribute];
            }
        }

        return '';
    }

    /**
     * {@inheritdoc}
     *
     * Реализация по умолчанию возвращает имена столбцов, значения которых были заполнены в этой записи.
     */
    //TODO Изучить fields() и исправить текущую
//    public function fields()
//    {
//        $fields = array_keys($this->_attributes);
//
//        return array_combine($fields, $fields);
//    }

//TODO Переписать function fields() с учетом атрибутов
    public function fields()
    {
//        $fields = ['id'=>'id', 'title'=>'title'];//array_keys($this->_attributes);
        return [];// array_combine($fields, $fields);
    }

    /**
     * {@inheritdoc}
     *
     * Реализация по умолчанию возвращает имена отношений, которые были заполнены в этой записи.
     */
    public function extraFields()
    {
        $fields = array_keys($this->getRelatedRecords());

        return array_combine($fields, $fields);
    }

    /**
     * Устанавливает значение элемента по указанному смещению равным нулю.
     * Этот метод требуется для интерфейса SPL [[\ArrayAccess]].
     * Он вызывается неявно, когда вы используете что-то вроде `unset($model[$offset])`.
     * @param mixed $offset the offset to unset element
     */
    public function offsetUnset($offset)
    {
        if (property_exists($this, $offset)) {
            $this->$offset = null;
        } else {
            unset($this->$offset);
        }
    }

    /**
     * Сбрасывает зависимые связанные модели, проверяя, содержат ли их ссылки определенный атрибут.
     * @param string $attribute The changed attribute name.
     */
    private function resetDependentRelations($attribute)
    {
        foreach ($this->_relationsDependencies[$attribute] as $relation) {
            unset($this->_related[$relation]);
        }
        unset($this->_relationsDependencies[$attribute]);
    }

    /**
     * Устанавливает зависимости отношения для свойства
     * @param string $name property name
     * @param ActiveQueryInterface $relation relation instance
     * @param string|null $viaRelationName intermediate relation
     */
    private function setRelationDependencies($name, $relation, $viaRelationName = null)
    {
        if (empty($relation->via) && $relation->link) {
            foreach ($relation->link as $attribute) {
                $this->_relationsDependencies[$attribute][$name] = $name;
                if ($viaRelationName !== null) {
                    $this->_relationsDependencies[$attribute][] = $viaRelationName;
                }
            }
        } elseif ($relation->via instanceof ActiveQueryInterface) {
            $this->setRelationDependencies($name, $relation->via);
        } elseif (is_array($relation->via)) {
            list($viaRelationName, $viaQuery) = $relation->via;
            $this->setRelationDependencies($name, $viaQuery, $viaRelationName);
        }
    }
}
