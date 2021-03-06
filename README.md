[![Latest Stable Version](https://poser.pugx.org/arrilot/bitrix-models/v/stable.svg)](https://packagist.org/packages/arrilot/bitrix-models/)
[![Total Downloads](https://img.shields.io/packagist/dt/arrilot/bitrix-models.svg?style=flat)](https://packagist.org/packages/Arrilot/bitrix-models)
[![Build Status](https://img.shields.io/travis/arrilot/bitrix-models/master.svg?style=flat)](https://travis-ci.org/arrilot/bitrix-models)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/arrilot/bitrix-models/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/arrilot/bitrix-models/)

#Bitrix models (in development)

*Данный пакет представляет собой надстройку над традиционным API Битрикса для работы с элементами и разделами инфоблоков, а такжен пользователями.*

## Установка

1. ```composer require arrilot/bitrix-models```
2. Регистрируем пакет в `init.php` - `Arrilot\BitrixModels\ServiceProvider::register();`

Теперь можно создавать свои модели, наследуя их либо от одного из перечисленных классов. 
```php
Arrilot\BitrixModels\Models\ElementModel
Arrilot\BitrixModels\Models\SectionModel
Arrilot\BitrixModels\Models\UserModel
```

## Использование

Для пример далее везде будем рассматривать модель для элемента инфоблока (ElementModel). 
Для других сущностей API практически идентичен.

Создадим, модель для инфоблока Товары.
```php
<?php

use Arrilot\BitrixModels\Models\ElementModel;

class Product extends ElementModel
{
    /**
     * Corresponding iblock id.
     *
     * @return int
     */
    const IBLOCK_ID = 1;
}
```

Для работы модели необходимо лишь задать ID инфоблока в константе.
Для юзеров не требуется и этого.

> Если вы не хотите привязываться к ID инфоблоков, можно переопределить в моделе метод `public static iblockId()` и получать в нём ID инфоблока например по коду. Это дает большую гибкость, но скорее всего вам потребуются дополнительные запросы в БД.

В дальнейшем мы будем использовать наш класс `Product` как в статическом, так и в динамическом контексте.

### Добавление продукта

```php
// $fields - массив, аналогичный передаваемому в CIblockElement::Add()
$product = Product::create($fields);
```

### Обновление

```php
// вариант 1
$product['NAME'] = 'Новое имя продукта';
$product->save();

// вариант 2
$product->update(['NAME' => 'Новое имя продукта']);
```

### Инстанцирование модели без запросов к базе.

Для ряда операций нет необходимости в получении информации из БД, достаточно лишь ID объекта.
В этом случае достаточно инстанцировать объект модели, передав идентификатор.
```php
$product = new Product($id);

//теперь есть возможно работать с моделью, допустим
$product->deactivate();

//объект для текущего пользователя можно получить так:
$user = User::current();
```

### Получение полей из базы

```php
$product = new Product($id);

// метод `get` обращается к базе, только если информация еще не была получена.
$arProduct = $product->get(); // и то и то

// следующие методы принудительно обновляют информацию из 
// базы даже если она уже была получена ранее
$arProps = $product->refreshProps(); // только свойства
$arFields = $product->refreshFields(); // только поля
$arProduct = $product->refresh(); // и то и то
```

Описанные методы не только возвращают данные наружу но и сохраняют их внутри класса `$product`
Объекты-модели реализуют ArrayAccess поэтому с ними можно во многом работать как с массивами.
```php
$product->get();
if ($product['CODE'] === 'test') {
    $product->deactivate();
}
```

### Преобразование моделей в массив/json.

```php
$array = $product->toArray();
$json = $product->toJson();
```

### Получение информации из базы

Наиболее распостраненный сценарий работы с моделями - получение элементов/списков из БД.
Для построение запроса используется "Fluent API", который использует в недрах себя стандартный Битриксовый API.

Для начала построения запроса используется статический метод `::query()`.

Простейший пример:
```php
$products = Product::query()
        ->select('ID')
        ->getList();
```

Любая цепочка запросов должна заканчиваться одним из следующих методов:

1. `->getList()` - получение коллекции (см. http://laravel.com/docs/5.4/collections) объектов. По умолчанию ключом каждого элемента является его ID.
2. `->getById($id)` - получение объекта по его ID.
3. `->first()` - получение одного (самого первого) объекта удовлетворяющего параметрам запроса.
4. `->count()` - получение количества объектов.
5. `->paginate() или ->simplePaginate()` - получение спагинированного списка с мета-данными (см. http://laravel.com/docs/5.4/pagination)
6. Методы для отдельных сущностей:
 `->getByLogin($login)` и `->getByEmail($email)` - получение первого попавшегося юзера с данным логином/email.
 `->getByCode($code)` и `->getByExternalId($id)` - получение первого попавшегося элемента или раздела ИБ по CODE/EXTERNAL_ID

#### Управление выборкой

1. `->sort($array)` - аналог `$arSort` (первого параметра `CIBlockElement::GetList`)

 Примеры:
 
 `->sort(['NAME' => 'ASC', 'ID => 'DESC'])`

 `->sort('NAME', 'DESC') // = ->sort(['NAME' => 'DESC'])`

 `->sort('NAME') // = ->sort(['NAME' => 'ASC'])`


2. `->filter($array)` - аналог `$arFilter`
3. `->navigation($array)`
4. `->select(...)` - аналог `$arSelect`

 Примеры:

 `->select(['ID', 'NAME'])`
 
 `->select('ID', 'NAME')`

 `select()` поддерживает два дополнительных значения - `'FIELDS'` (выбрать все поля), `'PROPS'` (выбрать все свойства).
 Для пользователей также можно указать `'GROUPS'` (добавить группы пользователя в выборку).
 Значение по-умолчанию - `['FIELDS', 'PROPS']`

5. `->limit($int)`, `->take($int)`, `->page($int)`, `->forPage($page, $perPage)` - для навигации 


#### Некоторые дополнительные моменты:

1. Для ограничения выборки добавлены алиасы `limit($value)` (соответсвует `nPageSize`) и `page($num)` (соответсвует `iNumPage`)
2. В некоторых местах API более дружелюбный чем в самом Битриксе. Допустим в фильтре по пользователям не обязательно использовать
`'GROUP_IDS'`. При передаче `'GROUP_ID'` (именно такой ключ требует Битрикс допустим при создании пользователя) или `'GROUPS'` 
результат будет аналогичен.


### Query Scopes

Построитель запросов можно расширять добавляя "query scopes" в модель.
Для этого необходимо создать публичный метод начинаюзищися со `scope`.

Пример "query scope"-a уже присутсвующего в пакете.
```php
    /**
     * Scope to get only active items.
     *
     * @param BaseQuery $query
     *
     * @return BaseQuery
     */
    public function scopeActive($query)
    {
        $query->filter['ACTIVE'] = 'Y';
    
        return $query;
    }

...

$products = Product::query()
                    ->filter(['SECTION_ID' => $secId])
                    ->active()
                    ->getList();
```

В "query scopes" можно также передавать дополнительные параметры.
```php

    /**
     * @param ElementQuery $query
     * @param string|array $category
     *
     * @return ElementQuery
     */
    public function scopeFromCategoryWithCode($query, $category)
    {
        $query->filter['SECTION_CODE'] = $category;

        return $query;
    }

...
$users = Product::query()->fromCategoryWithCode('sale')->getList();
```

Данные скоупы уже присутсвуют в пакете, ими можно пользоваться.

#### Остановка действия

Иногда требуется остановить выборку из базы из query scope. 
Для этого достаточно вернуть false.
Пример:
```php
    public function scopeFromCategory($query, $category)
    {
        if (!$category) {
            return false;
        }
        
        $query->filter['SECTION_CODE'] = $category;

        return $query;
    }
...
```
В результате запрос в базу не будет сделан - `getList()` вернёт пустую коллекцию, `getById()` - false, а `count()` - 0.

> Того же самого эффекта можно добиться вызвав вручную метод `->stopQuery()` 


### Accessors

Временами возникает потребность как то модифицировать данные между выборкой их из базы и получением их из модели.
Для этого используются акссессоры.
Также как и для "query scopes", для добавления аксессора необходимо добавить метод в соответсвующую модель.

Правило именования метода - `$methodName = "get".camelCase($field)."field"`.
Пример акссессора который принудительно делает первую букву имени заглавной:
```php

    public function getNameField($value)
    {
        return ucfirst($value);  
    }
    
    // теперь в $product['NAME'] будет модифицированное значение
    
```

Аксессоры можно создавать также и для несуществущих полей, например:
```php
    public function getFullNameField()
    {
        return $this['NAME']." ".$this['LAST_NAME'];
    }
    
    ...
    
    echo $user['NAME']; // John
    echo $user['LAST_NAME']; // Doe
    echo $user['FULL_NAME']; // John Doe
```

Для того чтобы такие аксессоры отображались в toArray() и toJson() их указать в поле $appends модели.
```php
    protected $appends = ['FULL_NAME'];
```

### События моделей (Model Events)

События позволяют вклиниваться в различные точки жизненного цикла модели и выполнять в них произвольный код.
Например, автоматически проставлять символьный код при создании элемента.

События моделей не используют событийную модель Битрикса.
Обработчик события задается переопределением соответсвующего метода в классе-модели.

```php
class News extends ElementModel
{
    /**
     * Hook into before item create or update.
     *
     * @return mixed
     */
    protected function onBeforeSave()
    {
        $this['CODE'] = CUtil::translit($this['NAME'], "ru");
    }

    /**
     * Hook into after item create or update.
     *
     * @param bool $result
     *
     * @return void
     */
    protected function onAfterSave($result)
    {
        //
    }
}
```

Сигнатуры обработчиков других событий совпадают с приведенными выше.

Список доступных эвентов:

1. `onBeforeCreate` - перед добавлением записи
2. `onAfterCreate` - после добавления записи
3. `onBeforeUpdate` - перед обновлением записи
4. `onAfterUpdate` - после обновления записи
5. `onBeforeSave` - перед добавлением или обновлением записи
6. `onAfterSave` - после добавления или обновления записи
7. `onBeforeDelete` - перед удалением записи
8. `onAfterDelete` - после удаления записи

Если сделать `return false;` из обработчика `onBefore...` то последующее действие будет отменено.