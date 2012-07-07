<?php

$tableName = $tableDefinition['name'];

$dependentTables = array();

foreach ($tableDefinition['dependentTables'] as $table)
{
    $dependentTables[] = $this->_getDbTableClassName($table[0]);
}

$primaryKey = var_export($tableDefinition['primaryKey'], true);
$dependentTables = var_export($dependentTables, true);
$referencedMap = array();

foreach ($tableDefinition['referenceMap'] as $column => $reference)
{
    $referencedMap[] = <<<CODE
        '$column' => array(
            'columns' => '$reference[columns]',
            'refTableClass' => '$reference[refTableClass]',
            'refColumns' => '$reference[refColumns]'
        )
CODE;
}

$referencedMap = "array(        \n" . implode(",\n\n", $referencedMap) . "\n    )";


$getDbSelectByParamsWheres = array();
$getDbSelectByParamsSearchableWheres = array();
$createDefaultRow = array();

foreach ($tableDefinition['fields'] as $field)
{
    $createDefaultRow[$field['name']] = $field['default_value'];
    
    $getDbSelectByParamsWheres[] = <<<CODE
        if (isset(\$params['$field[name]']) && !empty(\$params['$field[name]'])) {
            \$select->where('$field[name] = ?', \$params['$field[name]']);
        }
CODE;

    if ('char' === substr($field['type'], -4) || 'text' === substr($field['type'], -4)) {
        $getDbSelectByParamsSearchableWheres[] = <<<CODE
            if ('all' === \$searchMode || '$field[name]' === \$searchMode) {
                \$searchWheres[] = \$dbAdapter->quoteInto('$field[name] LIKE ?', "%\$keywords%");
            }
CODE;
    }
}

$createDefaultRow = var_export($createDefaultRow, true);
$getDbSelectByParamsWheres = implode("\n\n", $getDbSelectByParamsWheres);
$getDbSelectByParamsSearchableWheres = implode("\n\n", $getDbSelectByParamsSearchableWheres);


return <<<CODE
<?php

/**
 * Definition class for table $tableName.
 *
 * Do NOT write anything in this file, it will be removed when you regenerated.
 *
 * @package $this->_packageName
 * @author Zodeken
 * @version \$Id\$
 *
 * @method $tableDefinition[rowClassName] createRow(array \$data, string \$defaultSource = null)
 * @method $tableDefinition[rowsetClassName] fetchAll(string|array|Zend_Db_Table_Select \$where = null, string|array \$order = null, int \$count = null, int \$offset = null)
 * @method $tableDefinition[rowClassName] fetchRow(string|array|Zend_Db_Table_Select \$where = null, string|array \$order = null, int \$offset = null)
 * @method $tableDefinition[rowsetClassName] find()
 *
 */
abstract class $tableDefinition[classNameAbstract] extends Zend_Db_Table_Abstract
{
    /**
     * @var string
     */
    protected \$_name = '$tableDefinition[name]';

    /**
     * @var array
     */
    protected \$_primary = $primaryKey;

    /**
     * @var array
     */
    protected \$_dependentTables = $dependentTables;

    /**
     * @var array
     */
    protected \$_referenceMap = $referencedMap;

    /**
     * @var string
     */
    protected \$_rowClass = '$tableDefinition[rowClassName]';

    /**
     * @var string
     */
    protected \$_rowsetClass = '$tableDefinition[rowsetClassName]';

    /**
     * Get the table name
     *
     * @return string
     */
    public function getName()
    {
        return \$this->_name;
    }
        
    /**
     * Create a row object with default values
     *
     * @return $tableDefinition[rowClassName]
     */
    public function createDefaultRow()
    {
        return \$this->createRow($createDefaultRow);
    }
        
    /**
     * Delete multiple Ids
     *
     * @param array \$ids
     */
    public function deleteMultipleIds(\$ids = array())
    {
        if (empty(\$ids) || empty(\$this->_primary)) {
            return;
        }
        
        \$this->delete(\$this->_primary[0] . ' IN (' . implode(',', \$ids) . ')');
    }

