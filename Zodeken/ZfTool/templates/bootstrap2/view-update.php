<?php

$urlHelperModulePart = '';
if ($this->_moduleName) {
    $urlHelperModulePart = "'module' => '$this->_moduleName',";
}

return <<<CODE
<?php
\$this->headTitle('Update $tableDefinition[baseClassName]: ' . 
    \$this->row->getZodekenAutoLabel());
\$form = \$this->form;
?>
<div class="page-header">
   <h1>Update $tableDefinition[baseClassName]: <?php echo \$this->row->getZodekenAutoLabel(); ?></h1>
</div>

<form class="form-horizontal" method="post" action="<?php echo \$this->url(); ?>">
    <?php
    /* @var \$form Zend_Form */
    foreach (\$form->getElements() as \$element):
        /* @var \$element Zend_Form_Element */
        \$errors = \$element->getMessages();
        \$elementName = \$element->getName();
        ?>
        <div class="control-group<?php if (\$errors) echo ' error'; ?>">
            <?php if (\$element->getType() != 'Zend_Form_Element_Button'): ?>
                <label class="control-label" for="<?php echo \$element->getId(); ?>"><?php echo \$element->getLabel(); ?></label>
            <?php endif; ?>
            <div class="controls">
                <?php echo \$element->renderViewHelper(); ?>
                <?php if (\$errors): ?>
                    <span class="help-inline"><?php echo implode(', ', \$errors); ?></span>
                <?php endif; ?>
                <?php if ('submit' == \$elementName): ?>
                    <a class="btn" href="<?php echo \$this->url(array($urlHelperModulePart'controller' => '$tableDefinition[controllerName]'), null, true); ?>">Cancel</a>
                    <?php endif; ?>
            </div>
        </div>
        <?php
        endforeach;
        ?>
</form>
CODE;
?>
