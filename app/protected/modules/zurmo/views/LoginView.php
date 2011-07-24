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

    class LoginView extends View
    {
        private $controller;
        private $formModel;

        public function __construct(CController $controller, CFormModel $formModel)
        {
            $this->controller = $controller;
            $this->formModel  = $formModel;
        }

        protected function renderContent()
        {
            list($form, $formStart) = $this->controller->renderBeginWidget(
                'ZurmoActiveForm',
                array(
                    'id'                   => 'login-form',
                    'enableAjaxValidation' => true,
                    'clientOptions' => array(
                        'validateOnSubmit' => true,
                        'validateOnChange' => false,
                    ),
                )
            );
            $usernameLabel      = $form->labelEx      ($this->formModel, 'username');
            $usernameTextField  = $form->textField    ($this->formModel, 'username');
            $usernameError      = $form->error        ($this->formModel, 'username');

            $passwordLabel      = $form->labelEx      ($this->formModel, 'password');
            $passwordField      = $form->passwordField($this->formModel, 'password');
            $passwordError      = $form->error        ($this->formModel, 'password');

            $rememberMeCheckBox = $form->checkBox     ($this->formModel, 'rememberMe');
            $rememberMeLabel    = $form->label        ($this->formModel, 'rememberMe');
            $rememberMeError    = $form->error        ($this->formModel, 'rememberMe');

            $submitButton       = CHtml::submitButton(Yii::t('Default', 'Login'), array('name' => 'Login', 'id' => 'Login'));

            $fieldsRequiredLabel = Yii::t('Default', 'Fields with') .
                                                      ' <span class="required">*</span> ' .
                                   Yii::t('Default', 'are required.');

            $formEnd = $this->controller->renderEndWidget();

            $content = "<div class=\"form\">$formStart"                                                          .
                       "<div class=\"row\">$usernameLabel$usernameTextField$usernameError</div>"                 .
                       "<div class=\"row\">$passwordLabel$passwordField$passwordError</div>"                     .
                       "<div class=\"row rememberMe\">$rememberMeCheckBox$rememberMeLabel$rememberMeError</div>" .
                       "<div class=\"row buttons\">$submitButton</div>"                                          .
                       "<div class=\"row\"><p class=\"note\">$fieldsRequiredLabel</p></div>"                     .
                       "$formEnd</div>";
            return $content;
        }
    }
?>