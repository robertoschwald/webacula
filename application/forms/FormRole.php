<?php
/**
 * Copyright 2010 Yuri Timofeev tim4dev@gmail.com
 *
 * Webacula is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Webacula is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Webacula.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Yuri Timofeev <tim4dev@gmail.com>
 * @package webacula
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU Public License
 *
 */
require_once 'Zend/Form.php';
require_once 'Zend/Form/Element/Submit.php';
require_once 'Zend/Form/Element/Reset.php';


class FormRole extends Zend_Form
{

    protected $translate;
    protected $elDecorators = array('ViewHelper', 'Errors'); // , 'Label'


    public function init()
    {       
        $this->translate = Zend_Registry::get('translate');
        //Zend_Form::setDefaultTranslator( Zend_Registry::get('translate') );
        // set method to POST
        $this->setMethod('post');
        /*
         * hidden fields
         */
        $role_id = $this->addElement('hidden', 'role_id', array(
            'decorators' => $this->elDecorators
        ));
        $action_id = $this->addElement('hidden', 'action_id', array(
            'decorators' => $this->elDecorators
        ));
        /*
         * Order role
         */
        $order_role = $this->createElement('text', 'order_role', array(
            //'decorators' => $this->elDecorators,
            'label'     => $this->translate->_('Order'),
            'required'  => true,
            'size'      => 3,
            'maxlength' => 5
        ));
        /*
         * TODO добавить валидаторы :
         * Int
         * обязательное поле
         */

        /*
         * Name role
         */
        $name_role = $this->createElement('text', 'name_role', array(
            //'decorators' => $this->elDecorators,
            'label'     => $this->translate->_('Name'),
            'required'  => true,
            'size'      => 30,
            'maxlength' => 50
        ));
        /*
         * TODO добавить валидаторы :
         * макс длина 50 симв.
         * только буквы и подчеркивание
         * обязательное поле
         */

        /*
         * Description role
         */
        $description_role = $this->createElement('textarea', 'description_role', array(
            //'decorators' => $this->elDecorators,
            'label'     => $this->translate->_('Description'),
            'required'  => true,
            'cols' => 50,
            'rows' => 3
        ));
        /*
         * TODO добавить валидаторы :
         * обязательное поле
         */

        /*
         * Inherited role id
         */       
        Zend_Loader::loadClass('Wbroles');
        $table = new Wbroles();
        $rows  = $table->fetchAll(null, 'id');
        // create element
        $inherit_id = $this->createElement('select', 'inherit_id', array(
            'label'    => $this->translate->_('Inherited role'),
            'class' => 'ui-select',
            'size' => 10
        ));
        $inherit_id->addMultiOption('', '');
        foreach( $rows as $v) {
            $inherit_id->addMultiOption( $v['id'], $v['name'] );
        }
        unset ($table);
        /*
         * submit button
         */
        $submit = new Zend_Form_Element_Submit('submit',array(
            'decorators' => $this->elDecorators,
            'id'    => 'ok1',
            'class' => 'prefer_btn',
            'label' => $this->translate->_('Submit Form')
        ));
        /*
         * reset button
         */
        $reset = new Zend_Form_Element_Reset('reset',array(
            'decorators' => $this->elDecorators,
            'id'    => 'reset1',
            'label' => $this->translate->_('Cancel')
        ));
        /*
         *  add elements to form
         */
        $this->addElements( array(
            $order_role,
            $name_role,
            $description_role,
            $inherit_id,
            $submit,
            $reset
        ));
    }



}