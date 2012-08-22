<?php

return <<<CODE
<?php

/**
 * Data mapper class for table $tableDefinition[name].
 *
 * @package $this->_packageName
 * @author Zodeken
 * @version \$Id\$
 *
 */
class $tableDefinition[mapperClassName]
{
    /**
     *
     * @var $tableDefinition[className]
     */
    protected \$_dbTable;

    public function __construct()
    {
        \$this->_dbTable = new $tableDefinition[className]();
    }

    /**
     *
     * @return $tableDefinition[className]
     */
    public function getDbTabe()
    {
        return \$this->_dbTable;
    }
}

CODE;
?>
