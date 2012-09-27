<?php

return <<<CODE
<h1>Update $tableDefinition[baseClassName]: <?php echo \$this->row->getZodekenAutoLabel(); ?></h1>

<?php
echo \$this->form->setAction(\$_SERVER['REQUEST_URI']);
CODE;
?>