    /**
     * Get Db_Select for pagination by params sent from controller
     *
     * @param array \$params
     * @param string \$sortField
     * @param string \$sortOrder
     * @return Zend_Db_Select
     */
    public function getDbSelectByParams(\$params = array(), \$sortField = '', \$sortOrder = '')
    {
        \$select = \$this->select(true);
        
        if (\$sortField != '' && \$sortOrder != '') {
            if ('desc' === strtolower(\$sortOrder)) {
                \$sortOrder = 'DESC';
            } else {
                \$sortOrder = 'ASC';
            }
            \$select->order("\$sortField \$sortOrder");
        }
        
$getDbSelectByParamsWheres
        
        // _kw = keywords, _sm = search mode
        if (isset(\$params['_kw']) && !empty(\$params['_kw'])) {
            \$dbAdapter = \$this->getAdapter();
            \$searchWheres = array();
            \$keywords = \$params['_kw'];
            \$searchMode = isset(\$params['_sm']) && !empty(\$params['_sm']) ? \$params['_sm'] : 'all';
            
$getDbSelectByParamsSearchableWheres
                
            if (!empty(\$searchWheres)) {
                \$select->where(implode(' OR ', \$searchWheres));
            }
        }
            
        return \$select;
    }

    /**
     * Used to fetch a rowset and build an associative array from it.
     *
     * The first column is used as key and the second column is used as corresponding value.
     *
     * @param string|array|Zend_Db_Table_Select \$where  OPTIONAL An SQL WHERE clause or Zend_Db_Table_Select object.
     * @param string|array                      \$order  OPTIONAL An SQL ORDER clause.
     * @param int                               \$count  OPTIONAL An SQL LIMIT count.
     * @param int                               \$offset OPTIONAL An SQL LIMIT offset.
     * @return array
     */
    public function fetchPairs(\$where = null, \$order = null, \$count = null, \$offset = null)
    {
        \$return = array();

        if (!(\$where instanceof Zend_Db_Table_Select)) {
            \$select = \$this->select();

            if (\$where !== null) {
                \$this->_where(\$select, \$where);
            }

            if (\$order !== null) {
                \$this->_order(\$select, \$order);
            }

            if (\$count !== null || \$offset !== null) {
                \$select->limit(\$count, \$offset);
            }

        } else {
            \$select = \$where;
        }

        \$stmt = \$this->_db->query(\$select);
        \$rows = \$stmt->fetchAll(Zend_Db::FETCH_NUM);

        if (count(\$rows) == 0) {
            return array();
        }

        foreach (\$rows as \$row)
        {
            \$return[\$row[0]] = \$row[1];
        }

        return \$return;
    }

    /**
     * Fetch the first field's value of the first row.
     *
     * @param string|array|Zend_Db_Table_Select \$where  OPTIONAL An SQL WHERE clause or Zend_Db_Table_Select object.
     * @param string|array                      \$order  OPTIONAL An SQL ORDER clause.
     * @param int                               \$offset OPTIONAL An SQL OFFSET value.
     * @return mixed value of the first row's first column or null if no rows found.
     */
    public function fetchOne(\$where = null, \$order = null, \$offset = null)
    {
        if (!(\$where instanceof Zend_Db_Table_Select)) {
            \$select = \$this->select();

            if (\$where !== null) {
                \$this->_where(\$select, \$where);
            }

            if (\$order !== null) {
                \$this->_order(\$select, \$order);
            }

            \$select->limit(1, ((is_numeric(\$offset)) ? (int) \$offset : null));

        } else {
            \$select = \$where->limit(1, \$where->getPart(Zend_Db_Select::LIMIT_OFFSET));
        }

        \$stmt = \$this->_db->query(\$select);
        \$rows = \$stmt->fetchAll(Zend_Db::FETCH_NUM);

        if (count(\$rows) == 0) {
            return null;
        }

        return \$rows[0][0];
    }

    /**
     * Fetch first column's values of all rows.
     *
     * @param string|array|Zend_Db_Table_Select \$where  OPTIONAL An SQL WHERE clause or Zend_Db_Table_Select object.
     * @param string|array                      \$order  OPTIONAL An SQL ORDER clause.
     * @param int                               \$count  OPTIONAL An SQL LIMIT count.
     * @param int                               \$offset OPTIONAL An SQL LIMIT offset.
     * @return array List of values.
     */
    public function fetchOnes(\$where = null, \$order = null, \$count = null, \$offset = null)
    {
        \$return = array();

        if (!(\$where instanceof Zend_Db_Table_Select)) {
            \$select = \$this->select();

            if (\$where !== null) {
                \$this->_where(\$select, \$where);
            }

            if (\$order !== null) {
                \$this->_order(\$select, \$order);
            }

            if (\$count !== null || \$offset !== null) {
                \$select->limit(\$count, \$offset);
            }

        } else {
            \$select = \$where;
        }

        \$stmt = \$this->_db->query(\$select);
        \$rows = \$stmt->fetchAll(Zend_Db::FETCH_NUM);

        if (count(\$rows) == 0) {
            return array();
        }

        foreach (\$rows as \$row)
        {
            \$return[] = \$row[0];
        }

        return \$return;
    }
}

CODE;
