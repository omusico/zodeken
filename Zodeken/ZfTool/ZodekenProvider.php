<?php

require_once 'Zodeken/ZfTool/Exception.php';

/**
 * This class defines a provider for the ZF tool, it allows you generate
 * Data mapper, DbTables, Rowset, Row classes and the ZF controllers, views,
 * forms used for basic CRUD actions.
 *
 * All code is put into ZF application's default folders as guided by ZF.
 *
 * Usage: <code>zf generate zodeken</code>
 *
 * For the provider to be properly loaded, please append the line below into
 * your .zf.ini file:
 *
 *  <code>basicloader.classes.10 = "Zodeken_ZfTool_ZodekenProvider"</code>
 *
 * (The number 10 is the order of the loaded class, it may be another number
 * up to your preferred configs)
 *
 * The .zf.ini file is located at your home folder, if it does not exist,
 * please run the command:
 *
 *  <code>zf --setup config-file</code>
 */

/**
 * Zodeken provider for Zend Tool
 * 
 * @package Zodeken
 * @author Thuan Nguyen <me@ndthuan.com>
 * @copyright Copyright(c) 2011 Thuan Nguyen <me@ndthuan.com>
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt
 */
class Zodeken_ZfTool_ZodekenProvider extends Zend_Tool_Framework_Provider_Abstract
{

    /**
     *
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_db;

    /**
     *
     * @var string
     */
    protected $_dbName;

    /**
     * The package name that would be generated based on the dbName
     *
     * @var string
     */
    protected $_packageName;

    /**
     *
     * @var string
     */
    protected $_cwd;

    /**
     * The shared table definitions that would be set by _analyzeTableDefinitions()
     *
     * @var array
     */
    protected $_tables;

    /**
     * Common types map from mysql to PHP, mainly used for comments
     * @var array
     */
    protected $_mysqlToPhpTypesMap = array(
        'tinyint' => 'integer',
        'smallint' => 'integer',
        'mediumint' => 'integer',
        'int' => 'integer',
        'bigint' => 'integer',
        'float' => 'float',
        'double' => 'float',
        'decimal' => 'float',
        'bit' => 'string',
        'enum' => 'string',
        'set' => 'string',
        'varchar' => 'string',
        'char' => 'string',
        'tinytext' => 'string',
        'mediumtext' => 'string',
        'text' => 'string',
        'longtext' => 'string',
        'binary' => 'string',
        'varbinary' => 'string',
        'blob' => 'string',
        'tinyblob' => 'string',
        'mediumblob' => 'string',
        'longblob' => 'string',
        'date' => 'string',
        'datetime' => 'string',
        'time' => 'string',
        'year' => 'integer',
        'timestamp' => 'string',
    );

    /**
     * Prefix of application's resource classes.
     *
     * @var string
     */
    protected $_appnamespace = 'Application_';

