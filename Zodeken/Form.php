<?php

/**
 * Zend_Form decorators definition
 *
 * @package Zodeken
 * @author Thuan Nguyen <me@ndthuan.com>
 * @copyright Copyright(c) 2011 Thuan Nguyen <me@ndthuan.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt
 * @version $Id$
 */
class Zodeken_Form extends Zend_Form
{

    public $hiddenDecorators = array(
        'ViewHelper',
        array(array('data' => 'HtmlTag'), array('tag' => 'span'))
    );
    public $buttonDecorators = array(
        'ViewHelper',
        array(array('data' => 'HtmlTag'), array('tag' => 'td', 'class' => 'element')),
        array(array('label' => 'HtmlTag'), array('tag' => 'td', 'placement' => 'prepend')),
        array(array('row' => 'HtmlTag'), array('tag' => 'tr')),
    );
    public $elementDecorators = array(
        'ViewHelper',
        'Description',
        'Errors',
        array(array('elementDiv' => 'HtmlTag'), array('tag' => 'div', 'class' => 'element')),
        array(array('td' => 'HtmlTag'), array('tag' => 'td')),
        array('Label', array('tag' => 'td')),
        array(array('row' => 'HtmlTag'), array('tag' => 'tr')),
    );
    public $imageDecorators = array(
        'ViewHelper',
        array(array('data' => 'HtmlTag'), array('tag' => 'td', 'class' => 'image')),
        array(array('label' => 'HtmlTag'), array('tag' => 'td', 'placement' => 'prepend')),
        array(array('row' => 'HtmlTag'), array('tag' => 'tr')),
    );

    public function init()
    {
        parent::init();

        $this->setDecorators(array(
            'FormElements',
            array('HtmlTag', array('tag' => 'table')),
            'Form',
        ));
    }

}