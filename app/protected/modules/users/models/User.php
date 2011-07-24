<?php
    /*********************************************************************************
     * Zurmo is a customer relationship management program developed by
     * Zurmo, Inc. Copyright (C) 2011 Zurmo Inc.
     *
     * Zurmo is free software; you can redistribute it and/or modify it under
     * the terms of the GNU General Public License version 3 as published by the
     * Free Software Foundation with the addition of the following permission added
     * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
     * IN WHICH THE COPYRIGHT IS OWNED BY ZURMO, ZURMO DISCLAIMS THE WARRANTY
     * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
     *
     * Zurmo is distributed in the hope that it will be useful, but WITHOUT
     * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
     * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
     * details.
     *
     * You should have received a copy of the GNU General Public License along with
     * this program; if not, see http://www.gnu.org/licenses or write to the Free
     * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
     * 02110-1301 USA.
     *
     * You can contact Zurmo, Inc. with a mailing address at 113 McHenry Road Suite 207,
     * Buffalo Grove, IL 60089, USA. or at email address contact@zurmo.com.
     ********************************************************************************/

    class User extends Permitable
    {
        public static function getByUsername($username)
        {
            assert('is_string($username)');
            assert('$username != ""');
            $bean = R::findOne('_user', "username = '$username'");
            assert('$bean === false || $bean instanceof RedBean_OODBBean');
            if ($bean === false)
            {
                throw new NotFoundException();
            }
            return self::makeModel($bean);
        }

        public static function authenticate($username, $password)
        {
            assert('is_string($username)');
            assert('$username != ""');
            assert('is_string($password)');
            $user = User::getByUsername($username);
            if ($user->hash != md5($password))
            {
                throw new BadPasswordException();
            }
            if (Right::ALLOW != $user->getEffectiveRight(
                'UsersModule', UsersModule::RIGHT_LOGIN_VIA_WEB))
            {
                throw new NoRightWebLoginException();
            }
            return $user;
        }

        protected function constructDerived($bean, $setDefaults)
        {
            assert('$bean === null || $bean instanceof RedBean_OODBBean');
            assert('is_bool($setDefaults)');
            // Does a subset of what RedBeanModel::__construct does
            // in order to mix in the Person - this is metadata wise,
            // User doesn't get any functionality from Person.
            $modelClassName = 'Person';
            $tableName = self::getTableName($modelClassName);
            if ($bean === null)
            {
                $personBean = R::dispense($tableName);
            }
            else
            {
                $userBean = $this->getClassBean('User');
                $personBean = R::getBean($userBean, $tableName);
                assert('$personBean !== null');
            }
            $this->setClassBean                  ($modelClassName, $personBean);
            $this->mapAndCacheMetadataAndSetHints($modelClassName, $personBean);
            parent::constructDerived($bean, $setDefaults);
        }

        protected function unrestrictedDelete()
        {
            // Does a subset of what RedBeanModel::unrestrictedDelete
            // does to the classes in the class hierarchy but to Person
            // which is mixed in.
            $modelClassName = 'Person';
            $this->deleteOwnedRelatedModels  ($modelClassName);
            $this->deleteForeignRelatedModels($modelClassName);
            parent::unrestrictedDelete();
        }

        protected function linkBeans()
        {
            // Link the beans up the inheritance hierarchy, skipping
            // the person bean, then link that to the user. So the
            // user is linked to both the person and the permitable,
            // to complete the mixing in of the Person's data.
            $baseBean = null;
            foreach ($this->modelClassNameToBean as $modelClassName => $bean)
            {
                if ($modelClassName == 'Person')
                {
                    continue;
                }
                if ($baseBean !== null)
                {
                    R::$linkManager->link($bean, $baseBean);
                    //if (!RedBeanDatabase::isFrozen())
                    {
                        $tableName  = self::getTableName(get_class($this));
                        $columnName = 'person_id';
                        RedBean_Plugin_Optimizer_Id::ensureIdColumnIsINT11($tableName, $columnName);
                    }
                }
                $baseBean = $bean;
            }
            $userBean   = $this->modelClassNameToBean['User'];
            $personBean = $this->modelClassNameToBean['Person'];
            R::$linkManager->link($userBean, $personBean);
            //if (!RedBeanDatabase::isFrozen())
            {
                $tableName  = self::getTableName(get_class($this));
                RedBean_Plugin_Optimizer_Id::ensureIdColumnIsINT11($tableName, 'person_id');
            }
        }

        // Because no functionality is mixed in, because this is
        // purely and RedBeanModel trick, and php knows nothing about
        // it, a couple fof Person methods must be duplicated in User.
        public function __toString()
        {
            $fullName = $this->getFullName();
            if ($fullName == '')
            {
                return yii::t('Default', '(Unnamed)');
            }
            return $fullName;
        }

        public static function getModuleClassName()
        {
            return 'UsersModule';
        }

        public function getFullName()
        {
            $fullName = array();
            if ($this->firstName != '')
            {
                $fullName[] = $this->firstName;
            }
            if ($this->lastName != '')
            {
                $fullName[] = $this->lastName;
            }
            return join(' ' , $fullName);
        }

        public function save($runValidation = true, array $attributeNames = null)
        {
            $passwordChanged = array_key_exists('hash', $this->originalAttributeValues);
            unset($this->originalAttributeValues['hash']);
            assert('!isset($this->originalAttributeValues["hash"])');
            $saved = parent::save($runValidation, $attributeNames);
            if ($saved && $passwordChanged)
            {
                AuditEvent::logAuditEvent('UsersModule', UsersModule::AUDIT_EVENT_USER_PASSWORD_CHANGED, $this->username);
            }
            return $saved;
        }

        protected function afterSave()
        {
            if (((isset($this->originalAttributeValues['role'])) || $this->isNewModel) &&
                $this->role != null && $this->role->id > 0)
            {
                ReadPermissionsOptimizationUtil::userAddedToRole($this);
            }
            parent::afterSave();
        }

        protected function beforeSave()
        {
            if (parent::beforeSave())
            {
                if (isset($this->originalAttributeValues['role']) && $this->originalAttributeValues['role'][1] > 0)
                {
                    ReadPermissionsOptimizationUtil::userBeingRemovedFromRole($this, Role::getById($this->originalAttributeValues['role'][1]));
                }
                return true;
            }
            else
            {
                return false;
            }
        }

        protected function beforeDelete()
        {
            parent::beforeDelete();
            ReadPermissionsOptimizationUtil::userBeingDeleted($this);
        }

        protected function logAuditEventsListForCreatedAndModifed($newModel)
        {
            if ($newModel)
            {
                // When the first user is created there can be no
                // current user. Log the first user as creating themselves.
                if (Yii::app()->user->userModel == null || !Yii::app()->user->userModel->id > 0)
                {
                    Yii::app()->user->userModel = $this;
                }
                AuditEvent::logAuditEvent('ZurmoModule', ZurmoModule::AUDIT_EVENT_ITEM_CREATED, strval($this), $this);
            }
            else
            {
                AuditUtil::logAuditEventsListForChangedAttributeValues($this);
            }
        }

        public static function getMetadata()
        {
            $defaultMetadata = self::getDefaultMetadata();
            $metadata        = parent::getMetadata();
            $modelClassName = 'Person';
            try
            {
                $globalMetadata = GlobalMetadata::getByClassName($modelClassName);
                $metadata[$modelClassName] = unserialize($globalMetadata->serializedMetadata);
            }
            catch (NotFoundException $e)
            {
                if (isset($defaultMetadata[$modelClassName]))
                {
                    $metadata[$modelClassName] = $defaultMetadata[$modelClassName];
                }
            }
            if (YII_DEBUG)
            {
                self::assertMetadataIsValid($metadata);
            }
            return $metadata;
        }

        public static function setMetadata(array $metadata)
        {
            if (YII_DEBUG)
            {
                self::assertMetadataIsValid($metadata);
            }
            // Save the mixed in Person metadata.
            $modelClassName = 'Person';
            if (isset($metadata[$modelClassName]))
            {
                try
                {
                    $globalMetadata = GlobalMetadata::getByClassName($modelClassName);
                }
                catch (NotFoundException $e)
                {
                    $globalMetadata = new GlobalMetadata();
                    $globalMetadata->className = $modelClassName;
                }
                $globalMetadata->serializedMetadata = serialize($metadata[$modelClassName]);
                $saved = $globalMetadata->save();
                assert('$saved');
            }
            else
            {
                parent::setMetadata($metadata);
            }
        }

        public function setPassword($password)
        {
            assert('is_string($password)');
            $this->hash = md5($password);
        }

        public static function mangleTableName()
        {
            return true;
        }

        protected function untranslatedAttributeLabels()
        {
            return array_merge(parent::untranslatedAttributeLabels(),
                array(
                    'fullName' => 'Name',
                    'timeZone' => 'Time Zone',
                )
            );
        }

        public function getActualRight($moduleName, $rightName)
        {
            assert('is_string($moduleName)');
            assert('is_string($rightName)');
            assert('$moduleName != ""');
            assert('$rightName  != ""');
            if (!SECURITY_OPTIMIZED)
            {
                // The slow way will remain here as documentation
                // for what the optimized way is doing.
                if (Group::getByName(Group::SUPER_ADMINISTRATORS_GROUP_NAME)->contains($this))
                {
                    return Right::ALLOW;
                }
                return parent::getActualRight($moduleName, $rightName);
            }
            else
            {
                // Optimizations work on the database,
                // anything not saved will not work.
                assert('$this->id > 0');
                return intval(ZurmoDatabaseCompatibilityUtil::
                                callFunction("get_user_actual_right({$this->id}, '$moduleName', '$rightName')"));
            }
        }

        public function getPropagatedActualAllowRight($moduleName, $rightName)
        {
            if (!SECURITY_OPTIMIZED)
            {
                return $this->recursiveGetPropagatedActualAllowRight($this->role, $moduleName, $rightName);
            }
            else
            {
                // Optimizations work on the database,
                // anything not saved will not work.
                assert('$this->id > 0');
                return intval(ZurmoDatabaseCompatibilityUtil::
                                callFunction("get_user_propagated_actual_allow_right({$this->id}, '$moduleName', '$rightName')"));
            }
        }

        protected function recursiveGetPropagatedActualAllowRight(Role $role, $moduleName, $rightName)
        {
            if (!SECURITY_OPTIMIZED)
            {
                // The slow way will remain here as documentation
                // for what the optimized way is doing.
                foreach ($role->roles as $subRole)
                {
                    foreach ($subRole->users as $userInSubRole)
                    {
                        if ($userInSubRole->getActualRight($moduleName, $rightName) == Right::ALLOW)
                        {
                            return Right::ALLOW;
                        }
                    }
                    if ($this->recursiveGetPropagatedActualAllowRight($subRole, $moduleName, $rightName) == Right::ALLOW)
                    {
                        return Right::ALLOW;
                    }
                }
                return Right::NONE;
            }
            else
            {
                // It should never get here because the optimized version
                // of getPropagatedActualAllowRight will call
                // get_user_propagated_actual_allow_right.
                throw new NotSupportedException();
            }
        }

        public function getInheritedActualRight($moduleName, $rightName)
        {
            assert('is_string($moduleName)');
            assert('is_string($rightName)');
            assert('$moduleName != ""');
            assert('$rightName  != ""');
            if (!SECURITY_OPTIMIZED)
            {
                return parent::getInheritedActualRight($moduleName, $rightName);
            }
            else
            {
                // Optimizations work on the database,
                // anything not saved will not work.
                assert('$this->id > 0');
                return intval(ZurmoDatabaseCompatibilityUtil::
                                callFunction("get_user_inherited_actual_right({$this->id}, '$moduleName', '$rightName')"));
            }
        }

        protected function getInheritedActualRightIgnoringEveryone($moduleName, $rightName)
        {
            assert('is_string($moduleName)');
            assert('is_string($rightName)');
            assert('$moduleName != ""');
            assert('$rightName  != ""');
            if (!SECURITY_OPTIMIZED)
            {
                // The slow way will remain here as documentation
                // for what the optimized way is doing.
                $combinedRight = Right::NONE;
                foreach ($this->groups as $group)
                {
                    $combinedRight |= $group->getExplicitActualRight                 ($moduleName, $rightName) |
                                      $group->getInheritedActualRightIgnoringEveryone($moduleName, $rightName);
                }
                if (($combinedRight & Right::DENY) == Right::DENY)
                {
                    return Right::DENY;
                }
                assert('in_array($combinedRight, array(Right::NONE, Right::ALLOW))');
                return $combinedRight;
            }
            else
            {
                // It should never get here because the optimized version
                // of getInheritedActualRight will call
                // get_user_inherited_actual_right_ignoring_everyone.
                throw new NotSupportedException();
            }
        }

        protected function getInheritedActualPolicyIgnoringEveryone($moduleName, $policyName)
        {
            assert('is_string($moduleName)');
            assert('is_string($policyName)');
            assert('$moduleName != ""');
            assert('$policyName != ""');
            $values = array();
            foreach ($this->groups as $group)
            {
                $value = $group->getExplicitActualPolicy($moduleName, $policyName);
                if ($value !== null)
                {
                    $values[] = $value;
                }
                else
                {
                    $value = $group->getInheritedActualPolicyIgnoringEveryone($moduleName, $policyName);
                    if ($value !== null)
                    {
                        $values[] = $value;
                    }
                }
            }
            if (count($values) > 0)
            {
                return $moduleName::getStrongerPolicy($policyName, $values);
            }
            return null;
        }

        public static function canSaveMetadata()
        {
            return true;
        }

        public static function getDefaultMetadata()
        {
            // User is going to have a Person bean.
            // As far as Php is concerned User is not a
            // Person - because it isn't inheriting it,
            // but the RedBeanModel essentially uses the
            // Php inheritance to accumulate the data
            // it needs in the getDefaultMetadata() methods
            // to connect everything up in the database
            // in the same order as the inheritance.
            // By getting the person metadata from Person
            // and mixing it into the metadata for User
            // and the construction of User overriding
            // to create and connect the Person bean,
            // the User effectively is a Person from
            // a data point of view.
            $personMetadata = Person::getDefaultMetadata();
            $metadata       = parent::getDefaultMetadata();
            $metadata['Person'] = $personMetadata['Person'];
            $metadata[__CLASS__] = array(
                'members' => array(
                    'username',
                    'hash',
                    'language',
                    'timeZone',
                ),
                'relations' => array(
                    'manager'  => array(RedBeanModel::HAS_ONE,             'User'),
                    'groups'   => array(RedBeanModel::MANY_MANY,           'Group'),
                    'role'     => array(RedBeanModel::HAS_MANY_BELONGS_TO, 'Role'),
                    'currency' => array(RedBeanModel::HAS_ONE,             'Currency'),
                ),
                'foreignRelations' => array(
                    'Dashboard',
                    'Portlet',
                ),
                'rules' => array(
                    array('username', 'required'),
                    array('username', 'unique'),
                    array('username', 'validateUsernameLength'),
                    array('username', 'type',  'type' => 'string'),
                    array('username', 'match',   'pattern' => '/^[^A-Z]+$/', // Not Coding Standard
                                               'message' => 'Username must be lowercase.'),
                    array('username', 'length',  'max'   => 64),
                    array('hash',     'type',    'type' => 'string'),
                    array('hash',     'length',  'min'   => 32, 'max' => 32),
                    array('language', 'type',    'type'  => 'string'),
                    array('language', 'length',  'max'   => 2),
                    array('timeZone', 'type',    'type'  => 'string'),
                    array('timeZone', 'length',  'max'   => 64),
                    array('timeZone', 'default', 'value' => 'UTC'),
                    array('timeZone', 'validateTimeZone'),
                ),
                'elements' => array(
                ),
                'defaultSortAttribute' => 'lastName',
            );
            return $metadata;
        }

        public static function isTypeDeletable()
        {
            return true;
        }

        public function validateUsernameLength($attribute, $params)
        {
            $minLength = $this->getEffectivePolicy('UsersModule', UsersModule::POLICY_MINIMUM_USERNAME_LENGTH);
            if (strlen($this->$attribute) < $minLength)
            {
                $this->addError('username',
                    Yii::t('Default', 'The username is too short. Minimum length is') . '&#160;' . $minLength . '.');
            }
        }

        public function validateTimeZone($attribute, $params)
        {
            if ($this->$attribute != null && new DateTimeZone($this->$attribute) === false)
            {
                $this->addError('timeZone', Yii::t('Default', 'The time zone is invalid.'));
            }
        }
    }
?>