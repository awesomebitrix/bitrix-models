<?php

namespace Arrilot\BitrixModels\Queries;

use Illuminate\Support\Collection;
use Arrilot\BitrixModels\Models\UserModel;

/**
 * @method UserQuery fromGroup($groupId)
 */
class UserQuery extends BaseQuery
{
    /**
     * Query sort.
     *
     * @var array
     */
    public $sort = ['last_name' => 'asc'];

    /**
     * List of standard entity fields.
     *
     * @var array
     */
    protected $standardFields = [
        'ID',
        'PERSONAL_WWW',
        'PERSONAL_ZIP',
        'IS_ONLINE',
        'ACTIVE',
        'PERSONAL_ICQ',
        'PERSONAL_COUNTRY',
        'WORK_CITY',
        'LAST_LOGIN',
        'PERSONAL_GENDER',
        'PERSONAL_NOTES',
        'WORK_STATE',
        'LOGIN',
        'PERSONAL_PHOTO',
        'WORK_COMPANY',
        'WORK_ZIP',
        'EMAIL',
        'PERSONAL_PHONE',
        'WORK_DEPARTMENT',
        'WORK_COUNTRY',
        'NAME',
        'PERSONAL_FAX',
        'WORK_POSITION',
        'WORK_PROFILE',
        'LAST_NAME',
        'PERSONAL_MOBILE',
        'WORK_WWW',
        'WORK_NOTES',
        'SECOND_NAME',
        'PERSONAL_PAGER',
        'WORK_PHONE',
        'ADMIN_NOTES',
        'TIMESTAMP_X',
        'PERSONAL',
        'STREET',
        'WORK_FAX',
        'XML_ID',
        'PERSONAL_BIRTHDAY',
        'PERSONAL_MAILBOX',
        'WORK_PAGER',
        'LAST_NAME',
        'DATE_REGISTER',
        'PERSONAL_CITY',
        'WORK_STREET',
        'SECOND_NAME',
        'PERSONAL_PROFESSION',
        'PERSONAL_STATE',
        'WORK_MAILBOX',
        'STORED_HASH',
        'CHECKWORD_TIME',
        'EXTERNAL_AUTH_ID',
        'CONFIRM_CODE',
        'LOGIN_ATTEMPTS',
        'LAST_ACTIVITY_DATE',
        'AUTO_TIME_ZONE',
        'TIME_ZONE',
        'PASSWORD',
        'CHECKWORD',
        'LID',
    ];

    /**
     * Get the collection of users according to the current query.
     *
     * @return Collection
     */
    public function getList()
    {
        if ($this->queryShouldBeStopped) {
            return new Collection();
        }

        $params = [
            'SELECT'     => $this->propsMustBeSelected() ? ['UF_*'] : false,
            'NAV_PARAMS' => $this->navigation,
            'FIELDS'     => $this->normalizeSelect(),
        ];

        $users = [];
        $rsUsers = $this->bxObject->getList($this->sort, $sortOrder = false, $this->normalizeFilter(), $params);
        while ($arUser = $rsUsers->Fetch()) {
            if ($this->groupsMustBeSelected()) {
                $arUser['GROUP_ID'] = $this->bxObject->getUserGroup($arUser['ID']);
            }

            $this->addItemToResultsUsingKeyBy($users, new $this->modelName($arUser['ID'], $arUser));
        }

        return new Collection($users);
    }

    /**
     * Get the first user with a given login.
     *
     * @param string $login
     *
     * @return UserModel
     */
    public function getByLogin($login)
    {
        $this->filter['LOGIN_EQUAL_EXACT'] = $login;

        return $this->first();
    }

    /**
     * Get the first user with a given email.
     *
     * @param string $email
     *
     * @return UserModel
     */
    public function getByEmail($email)
    {
        $this->filter['EMAIL'] = $email;

        return $this->first();
    }

    /**
     * Get count of users according the current query.
     *
     * @return int
     */
    public function count()
    {
        if ($this->queryShouldBeStopped) {
            return 0;
        }

        return (int) $this->bxObject->getList($order = 'ID', $by = 'ASC', $this->normalizeFilter(), [
            'NAV_PARAMS' => [
                'nTopCount' => 0,
            ],
        ])->NavRecordCount;
    }

    /**
     * Determine if groups must be selected.
     *
     * @return bool
     */
    protected function groupsMustBeSelected()
    {
        return in_array('GROUPS', $this->select) || in_array('GROUP_ID', $this->select) || in_array('GROUPS_ID', $this->select);
    }

    /**
     * Normalize filter before sending it to getList.
     * This prevents some inconsistency.
     *
     * @return array
     */
    protected function normalizeFilter()
    {
        $this->substituteField($this->filter, 'GROUPS', 'GROUPS_ID');
        $this->substituteField($this->filter, 'GROUP_ID', 'GROUPS_ID');

        return $this->filter;
    }

    /**
     * Normalize select before sending it to getList.
     * This prevents some inconsistency.
     *
     * @return array
     */
    protected function normalizeSelect()
    {
        if ($this->fieldsMustBeSelected()) {
            $this->select = array_merge($this->standardFields, $this->select);
        }

        $this->select[] = 'ID';

        return $this->clearSelectArray();
    }
}
