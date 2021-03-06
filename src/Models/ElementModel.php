<?php

namespace Arrilot\BitrixModels\Models;

use Arrilot\BitrixModels\Queries\ElementQuery;
use Exception;

class ElementModel extends BaseModel
{
    /**
     * Corresponding IBLOCK_ID
     *
     * @var int
     */
    const IBLOCK_ID = null;

    /**
     * Corresponding object class name.
     *
     * @var string
     */
    protected static $objectClass = 'CIBlockElement';

    /**
     * Have sections been already fetched from DB?
     *
     * @var bool
     */
    protected $sectionsAreFetched = false;

    /**
     * Getter for corresponding iblock id.
     *
     * @throws Exception
     *
     * @return int
     */
    public static function iblockId()
    {
        $id = static::IBLOCK_ID;
        if (!$id) {
            throw new Exception('You must set IBLOCK_ID constant inside a model or override iblockId() method');
        }
        
        return $id;
    }

    /**
     * Corresponding section model full qualified class name.
     * MUST be overridden if you are going to use section model for this iblock.
     *
     * @throws Exception
     *
     * @return string
     */
    public static function sectionModel()
    {
        throw new Exception('public static function sectionModel() MUST be overridden');
    }

    /**
     * Instantiate a query object for the model.
     *
     * @return ElementQuery
     */
    public static function query()
    {
        return new ElementQuery(static::instantiateObject(), get_called_class());
    }

    /**
     * Scope to sort by date.
     *
     * @param ElementQuery $query
     * @param string       $sort
     *
     * @return ElementQuery
     */
    public function scopeSortByDate($query, $sort = 'DESC')
    {
        return $query->sort(['ACTIVE_FROM' => $sort]);
    }

    /**
     * Scope to get only items from a given section.
     *
     * @param ElementQuery $query
     * @param mixed        $id
     *
     * @return ElementQuery
     */
    public function scopeFromSectionWithId($query, $id)
    {
        $query->filter['SECTION_ID'] = $id;

        return $query;
    }

    /**
     * Scope to get only items from a given section.
     *
     * @param ElementQuery $query
     * @param string       $code
     *
     * @return ElementQuery
     */
    public function scopeFromSectionWithCode($query, $code)
    {
        $query->filter['SECTION_CODE'] = $code;

        return $query;
    }

    /**
     * Fill extra fields when $this->field is called.
     *
     * @return null
     */
    protected function afterFill()
    {
        $this->normalizePropertyFormat();
    }

    /**
     * Get all model attributes from cache or database.
     *
     * @return array
     */
    public function get()
    {
        $this->getFields();

        return $this->fields;
    }

    /**
     * Get element's sections from cache or database.
     *
     * @return array
     */
    public function getSections()
    {
        if ($this->sectionsAreFetched) {
            return $this->fields['IBLOCK_SECTION'];
        }

        return $this->refreshSections();
    }

    /**
     * Refresh model from database and place data to $this->fields.
     *
     * @return array
     */
    public function refresh()
    {
        return $this->refreshFields();
    }

    /**
     * Refresh element's fields and save them to a class field.
     *
     * @return array
     */
    public function refreshFields()
    {
        if ($this->id === null) {
            return  $this->fields = [];
        }

        $sectionsBackup = isset($this->fields['IBLOCK_SECTION']) ? $this->fields['IBLOCK_SECTION'] : null;

        $this->fields = static::query()->getById($this->id)->fields;

        if (!empty($sectionsBackup)) {
            $this->fields['IBLOCK_SECTION'] = $sectionsBackup;
        }

        $this->fieldsAreFetched = true;

        return $this->fields;
    }

    /**
     * Refresh element's sections and save them to a class field.
     *
     * @return array
     */
    public function refreshSections()
    {
        if ($this->id === null) {
            return [];
        }

        $this->fields['IBLOCK_SECTION'] = static::$bxObject->getElementGroups($this->id, true);
        $this->sectionsAreFetched = true;

        return $this->fields['IBLOCK_SECTION'];
    }

    /**
     * Get element direct section as ID or array of fields.
     *
     * @param bool $withProps
     *
     * @return false|int|array
     */
    public function getSection($withProps = false)
    {
        $fields = $this->getFields();
        if (!$withProps) {
            return $fields['IBLOCK_SECTION_ID'];
        }

        /** @var SectionModel $sectionModel */
        $sectionModel = static::sectionModel();
        if (!$fields['IBLOCK_SECTION_ID']) {
            return false;
        }

        return $sectionModel::query()->getById($fields['IBLOCK_SECTION_ID'])->toArray();
    }

    /**
     * Get element direct section as model object.
     *
     * @param bool $withProps
     *
     * @return false|SectionModel
     */
    public function section($withProps = false)
    {
        $fields = $this->getFields();

        /** @var SectionModel $sectionModel */
        $sectionModel = static::sectionModel();

        return $withProps
            ? $sectionModel::query()->getById($fields['IBLOCK_SECTION_ID'])
            : new $sectionModel($fields['IBLOCK_SECTION_ID']);
    }

    /**
     * Save props to database.
     * If selected is not empty then only props from it are saved.
     *
     * @param array $selected
     *
     * @return bool
     */
    public function saveProps($selected = [])
    {
        $propertyValues = $this->constructPropertyValuesForSave($selected);
        if (empty($propertyValues)) {
            return false;
        }

        $bxMethod = empty($selected) ? 'setPropertyValues' : 'setPropertyValuesEx';
        static::$bxObject->$bxMethod(
            $this->id,
            static::iblockId(),
            $propertyValues
        );

        return true;
    }

    /**
     * Normalize properties's format converting it to 'PROPERTY_"CODE"_VALUE'.
     *
     * @return null
     */
    protected function normalizePropertyFormat()
    {
        if (empty($this->fields['PROPERTIES'])) {
            return;
        }

        foreach ($this->fields['PROPERTIES'] as $code => $prop) {
            $this->fields['PROPERTY_'.$code.'_VALUE'] = $prop['VALUE'];
            $this->fields['~PROPERTY_'.$code.'_VALUE'] = $prop['~VALUE'];
            $this->fields['PROPERTY_'.$code.'_DESCRIPTION'] = $prop['DESCRIPTION'];
            $this->fields['~PROPERTY_'.$code.'_DESCRIPTION'] = $prop['~DESCRIPTION'];
            $this->fields['PROPERTY_'.$code.'_VALUE_ID'] = $prop['PROPERTY_VALUE_ID'];
        }
    }

    /**
     * Construct 'PROPERTY_VALUES' => [...] from flat fields array.
     * This is used in save.
     * If $selectedFields are specified only those are saved.
     *
     * @param $selectedFields
     *
     * @return array
     */
    protected function constructPropertyValuesForSave($selectedFields = [])
    {
        $propertyValues = [];
        $saveOnlySelected = !empty($selectedFields);
        foreach ($this->fields as $code => $value) {
            if ($saveOnlySelected && !in_array($code, $selectedFields)) {
                continue;
            }

            if (preg_match('/^PROPERTY_(.*)_VALUE$/', $code, $matches) && !empty($matches[1])) {
                $propertyValues[$matches[1]] = $value;
            }
        }

        return $propertyValues;
    }
}
