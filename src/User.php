<?

namespace Arrilot\BitrixModels;

use Exception;

class User extends Model
{
    /**
     * Corresponding object class name.
     *
     * @var string
     */
    protected static $objectClass = 'CUser';

    /**
     * Have groups been already fetched from DB?
     *
     * @var bool
     */
    protected $groupsHaveBeenFetched = false;
    
    /**
     * Constructor.
     *
     * @param $id
     * @param $fields
     *
     * @throws Exception
     */
    public function __construct($id = null, $fields = null)
    {
        global $USER;
        $currentUserId = $USER->getID();

        $id = is_null($id) ? $currentUserId : $id;

        parent::__construct($id, $fields);
    }

    /**
     * Get a new instance for the current user.
     *
     * @param null $fields
     *
     * @return static
     */
    public static function current($fields = null)
    {
        global $USER;

        return new static($USER->getId(), $fields);
    }

    /**
     * Create new user in database.
     *
     * @param $fields
     *
     * @return static
     * @throws Exception
     */
    public static function create($fields)
    {
        $user = static::instantiateObject();
        $id = $user->add($fields);

        if (!$id) {
            throw new Exception($user->LAST_ERROR);
        }

        $fields['ID'] = $id;

        return new static($id, $fields);
    }

    /**
     * CUser::getList substitution.
     *
     * @param array $params
     *
     * @return array
     */
    public static function getList($params = [])
    {
        $object = static::instantiateObject();

        static::normalizeGetListParams($params);

        $users = [];
        $rsUsers = $object->getList($params['sort'], $sortOrder = false, $params['filter'], $params['arParams']);
        while ($arUser = $rsUsers->fetch()) {

            if ($params['withGroups']) {
                $arUser['GROUP_ID'] = $object->getUserGroup($arUser['ID']);
            }

            $listByValue = ($params['listBy'] && isset($arUser[$params['listBy']])) ? $arUser[$params['listBy']] : false;

            if ($listByValue) {
                $users[$listByValue] = $arUser;
            } else {
                $users[] = $arUser;
            }
        }

        return $users;
    }

    /**
     * Normalize params for static::getList().
     *
     * @param $params
     *
     * @return void
     */
    protected static function normalizeGetListParams(&$params)
    {
        $inspectedParamsWithDefaults = [
            'sort'       => ['last_name' => 'asc'],
            'filter'     => [],
            'navigation' => false,
            'select'     => false,
            'withProps'  => false,
            'withGroups' => false,
            'listBy'     => 'ID',
        ];

        foreach ($inspectedParamsWithDefaults as $param => $default) {
            if (!isset($params[$param])) {
                $params[$param] = $default;
            }
        }

        if (!isset($params['arParams'])) {
            $params['arParams'] = [
                'SELECT' => $params['withProps'] === true ? ['UF_*'] : $params['withProps'],
                'NAV_PARAMS' => $params['navigation'],
                'FIELDS' => $params['select']
            ];
        }
    }

    /**
     * Get count of elements that match $filter.
     *
     * @param array $filter
     *
     * @return int
     */
    public static function count($filter = [])
    {
        $object = static::instantiateObject();

        return $object->getList($order = 'ID', $by = 'ASC', $filter, [
            'NAV_PARAMS' => [
                "nTopCount" => 0
            ]
        ])->NavRecordCount;
    }

    /**
     * Get model fields from cache or database.
     *
     * @return array
     */
    public function get()
    {
        if (!$this->hasBeenFetched) {
            $this->fetch();
        }

        $this->getGroups();

        return $this->fields;
    }

    /**
     * Fetch model fields from database and place them to $this->fields.
     *
     * @return array
     * @throws NotSetModelIdException
     */
    protected function fetch()
    {
        if (!$this->id) {
            throw new NotSetModelIdException();
        }

        $this->fields = static::$object->getByID($this->id)->fetch();

        $this->fetchGroups();

        $this->hasBeenFetched = true;

        return $this->fields;
    }

    /**
     * Get user groups from cache or database.
     *
     * @return array
     */
    public function getGroups()
    {
        if ($this->groupsHaveBeenFetched) {
            return $this->fields['GROUP_ID'];
        }

        return $this->fetchGroups();
    }

    /**
     * Fetch user groups and save them to a class field.
     *
     * @return array
     * @throws NotSetModelIdException
     */
    protected function fetchGroups()
    {
        if (!$this->id) {
            throw new NotSetModelIdException();
        }

        global $USER;

        $this->fields['GROUP_ID'] = $this->isCurrent()
            ? $USER->getUserGroupArray()
            : static::$object->getUserGroup($this->id);

        $this->fields['GROUPS'] = $this->fields['GROUP_ID']; // for backward compatibility

        $this->groupsHaveBeenFetched = true;

        return $this->fields['GROUP_ID'];
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin()
    {
        return $this->hasRoleWithId(1);
    }

    /**
     * Check if this user is the operating user.
     */
    public function isCurrent()
    {
        global $USER;

        return $USER->getId() && $this->id == $USER->getId();
    }

    /**
     * Check if user has role with a given ID.
     *
     * @param $role_id
     *
     * @return bool
     */
    public function hasRoleWithId($role_id)
    {
        return in_array($role_id, $this->getGroups());
    }

    /**
     * Check if user is authorized.
     *
     * @return bool
     */
    public function isAuthorized()
    {
        global $USER;

        return ($USER->getId() == $this->id) && $USER->isAuthorized();
    }

    /**
     * Save model to database.
     *
     * @param array $selectedFields save only these fields instead of all.
     *
     * @return bool
     */
    public function save(array $selectedFields = [])
    {
        $fields = $this->collectFieldsForSave($selectedFields);

        return static::$object->update($this->id, $fields);
    }

    /**
     * Create an array of fields that will be saved to database.
     *
     * @param $selectedFields
     *
     * @return array
     */
    protected function collectFieldsForSave($selectedFields)
    {
        $blacklistedFields = [
            'ID',
            'GROUPS',
        ];

        $fields = [];

        foreach ($this->fields as $field => $value) {
            // skip if it is not in selected fields
            if ($selectedFields && !in_array($field, $selectedFields)) {
                continue;
            }

            // skip blacklisted fields
            if (in_array($field, $blacklistedFields)) {
                continue;
            }

            // skip trash fields
            if (substr($field, 0, 1) === '~') {
                continue;
            }

            $fields[$field] = $value;
        }

        return $fields;
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        $field = $key === 'groups' ? 'GROUP_ID' : $key;

        return $this->fields[$field];
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function __set($key, $value)
    {
        $field = $key === 'groups' ? 'GROUP_ID' : $key;

        $this->fields[$field] = $value;
    }
}