    protected function _createFile($baseDir, $fileName, $code, $allowOverride = false)
    {
        if (!file_exists($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        $filePath = $baseDir . '/' . $fileName;

        if (!$allowOverride && file_exists($filePath)) {
            echo "\033[31mExisting\033[37m: $filePath\n";
            return -1;
        }

        if (@file_put_contents($filePath, $code)) {
            echo "\033[32mCreating\033[37m: $filePath\n";
            return 1;
        }

        return 0;
    }

    /**
     * The public method that would be exposed into ZF tool
     */
    public function generate()
    {
        $currentWorkingDirectory = getcwd();

        // replace the slash just to print a beautiful message :D
        $configFilePath = str_replace(
            '/', DIRECTORY_SEPARATOR, $currentWorkingDirectory
            . '/application/configs/application.ini');

        if (!file_exists($configFilePath)) {

            throw new Zodeken_ZfTool_Exception(
                'Application config file not found: ' . $configFilePath
            );
        }

        $this->_cwd = $currentWorkingDirectory;

        $configs = new Zend_Config_Ini($configFilePath);

        // find db configs in development section
        $dbConfig = $configs->development->resources->db;

        // if not found, find it in production section
        if (null === $dbConfig) {
            $dbConfig = $configs->production->resources->db;
        }

        if (null === $dbConfig) {
            throw new Zodeken_ZfTool_Exception(
                "Db configs not found in your application.ini"
            );
        }

        // get the app namespace
        if ($configs->production->appnamespace) {
            $this->_appnamespace = $configs->production->appnamespace;

            if ($this->_appnamespace[strlen($this->_appnamespace) - 1] !== '_') {

                $this->_appnamespace .= '_';
            }
        }

        $this->_dbName = $dbConfig->params->dbname;
        $this->_packageName = $this->_getCamelCase($this->_dbName);
        $this->_db = Zend_Db::factory($dbConfig);

        $eol = PHP_EOL;

        echo "Which component do you want to generate?{$eol}{$eol}1. DbTables{$eol}2. Mappers{$eol}3. Forms{$eol}4. All{$eol}{$eol}";

        echo "Your choice (4): ";

        $mode = (int) trim(fgets(STDIN));

        if (!$mode) {
            $mode = 4;
        }
        
        echo "Your package name ($this->_packageName):";
        
        $packageName = trim(fgets(STDIN));
        
        if (!empty($packageName)) {
            $this->_packageName = $packageName;
        }

        $this->_analyzeTableDefinitions();

        $modelsDir = $currentWorkingDirectory . '/application/models';
        $formsDir = $currentWorkingDirectory . '/application/forms';

        foreach ($this->_tables as $tableName => $tableDefinition)
        {
            if (1 === $mode || 4 === $mode) {
                $tableCode = $this->_getDbTableCode($tableDefinition);
                $rowCode = $this->_getRowCode($tableDefinition);
                $rowsetCode = $this->_getRowsetCode($tableDefinition);

                $this->_createFile("$modelsDir/DbTable", $tableDefinition['baseClassName'] . '.php', $tableCode, true);
                $this->_createFile("$modelsDir/DbTable/Row", $tableDefinition['baseClassName'] . '.php', $rowCode, true);
                $this->_createFile("$modelsDir/DbTable/Rowset", $tableDefinition['baseClassName'] . '.php', $rowsetCode, true);
            }

            if (!$tableDefinition['isMap']) {
                if ((2 === $mode || 4 === $mode)) {
                    $this->_createFile($modelsDir, $tableDefinition['baseClassName'] . 'Mapper.php', $this->_getMapperCode($tableDefinition), false);
                }

                if ((3 === $mode || 4 === $mode)) {
                    $this->_createFile($formsDir, $tableDefinition['baseClassName'] . '.php', $this->_getFormCode($tableDefinition), false);
                }
            }
        }
    }

    /**
     * Convert a table name to class name.
     *
     * Eg, post -> Model_DbTable_Post, posts_tags => Model_DbTable_PostsTags
     *
     * @param string $tableName
     * @return string
     */
    protected function _getDbTableClassName($tableName)
    {
        return $this->_appnamespace . 'Model_DbTable_'
            . $this->_getCamelCase($tableName);
    }

    /**
     * Convert a table name to a table's row class name.
     *
     * Eg, post -> Model_DbTable_Row_Post, posts_tags => Model_DbTable_Row_PostsTags
     *
     * @param string $tableName
     * @return string
     */
    protected function _getRowClassName($tableName)
    {
        return $this->_appnamespace . 'Model_DbTable_Row_'
            . $this->_getCamelCase($tableName);
    }

    /**
     * Convert a table name to a table's rowset class name.
     *
     * Eg, post -> Model_DbTable_Rowset_Post, posts_tags => Model_DbTable_Rowset_PostsTags
     *
     * @param string $tableName
     * @return string
     */
    protected function _getRowsetClassName($tableName)
    {
        return $this->_appnamespace . 'Model_DbTable_Rowset_'
            . $this->_getCamelCase($tableName);
    }

    /**
     * Convert a table name to a mapper class name.
     *
     * @param string $tableName
     * @return string
     */
    protected function _getMapperClassName($tableName)
    {
        return $this->_appnamespace . 'Model_' . $this->_getCamelCase($tableName) . 'Mapper';
    }

    /**
     * Convert a table name to a form class name.
     *
     * @param string $tableName
     * @return string
     */
    protected function _getFormClassName($tableName)
    {
        return $this->_appnamespace . 'Form_' . $this->_getCamelCase($tableName);
    }

    /**
     * Convert a string to CamelCase format.
     *
     * Underscores are eliminated, each word's first character is capitalized.
     *
     * Eg, post -> Post, posts_tags => PostsTags
     *
     * @param string $string
     * @return string
     */
    protected function _getCamelCase($string)
    {
        $string = str_replace('_', ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);

        return $string;
    }

    /**
     * Convert a string to CamelCase label.
     *
     * Underscores are eliminated, each word's first character is capitalized.
     *
     * Eg, post -> Post, posts_tags => Posts Tags
     *
     * @param string $string
     * @return string
     */
    protected function _getLabel($string)
    {
        $string = str_replace('_', ' ', $string);
        $string = ucwords($string);

        return $string;
    }

    /**
     * Generate form code
     *
     * @param array $tableDefinition
     * @return string
     */
    protected function _getFormCode($tableDefinition)
    {
        $fields = array();

        foreach ($tableDefinition['fields'] as $field)
        {
            $addedCode = null;
            $fieldType = null;
            $referenceTableClass = null;
            $fieldConfigs = array();
            $validators = array();
            $filters = array();

            foreach ($tableDefinition['referenceMap'] as $referenceTable => $reference)
            {
                if ($field['name'] === $reference['columns']) {
                    $fieldType = 'select';
                    $referenceTableClass = $reference['refTableClass'];
                    $baseClass = $this->_getCamelCase($referenceTable);
                }
            }

            if ($field['is_primary_key']) {
                $fieldType = 'hidden';
            } elseif ($referenceTableClass) {
                $addedCode = '$table' . $baseClass . ' = new ' . $referenceTableClass . '();';
                $fieldConfigs[] = "->setLabel('$field[label]')";
                $fieldConfigs[] = '->setMultiOptions(array("" => "- - Select - -") + $table' . $baseClass . '->fetchPairs())';
                $fieldConfigs[] = '->setRequired(true)';
            } else {
                $fieldConfigs[] = "->setLabel('$field[label]')";

                // base on the type and type arguments, add corresponding validators and filters
                switch ($field['type'])
                {
                    case 'set':
                    case 'enum':
                        /**
                         * For example, ENUM('Male', 'Female') would be converted to
                         *
                         * ->setMultiOptions(array("Male" => "Male", "Female" => "Female"))
                         */
                        $numericOptions = eval("return array($field[type_arguments]);");
                        $assocOptions = array();
                        foreach ($numericOptions as $option)
                        {
                            $option = str_replace("'", "\'", $option);
                            $assocOptions[] = "'$option' => '" . ucfirst($option) . "'";
                        }
                        $fieldType = 'radio';
                        $fieldConfigs[] = '->setMultiOptions(array(' . implode(',', $assocOptions) . '))';
                        break;
                    case 'tinytext':
                    case 'mediumtext':
                    case 'text':
                    case 'longtext':
                        $fieldType = 'textarea';
                        $filters[] = 'new Zend_Filter_StringTrim()';
                        $fieldConfigs[] = '->setAttribs(array("cols" => 60, "rows" => 10))';
                        break;
                    case 'tinyint':
                    case 'mediumint':
                    case 'int':
                    case 'bigint':
                    case 'year':
                        $fieldType = 'text';
                        $filters[] = 'new Zend_Filter_StringTrim()';
                        $validators[] = 'new Zend_Validate_Int()';
                        break;
                    case 'decimal':
                    case 'float':
                    case 'double':
                        $fieldType = 'text';
                        $filters[] = 'new Zend_Filter_StringTrim()';
                        $validators[] = 'new Zend_Validate_Float()';
                        break;
                    case 'varchar':
                    case 'char':
                        $validators[] = 'new Zend_Validate_StringLength(array("max" => ' . $field['type_arguments'] . '))';
                        $fieldType = 'text';
                        $filters[] = 'new Zend_Filter_StringTrim()';
                        $fieldConfigs[] = '->setAttrib("size", 50)';
                        break;
                    case 'bit':
                    case 'date':
                    case 'datetime':
                    case 'time':
                    case 'timestamp':
                    default:
                        $fieldType = 'text';
                        $filters[] = 'new Zend_Filter_StringTrim()';
                        break;
                }

                if ($field['is_required']) {
                    $fieldConfigs[] = '->setRequired(true)';
                }
            }

            if ($field['default_value']) {
                $fieldConfigs[] = '->setValue("' . str_replace('"', '\"', $field['default_value']) . '")';
            }

            foreach ($validators as $validator)
            {
                $fieldConfigs[] = '->addValidator(' . $validator . ')';
            }

            foreach ($filters as $filter)
            {
                $fieldConfigs[] = '->addFilter(' . $filter . ')';
            }

            if ($fieldType === 'hidden') {
                $fieldConfigs[] = '->setDecorators($this->hiddenDecorators)';
            } else {
                $fieldConfigs[] = '->setDecorators($this->elementDecorators)';
            }

            $fieldConfigs = implode("\n                ", $fieldConfigs);

            $fieldCode = <<<ELEMENT
        \$this->addElement(
            \$this->createElement('$fieldType', '{$field['name']}')
                $fieldConfigs
        );
ELEMENT;

            if ($addedCode) {
                $fieldCode = '        ' . $addedCode . "\n" . $fieldCode;
            }

            $fields[] = $fieldCode;
        }

        $fields[] = <<<CODE
        \$this->addElement(
            \$this->createElement('button', 'Submit')
                ->setAttrib('type', 'submit')
                ->setDecorators(\$this->buttonDecorators)
        );
CODE;

        $fields = implode("\n\n", $fields);

        return <<<CODE
<?php

/**
 * Form definition for table $tableDefinition[name].
 *
 * @package $this->_packageName
 * @author Zodeken
 * @version \$Id\$
 *
 */
class $tableDefinition[formClassName] extends Zodeken_Form
{
    public function init()
    {
        \$this->setMethod('post');

$fields

        parent::init();
    }
}
CODE;
    }

    /**
     * Generate mapper code
     *
     * @param array $tableDefinition
     * @return string
     */
    protected function _getMapperCode($tableDefinition)
    {
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
    }

    /**
     * Create row class for a table.
     *
     * @param array $tableDefinition
     * @return string
     */
    protected function _getRowCode($tableDefinition)
    {
        $properties = array();
        $functions = array();

        foreach ($tableDefinition['fields'] as $field)
        {
            $type = strtolower($field['type']);

            $type = isset($this->_mysqlToPhpTypesMap[$type]) ? $this->_mysqlToPhpTypesMap[$type] : 'mixed';

            $properties[] = " * @property $type \$$field[name]";
        }

        foreach ($tableDefinition['referenceMap'] as $parentTable => $reference)
        {
            $parentDefinition = $this->_tables[$parentTable];
            $parentTable = $this->_getCamelCase($parentTable);

            $functions[] = <<<FUNCTION
    /**
     * Get a row of $parentTable.
     *
     * @return $parentDefinition[rowClassName]
     */
    public function get{$parentTable}Row()
    {
        return \$this->findParentRow('$parentDefinition[className]');
    }
FUNCTION;
        }

        foreach ($tableDefinition['hasMany'] as $hasManyTable => $mapTable)
        {
            $hasManyDefinition = $this->_tables[$hasManyTable];
            $hasManyTable = $this->_getCamelCase($hasManyTable);
            $mapDefinition = $this->_tables[$mapTable];

            $functions[] = <<<FUNCTION
    /**
     * Get a list of rows of $hasManyTable.
     *
     * @return $hasManyDefinition[rowsetClassName] which contains a list of $hasManyDefinition[rowClassName] instances
     */
    public function get{$hasManyTable}Rows()
    {
        return \$this->findManyToManyRowset('$hasManyDefinition[className]', '$mapDefinition[className]');
    }
FUNCTION;
        }

        foreach ($tableDefinition['dependentTables'] as $childTable)
        {
            $childDefinition = $this->_tables[$childTable];

            // no need to get rows of map table
            if ($childDefinition['isMap']) {
                continue;
            }

            $childTable = $this->_getCamelCase($childTable);

            $functions[] = <<<FUNCTION
    /**
     * Get a list of rows of $childTable.
     *
     * @return array a list of $childDefinition[rowClassName]
     */
    public function get{$childTable}Rows()
    {
        return \$this->findDependentRowset('$childDefinition[className]');
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
 * @package $this->_packageName
 * @author Zodeken
 * @version \$Id\$
 *
$properties
 */
class $tableDefinition[rowClassName] extends Zend_Db_Table_Row_Abstract
{
$functions
}

CODE;
    }

    protected function _getRowsetCode($tableDefinition)
    {
        return <<<CODE
<?php

/**
 * Rowset definition class for table $tableDefinition[name].
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
class $tableDefinition[rowsetClassName] extends Zend_Db_Table_Rowset_Abstract
{
}

CODE;
    }

    /**
     * Create DbTable definition class.
     *
     * @param string $tableDefinition
     * @return string
     */
    protected function _getDbTableCode($tableDefinition)
    {
        $tableName = $tableDefinition['name'];

        $dependentTables = array();

        foreach ($tableDefinition['dependentTables'] as $table)
        {
            $dependentTables[] = $this->_getDbTableClassName($table);
        }

        $primaryKey = "array('" . implode("','", $tableDefinition['primaryKey']) . "')";
        $dependentTables = "array('" . implode("','", $dependentTables) . "')";
        $referencedMap = array();

        foreach ($tableDefinition['referenceMap'] as $referenceTableName => $reference)
        {
            $referenceTableName = $this->_getCamelCase($referenceTableName);

            $referencedMap[] = <<<CODE
        '$referenceTableName' => array(
            'columns' => '$reference[columns]',
            'refTableClass' => '$reference[refTableClass]',
            'refColumns' => '$reference[refColumns]'
        )
CODE;
        }

        $referencedMap = "array(        \n" . implode(",\n\n", $referencedMap) . "\n    )";

        return <<<CODE
<?php

/**
 * Definition class for table $tableName.
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
class $tableDefinition[className] extends Zend_Db_Table_Abstract
{
    /**
     * @var array
     */
    protected \$_schema = '$this->_dbName';

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
    }

    /**
     * Analyze tables structure and relationships.
     *
     * These configurations are used by other methods.
     */
    protected function _analyzeTableDefinitions()
    {
        $tables = array();

        // get the list of tables
        echo "Analyzing tables\n";
        foreach ($this->_db->fetchAll("SHOW TABLES", array(), Zend_Db::FETCH_NUM) as $tableRow)
        {
            $tableName = $tableRow[0];

            $primaryKey = array();
            $fields = array();
            $dependentTables = array();
            $references = array();

            echo "\tAnalyzing table: $tableName\n";
            // loop through the field list
            foreach ($this->_db->fetchAll("SHOW FIELDS FROM $tableName") as $fieldRow)
            {
                /* @var $fieldRow Zend_Db_Table_Row_Abstract */

                // check if the field is listed in the primary key fields
                // strtoupper is probably not necessary, but add it for sure
                $isPrimaryKey = 'PRI' === strtoupper($fieldRow['Key']);

                if ($isPrimaryKey) {
                    $primaryKey[] = $fieldRow['Field'];
                }

                // analyze type definition to find the type name and type arguments
                // for example: ENUM('m','f'), INT(10), VARCHAR(200)...
                $typeAnalyzed = array();
                preg_match('#([a-z_\$]+)(?:\((.+)\))?#', $fieldRow['Type'], $typeAnalyzed);

                $field = array(
                    'name' => $fieldRow['Field'],
                    'label' => $this->_getLabel($fieldRow['Field']),
                    'is_required' => 'YES' === $fieldRow['Null'] ? false : true,
                    'is_primary_key' => $isPrimaryKey,
                    'default_value' => $fieldRow['Default'],
                    'type' => strtolower($typeAnalyzed[1]),
                    'type_arguments' => ''
                );

                if (isset($typeAnalyzed[2])) {
                    $field['type_arguments'] = $typeAnalyzed[2];
                }

                $fields[] = $field;
            }

            echo "\t\tGet table relationships\n";
            // get dependent tables
            foreach ($this->_db->fetchAll("
                SELECT TABLE_SCHEMA, TABLE_NAME
                FROM information_schema.key_column_usage
                WHERE REFERENCED_TABLE_SCHEMA = '$this->_dbName'
                    AND REFERENCED_TABLE_NAME = '$tableName'") as $dependentTable)
            {
                $dependentTables[] = $dependentTable['TABLE_NAME'];
            }

            // get referenced tables
            foreach ($this->_db->fetchAll("
                SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.key_column_usage
                WHERE TABLE_SCHEMA = '$this->_dbName'
                    AND TABLE_NAME = '$tableName'
                    AND REFERENCED_COLUMN_NAME IS NOT NULL
                ") as $referenceTable)
            {
                $references[$referenceTable['REFERENCED_TABLE_NAME']] = array(
                    'columns' => $referenceTable['COLUMN_NAME'],
                    'refTableClass' => $this->_getDbTableClassName($referenceTable['REFERENCED_TABLE_NAME']),
                    'refColumns' => $referenceTable['REFERENCED_COLUMN_NAME'],
                );
            }

            $tables[$tableName] = array(
                'name' => $tableName,
                'className' => $this->_getDbTableClassName($tableName),
                'baseClassName' => $this->_getCamelCase($tableName),
                'rowClassName' => $this->_getRowClassName($tableName),
                'rowsetClassName' => $this->_getRowsetClassName($tableName),
                'mapperClassName' => $this->_getMapperClassName($tableName),
                'formClassName' => $this->_getFormClassName($tableName),
                'primaryKey' => $primaryKey,
                'fields' => $fields,
                'dependentTables' => $dependentTables,
                'referenceMap' => $references,
                // if the primary key consists of 2 columns at least, mark
                // this as a map table
                'isMap' => isset($primaryKey[1]),
                'hasMany' => array(),
            );
        }

        // contains the list of tables that have many of each other
        $manyToManyTables = array();

        // loop again to repair the many-to-many relationships
        foreach ($tables as $tableName => $table)
        {
            if (!$table['isMap']) {
                continue;
            }

            // loop through the references, get the referenced table that has
            // a field linking to the mapped table's primary key
            foreach ($table['referenceMap'] as $referenceTableName => $reference)
            {
                if (in_array($reference['refColumns'], $primaryKey)) {

                    $manyToManyTables[] = $referenceTableName;
                }
            }

            foreach ($manyToManyTables as $tableName1)
            {
                $hasManyClasses = array();

                foreach ($manyToManyTables as $tableName2)
                {
                    if ($tableName1 !== $tableName2) {

                        $hasManyClasses[$tableName2] = $table['name'];
                    }
                }

                $tables[$tableName1]['hasMany'] = $hasManyClasses;
            }
        }

        $this->_tables = $tables;
    }

}
