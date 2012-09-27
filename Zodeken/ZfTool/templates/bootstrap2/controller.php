<?php

$nullReferencesCheck = array();

foreach (array_keys($tableDefinition['referenceMap']) as $refColumn)
{
    $nullReferencesCheck[] = <<<CODE

                if (isset(\$values['$refColumn']) && !\$values['$refColumn']) {
                    \$values['$refColumn'] = new Zend_Db_Expr('NULL');
                }
CODE;
}

$nullReferencesCheck = implode("\n", $nullReferencesCheck);

return <<<CODE
<?php

/**
 * Controller for table $tableDefinition[name]
 *
 * @package $this->_packageName
 * @author Zodeken
 * @version \$Id\$
 *
 */
class {$this->_controllerNamePrefix}$tableDefinition[baseClassName]Controller extends Zend_Controller_Action
{
    public function indexAction()
    {
        \$this->getFrontController()->getRequest()->setParams(\$_GET);
        
        // zsf = zodeken sort field, zso = zodeken sort order
        \$sortField = \$this->_getParam('_sf', '');
        \$sortOrder = \$this->_getParam('_so', '');
        \$pageNumber = \$this->_getParam('page', 1);
        
        \$table$tableDefinition[baseClassName] = new $tableDefinition[className]();
        \$gridSelect = \$table$tableDefinition[baseClassName]->getDbSelectByParams(\$this->_getAllParams(), \$sortField, \$sortOrder);
        \$paginator = Zend_Paginator::factory(\$gridSelect);
        \$paginator->setItemCountPerPage(20)
            ->setCurrentPageNumber(\$pageNumber);
            
        \$this->view->assign(array(
            'paginator' => \$paginator,
            'sortField' => \$sortField,
            'sortOrder' => \$sortOrder,
            'pageNumber' => \$pageNumber,
        ));
        
        foreach (\$this->_getAllParams() as \$paramName => \$paramValue)
        {
            // prepend 'param' to avoid error of setting private/protected members
            \$this->view->assign('param' . \$paramName, \$paramValue);
        }
    }
    
    public function createAction()
    {
        \$form = new $tableDefinition[formClassName]();
            
        if (\$this->_request->isPost()) {
            if (\$form->isValid(\$this->_request->getPost())) {
                \$values = \$form->getValues();
                    
                \$table$tableDefinition[baseClassName] = new $tableDefinition[className]();$nullReferencesCheck
                \$table$tableDefinition[baseClassName]->insert(\$values);
                    
                \$this->_helper->redirector('index');
                exit;
            }
        } else {
            \$form->populate(\$this->_getAllParams());
        }
        
        \$this->view->form = \$form;
    }
    
    public function updateAction()
    {
        \$dest = \$this->_getParam('dest', null);
        \$table$tableDefinition[baseClassName] = new $tableDefinition[className]();
        \$form = new $tableDefinition[formClassName]();
        \$id = (int) \$this->_getParam('id', 0);
        
        \$row = \$table$tableDefinition[baseClassName]->find(\$id)->current();

        if (!\$row) {
            \$this->_helper->redirector('index');
            exit;
        }
            
        if (\$this->_request->isPost()) {
            if (\$form->isValid(\$this->_request->getPost())) {
                \$values = \$form->getValues();$nullReferencesCheck
        
                \$where = array('{$tableDefinition['primaryKey'][0]} = ?' => \$id);
        
                \$table$tableDefinition[baseClassName]->update(\$values, \$where);

                if (\$dest) {
                    \$this->_redirect(\$dest);
                } else {
                    \$this->_helper->redirector('index');
                }
                exit;
            }
        } else {
            
            \$form->populate(\$row->toArray());
        }
        
        \$this->view->form = \$form;
        \$this->view->row = \$row;
        \$this->view->dest = \$dest;
    }
    
    public function deleteAction()
    {
        \$dest = \$this->_getParam('dest', null);
        \$ids = \$this->_getParam('del_id', array());
        
        if (!is_array(\$ids)) {
            \$ids = array(\$ids);
        }
        
        if (!empty(\$ids)) {
            \$table$tableDefinition[baseClassName] = new $tableDefinition[className]();
            \$table$tableDefinition[baseClassName]->deleteMultipleIds(\$ids);
        }
        
        if (\$dest) {
            \$this->_redirect(\$dest);
        } else {
            \$this->_helper->redirector('index');
        }
        exit;
    }
}
CODE;
?>
