<?php

return <<<CODE
<?php

/**
 * Rowset definition class for table $tableDefinition[name].
 *
 * Do NOT write anything in this file, it will be removed when you regenerated.
 *
 * @package $this->_packageName
 * @author Zodeken
 * @version \$Id\$
 *
 * @method $tableDefinition[rowClassName] current()
 * @method $tableDefinition[rowClassName] getRow(int \$position, bool \$seek = false)
 * @method $tableDefinition[className] getTable()
 * @method $tableDefinition[rowClassName] offsetGet(string \$offset)
 * @method $tableDefinition[rowsetClassName] rewind()
 * @method $tableDefinition[rowsetClassName] seek(int \$position)
 * @method bool setTable($tableDefinition[className] \$table)
 *
 */
abstract class $tableDefinition[rowsetClassNameAbstract] extends Zend_Db_Table_Rowset_Abstract
{
}

CODE;
?>
