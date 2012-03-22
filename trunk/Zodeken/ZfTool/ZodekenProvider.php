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
 * @version $Id$
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
    protected $_formBaseClass = 'Zodeken_Form';

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

    /**
     *
     * @param string $filePath
     * @param string $code
     * @param bool $allowOverride
     * @return integer -1 = existing, 1 = created, 0 = other
     */
    protected function _createFile($filePath, $code, $allowOverride = false)
    {
        $baseDir = pathinfo($filePath, PATHINFO_DIRNAME);

        if (!file_exists($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

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
        $configDir = str_replace(
            '/', DIRECTORY_SEPARATOR, $currentWorkingDirectory . '/application/configs/');

        $configFilePath = $configDir . 'application.ini';

        $backupName = 'application.ini';
        $backupCount = 1;

        // create a backup
        while (file_exists($configDir . "$backupName.$backupCount"))
        {
            ++$backupCount;
        }
        copy($configFilePath, $configDir . "$backupName.$backupCount");

        if (!file_exists($configFilePath)) {

            throw new Zodeken_ZfTool_Exception(
                'Application config file not found: ' . $configFilePath
            );
        }

        $this->_cwd = $currentWorkingDirectory;

        // used to get db configs
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

        // used to modify the file
        $writableConfigs = new Zend_Config_Ini($configFilePath, null, array(
                'skipExtends' => true,
                'allowModifications' => true
            ));

        // get the app namespace
        if ($writableConfigs->production->appnamespace) {
            $this->_appnamespace = $writableConfigs->production->appnamespace;

            if ($this->_appnamespace[strlen($this->_appnamespace) - 1] !== '_') {

                $this->_appnamespace .= '_';
            }
        }

        $this->_dbName = $dbConfig->params->dbname;
        $this->_packageName = $this->_getCamelCase($this->_dbName);
        $this->_db = Zend_Db::factory($dbConfig);

        // modify the config file
        if (!$writableConfigs->zodeken) {
            $writableConfigs->zodeken = array();
        }

        // get package name from config
        if ($writableConfigs->zodeken->packageName) {
            $this->_packageName = $writableConfigs->zodeken->packageName;
        }

        // get form base class from config
        if ($writableConfigs->zodeken->formBaseClass) {
            $this->_formBaseClass = $writableConfigs->zodeken->formBaseClass;
        }

        $eol = PHP_EOL;

        $question = "Which component do you want to generate?{$eol}{$eol}"
            . "1. DbTables{$eol}2. Mappers{$eol}3. Forms{$eol}4. All{$eol}{$eol}";

        $question .= "Your choice (4): ";

        $mode = (int) $this->_readInput($question);

        if (!$mode) {
            $mode = 4;
        }

        $packageName = $this->_readInput("Your package name ($this->_packageName): ");
        $formBaseClass = $this->_readInput("Form's parent class ($this->_formBaseClass): ");

        if (!empty($packageName)) {
            $this->_packageName = $packageName;
        }

        if (!empty($formBaseClass)) {
            $this->_formBaseClass = $formBaseClass;
        }

        // auto-add "Zodeken_" to the autoloadernamespaces directive
        $autoloaderNamespaces = $writableConfigs->production->autoloadernamespaces;

        if (!$autoloaderNamespaces) {
            $autoloaderNamespaces = array('Zodeken_');
        } else {
            $autoloaderNamespaces = $autoloaderNamespaces->toArray();

            if (false === array_search('Zodeken_', $autoloaderNamespaces)) {
                $autoloaderNamespaces[] = 'Zodeken_';
            }
        }

        // modify configs
        $writableConfigs->zodeken->packageName = $this->_packageName;
        $writableConfigs->zodeken->formBaseClass = $this->_formBaseClass;
        $writableConfigs->production->autoloadernamespaces = $autoloaderNamespaces;

        $configWriter = new Zend_Config_Writer_Ini(array(
                'config' => $writableConfigs,
                'filename' => $configFilePath
            ));

        $configWriter->write();

        // some constants like APPLICATION_PATH is replaced with "APPLICATION_PATH"
        // we need to remove the double quotes...
        $this->_preserveIniConfigs($configFilePath);

        echo 'Configs have been written to application.ini', PHP_EOL;
        // end of modifying configs

        $this->_analyzeTableDefinitions();

        $modelsDir = $currentWorkingDirectory . '/application/models';
        $formsDir = $currentWorkingDirectory . '/application/forms';

        foreach ($this->_tables as $tableName => $tableDefinition)
        {
            $tableBaseClassName = $tableDefinition['baseClassName'];

            if (1 === $mode || 4 === $mode) {
                $tableCode = $this->_getDbTableCode($tableDefinition);
                $rowCode = $this->_getRowCode($tableDefinition);
                $rowsetCode = $this->_getRowsetCode($tableDefinition);
                $tableAbstractCode = $this->_getDbTableAbstractCode($tableDefinition);
                $rowAbstractCode = $this->_getRowAbstractCode($tableDefinition);
                $rowsetAbstractCode = $this->_getRowsetAbstractCode($tableDefinition);

                $this->_createFile($modelsDir . '/' . str_replace(array($this->_appnamespace . 'Model_', '_'), array('', '/'), $tableDefinition['classNameAbstract']) . '.php', $tableAbstractCode, true);
                $this->_createFile($modelsDir . '/' . str_replace(array($this->_appnamespace . 'Model_', '_'), array('', '/'), $tableDefinition['rowClassNameAbstract']) . '.php', $rowAbstractCode, true);
                $this->_createFile($modelsDir . '/' . str_replace(array($this->_appnamespace . 'Model_', '_'), array('', '/'), $tableDefinition['rowsetClassNameAbstract']) . '.php', $rowsetAbstractCode, true);

                $this->_createFile($modelsDir . '/' . str_replace(array($this->_appnamespace . 'Model_', '_'), array('', '/'), $tableDefinition['className']) . '.php', $tableCode, false);
                $this->_createFile($modelsDir . '/' . str_replace(array($this->_appnamespace . 'Model_', '_'), array('', '/'), $tableDefinition['rowClassName']) . '.php', $rowCode, false);
                $this->_createFile($modelsDir . '/' . str_replace(array($this->_appnamespace . 'Model_', '_'), array('', '/'), $tableDefinition['rowsetClassName']) . '.php', $rowsetCode, false);
            }

            if (!$tableDefinition['isMap']) {
                if ((2 === $mode || 4 === $mode)) {
                    $this->_createFile($modelsDir . '/' . str_replace(array($this->_appnamespace . 'Model_', '_'), array('', '/'), $tableDefinition['mapperClassName']) . '.php', $this->_getMapperCode($tableDefinition), false);
                }

                if ((3 === $mode || 4 === $mode)) {
                    $this->_createFile($formsDir . '/' . str_replace(array($this->_appnamespace . 'Form_', '_'), array('', '/'), $tableDefinition['formClassName']) . '.php', $this->_getFormCode($tableDefinition), false);
                    $this->_createFile($formsDir . '/' . str_replace(array($this->_appnamespace . 'Form_', '_'), array('', '/'), $tableDefinition['formClassNameLatest']) . '.php', $this->_getFormCode($tableDefinition, true), true);
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
        return $this->_appnamespace . 'Model_'
            . $this->_getCamelCase($tableName) . '_DbTable';
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
        return $this->_appnamespace . 'Model_'
            . $this->_getCamelCase($tableName) . '_Row';
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
        return $this->_appnamespace . 'Model_'
            . $this->_getCamelCase($tableName) . '_Rowset';
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
     * Convert a table name to a form class name ('latest' version).
     *
     * @param string $tableName
     * @return string
     */
    protected function _getFormLatestClassName($tableName)
    {
        return $this->_appnamespace . 'Form_Edit'
            . $this->_getCamelCase($tableName) . '_Latest';
    }

    /**
     * Convert a table name to a form class name.
     *
     * @param string $tableName
     * @return string
     */
    protected function _getFormClassName($tableName)
    {
        return $this->_appnamespace . 'Form_Edit' . $this->_getCamelCase($tableName);
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
    protected function _getFormCode($tableDefinition, $isNew = false)
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
                    $baseClass = $this->_getCamelCase($reference['table']);
                }
            }

            if ($field['is_primary_key']) {
                $fieldType = 'hidden';

                $fieldsConfigs[] = '->setAttrib("class", "hidden-input")';

            } elseif ($referenceTableClass) {

                $addedCode = '$table' . $baseClass . ' = new ' . $referenceTableClass . '();';

                $fieldConfigs[] = "->setLabel('$field[label]')";
                $fieldConfigs[] = '->setMultiOptions(array("" => "- - Select - -") + $table' . $baseClass . '->fetchPairs())';

                if ($field['is_required']) {
                    $fieldConfigs[] = '->setRequired(true)';
                }

                $fieldsConfigs[] = '->setAttrib("class", "element-input")';
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
                        $array = 'array(' . implode(',', $assocOptions) . ')';
                        $fieldType = 'radio';
                        $fieldConfigs[] = '->setMultiOptions($array)';
                        $validators[] = "new Zend_Validate_InArray(array('haystack' => $array))";
                        $fieldConfigs[] = '->setSeparator(" ")';
                        break;
                    case 'tinytext':
                    case 'mediumtext':
                    case 'text':
                    case 'longtext':
                        $fieldType = 'textarea';
                        $filters[] = 'new Zend_Filter_StringTrim()';
                        break;
                    case 'tinyint':
                    case 'mediumint':
                    case 'int':
                    case 'year':
                        $fieldType = 'text';
                        $filters[] = 'new Zend_Filter_StringTrim()';
                        $validators[] = 'new Zend_Validate_Int()';
                        break;
                    case 'decimal':
                    case 'float':
                    case 'double':
                    case 'bigint':
                        $fieldType = 'text';
                        $filters[] = 'new Zend_Filter_StringTrim()';
                        $validators[] = 'new Zend_Validate_Float()';
                        break;
                    case 'varchar':
                    case 'char':
                        $validators[] = 'new Zend_Validate_StringLength(array("max" => ' . $field['type_arguments'] . '))';
                        $fieldType = 'text';
                        $filters[] = 'new Zend_Filter_StringTrim()';
                        $fieldConfigs[] = '->setAttrib("maxlength", ' . $field['type_arguments'] . ')';

                        if ('email' === strtolower($field['name']) || 'emailaddress' === strtolower($field['name'])) {
                            $validators[] = 'new Zend_Validate_EmailAddress()';
                        }
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

                $fieldsConfigs[] = '->setAttrib("class", "element-input")';
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

            if ('Zodeken_Form' === $this->_formBaseClass) {
                if ($fieldType === 'hidden') {
                    $fieldConfigs[] = '->setDecorators($this->hiddenDecorators)';
                } else {
                    $fieldConfigs[] = '->setDecorators($this->elementDecorators)';
                }
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

        $buttonDecorators = '';

        if ('Zodeken_Form' === $this->_formBaseClass) {
            $buttonDecorators = '
                ->setDecorators($this->buttonDecorators)';
        }

        $fields[] = <<<CODE
        \$this->addElement(
            \$this->createElement('button', 'submit')
                ->setLabel('Submit')
                ->setAttrib('type', 'submit')$buttonDecorators
        );
CODE;

        $fields = implode("\n\n", $fields);

        $className = $isNew ? $tableDefinition['formClassNameLatest'] : $tableDefinition['formClassName'];

        $addedComments = '';

        if ($isNew) {
            $addedComments = "
 *
 * This is your latest code that generated by Zodeken. Use this to compare with
 * your current version to see changes.
 *
 * Do NOT write anything in this file, it will be removed when you regenerated.";
        }
        return <<<CODE
<?php

/**
 * Form definition for table $tableDefinition[name].$addedComments
 *
 * @package $this->_packageName
 * @author Zodeken
 * @version \$Id\$
 *
 */
class $className extends $this->_formBaseClass
{
    public function init()
    {
        \$this->setMethod('post')
            ->setAttrib('class', '$this->_formBaseClass');

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

    protected function _getRowCode($tableDefinition)
    {
        return <<<CODE
<?php

/**
 * Row definition class for table $tableDefinition[name].
 *
 * @package $this->_packageName
 * @author Zodeken
 * @version \$Id\$
 *
 */
class $tableDefinition[rowClassName] extends $tableDefinition[rowClassNameAbstract]
{
    // write your custom functions here
}

CODE;
    }

    /**
     * Create row class for a table.
     *
     * @param array $tableDefinition
     * @return string
     */
    protected function _getRowAbstractCode($tableDefinition)
    {
        $properties = array();
        $functions = array();
        $functionNames = array();

        foreach ($tableDefinition['fields'] as $field)
        {
            $type = strtolower($field['type']);

            $type = isset($this->_mysqlToPhpTypesMap[$type]) ? $this->_mysqlToPhpTypesMap[$type] : 'mixed';
            $fieldNameCamel = $this->_getCamelCase($field['name']);

            $properties[] = " * @property $type \$$field[name]";
            $functions[] = <<<FUNCTION
    /**
     * Set value for '$field[name]' field
     *
     * @param $type \$valueOf$fieldNameCamel
     *
     * @return $tableDefinition[rowClassName]
     */
    public function set$fieldNameCamel(\$valueOf$fieldNameCamel)
    {
        \$this->$field[name] = \$valueOf$fieldNameCamel;
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

        foreach ($tableDefinition['referenceMap'] as $column => $reference)
        {
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

        foreach ($tableDefinition['hasMany'] as $hasManyTable)
        {
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

        foreach ($tableDefinition['dependentTables'] as $childTable)
        {
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
$properties
 */
abstract class $tableDefinition[rowClassNameAbstract] extends Zend_Db_Table_Row_Abstract
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
 */
class $tableDefinition[rowsetClassName] extends $tableDefinition[rowsetClassNameAbstract]
{
    // write your custom functions here
}

CODE;
    }

    protected function _getRowsetAbstractCode($tableDefinition)
    {
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
    }

    protected function _getDbTableCode($tableDefinition)
    {
        return <<<CODE
<?php

/**
 * Definition class for table $tableDefinition[name].
 *
 * @package $this->_packageName
 * @author Zodeken
 * @version \$Id\$
 */
class $tableDefinition[className] extends $tableDefinition[classNameAbstract]
{
    // write your custom functions here
}
CODE;
    }

    /**
     * Create DbTable definition class.
     *
     * @param string $tableDefinition
     * @return string
     */
    protected function _getDbTableAbstractCode($tableDefinition)
    {
        $tableName = $tableDefinition['name'];

        $dependentTables = array();

        foreach ($tableDefinition['dependentTables'] as $table)
        {
            $dependentTables[] = $this->_getDbTableClassName($table[0]);
        }
        
        $pkCode = '';
        
        if (!empty($tableDefinition['primaryKey'])) {
            $pkCode = "'" . implode("','", $tableDefinition['primaryKey']) . "'";
        }

        $primaryKey = "array($pkCode)";
        $dependentTables = "array('" . implode("','", $dependentTables) . "')";
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
            foreach ($this->_db->fetchAll("SHOW FIELDS FROM `$tableName`") as $fieldRow)
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
                SELECT TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME
                FROM information_schema.key_column_usage
                WHERE REFERENCED_TABLE_SCHEMA = '$this->_dbName'
                    AND REFERENCED_TABLE_NAME = '$tableName'") as $dependentTable)
            {
                $dependentTables[] = array($dependentTable['TABLE_NAME'], $dependentTable['COLUMN_NAME']);
            }

            $foreignKeyInPrimaryKeyCount = 0;

            // get referenced tables
            foreach ($this->_db->fetchAll("
                SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.key_column_usage
                WHERE TABLE_SCHEMA = '$this->_dbName'
                    AND TABLE_NAME = '$tableName'
                    AND REFERENCED_COLUMN_NAME IS NOT NULL
                ") as $referenceTable)
            {
                if (in_array($referenceTable['COLUMN_NAME'], $primaryKey)) {
                    $foreignKeyInPrimaryKeyCount++;
                }

                $references[$referenceTable['COLUMN_NAME']] = array(
                    'columns' => $referenceTable['COLUMN_NAME'],
                    'refTableClass' => $this->_getDbTableClassName($referenceTable['REFERENCED_TABLE_NAME']),
                    'refColumns' => $referenceTable['REFERENCED_COLUMN_NAME'],
                    'table' => $referenceTable['REFERENCED_TABLE_NAME']
                );
            }

            $tables[$tableName] = array(
                'name' => $tableName,
                'className' => $this->_getDbTableClassName($tableName),
                'classNameAbstract' => $this->_getDbTableClassName($tableName) . '_Abstract',
                'baseClassName' => $this->_getCamelCase($tableName),
                'rowClassName' => $this->_getRowClassName($tableName),
                'rowClassNameAbstract' => $this->_getRowClassName($tableName) . '_Abstract',
                'rowsetClassName' => $this->_getRowsetClassName($tableName),
                'rowsetClassNameAbstract' => $this->_getRowsetClassName($tableName) . '_Abstract',
                'mapperClassName' => $this->_getMapperClassName($tableName),
                'formClassName' => $this->_getFormClassName($tableName),
                'formClassNameLatest' => $this->_getFormLatestClassName($tableName),
                'primaryKey' => $primaryKey,
                'fields' => $fields,
                'dependentTables' => $dependentTables,
                'referenceMap' => $references,
                // if the primary key consists of 2 columns at least, mark
                // this as a map table
                'isMap' => $foreignKeyInPrimaryKeyCount > 1,
                'hasMany' => array(),
            );
        }

        // loop again to repair the many-to-many relationships
        foreach ($tables as $tableName => $table)
        {
            // we just find many-to-many from a map table, so if table is not a
            // map, we'll skip it
            if (!$table['isMap']) {
                continue;
            }

            $inRelationships = array();

            // loop through the references, get the referenced table that has
            // a field linking to the mapped table's primary key
            foreach ($table['referenceMap'] as $column => $reference)
            {
                // if the column of this table is one of the composite key,
                // we consider its refereced table as a table that has a
                // many-to-many relationship with another table
                if (in_array($column, $table['primaryKey'])) {
                    $inRelationships[] = array($reference['table'], $column);
                }
            }

            $tables[$inRelationships[0][0]]['hasMany'][$inRelationships[0][1]] = array($inRelationships[1][0], $inRelationships[1][1], $table['name']);
            $tables[$inRelationships[1][0]]['hasMany'][$inRelationships[1][1]] = array($inRelationships[0][0], $inRelationships[0][1], $table['name']);
        }

        $this->_tables = $tables;
    }

    /**
     * Preserve some special constants in application.ini file
     *
     * @param string $iniFilename
     */
    protected function _preserveIniConfigs($iniFilename)
    {
        $ini = file_get_contents($iniFilename);

        //$ini = preg_replace('#"([A-Z_]{2,})#s', '\1 "', $ini);

        $ini = str_replace('"APPLICATION_PATH/', 'APPLICATION_PATH "/', $ini);
        // "0" -> 0, "1" => 1...
        $ini = preg_replace('#= "(\d+)"#si', '= \1', $ini);

        file_put_contents($iniFilename, $ini);
    }

    /**
     * Show the question and retrieve answer from user
     *
     * @param string $question
     * @return string
     */
    protected function _readInput($question)
    {
        echo $question;

        return trim(fgets(STDIN));
    }

}