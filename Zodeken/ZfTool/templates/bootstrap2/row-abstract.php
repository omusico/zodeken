<?php

$properties = array();
$functions = array();
$functionNames = array();
$autoLabelField = null;

foreach ($tableDefinition['fields'] as $field) {
    $type = strtolower($field['type']);
    
    if (null === $autoLabelField && 'varchar' === $type) {
        $autoLabelField = $field['name'];
    }
    
    $type = isset($this->_mysqlToPhpTypesMap[$type]) ? $this->_mysqlToPhpTypesMap[$type] : 'mixed';
    
    $fieldNameCamel = $this->_getCamelCase($field['name']);

    $properties[] = " * @property $type \$$field[name]";
    $functions[] = <<<FUNCTION
    /**
     * Set value for '$field[name]' field
     *
     * @param $type \$$fieldNameCamel
     *
     * @return $tableDefinition[rowClassName]
     */
    public function set$fieldNameCamel(\$$fieldNameCamel)
    {
        \$this->$field[name] = \$$fieldNameCamel;
        return \$this;
    }

    /**
     * Get value of '$field[name]' field
     *
     * @return $type
     */
    public function get$fieldNameCamel()
    {
        return \$this->$field[name];
    }
FUNCTION;
}

if (empty($autoLabelField)) {
    $autoLabelField = $tableDefinition['fields'][0]['name'];
}

foreach ($tableDefinition['referenceMap'] as $column => $reference) {
    $parentTable = $reference['table'];
    $parentDefinition = $this->_tables[$parentTable];
    $parentTable = $this->_getCamelCase($parentTable);

    $functionName = "get{$parentTable}RowBy" . $this->_getCamelCase($column);

    if (isset($functionNames[$functionName])) {
        continue;
    } else {
        $functionNames[$functionName] = 0;
    }

    $functions[] = <<<FUNCTION
    /**
     * Get a row of $parentTable.
     *
     * @return $parentDefinition[rowClassName]
     */
    public function $functionName()
    {
        return \$this->findParentRow('$parentDefinition[className]', '$column');
    }
FUNCTION;
}

foreach ($tableDefinition['hasMany'] as $hasManyTable) {
    $hasManyTableName = $hasManyTable[0];
    $hasManyTableColumn = $hasManyTable[1];
    $mapTableName = $hasManyTable[2];

    $hasManyDefinition = $this->_tables[$hasManyTableName];
    $mapDefinition = $this->_tables[$mapTableName];

    $hasManyTableName = $this->_getCamelCase($hasManyTableName);
    $functionName = "get{$hasManyTableName}Rowset";

    if (isset($functionNames[$functionName])) {
        continue;
    } else {
        $functionNames[$functionName] = 0;
    }

    $functions[] = <<<FUNCTION
    /**
     * Get a list of rows of $hasManyTableName.
     *
     * @return $hasManyDefinition[rowsetClassName]
     */
    public function $functionName()
    {
        return \$this->findManyToManyRowset('$hasManyDefinition[className]', '$mapDefinition[className]', '$hasManyTableColumn');
    }
FUNCTION;
}

foreach ($tableDefinition['dependentTables'] as $childTable) {
    $childTableName = $childTable[0];
    $childDefinition = $this->_tables[$childTableName];

    // no need to get rows of map table
    if ($childDefinition['isMap']) {
        continue;
    }

    $childTableName = $this->_getCamelCase($childTableName);

    $functionName = "get{$childTableName}RowsBy" . $this->_getCamelCase($childTable[1]);

    if (isset($functionNames[$functionName])) {
        continue;
    } else {
        $functionNames[$functionName] = 0;
    }

    $functions[] = <<<FUNCTION
    /**
     * Get a list of rows of $childTableName.
     *
     * @return $childDefinition[rowsetClassName]
     */
    public function $functionName()
    {
        return \$this->findDependentRowset('$childDefinition[className]', '$childTable[1]');
    }
FUNCTION;
}

$properties = implode("\n", $properties);
$functions = implode("\n\n", $functions);

return <<<CODE
<?php

/**
 * Row definition class for table $tableDefinition[name].
 *
 * Do NOT write anything in this file, it will be removed when you regenerated.
 *
 * @package $this->_packageName
 * @author Zodeken
 * @version \$Id\$
 *
 * @method $tableDefinition[rowClassName] setFromArray(\$data)
 *
$properties
 */
abstract class $tableDefinition[rowClassNameAbstract] extends Zend_Db_Table_Row_Abstract
{
$functions
    
    /**
     * Get the label that has been auto-detected by Zodeken
     *
     * @return string
     */
    public function getZodekenAutoLabel()
    {
        return \$this->$autoLabelField;
    }
}

CODE;
?>
