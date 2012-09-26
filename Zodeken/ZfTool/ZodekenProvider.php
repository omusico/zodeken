<?php

// PHP < 5.3 compatibility
!defined('__DIR__') || define('__DIR__', dirname(__FILE__));

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

    const GENERATE_DB_TABLES = 1;
    const GENERATE_MAPPERS = 2;
    const GENERATE_FORMS = 4;
    const GENERATE_CRUD = 8;

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
     * The module name of generated code, set to null or empty to disable
     * 
     * @var string 
     */
    protected $_moduleName;

    /**
     * The controller name prefix
     * 
     * @var string
     */
    protected $_controllerNamePrefix = '';

    /**
     * Current working directory
     * 
     * @var string
     */
    protected $_cwd;

    /**
     * Zodeken directory
     * 
     * @var string
     */
    protected $_zodekenDir = __DIR__;

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
     * @var string
     */
    protected $_outputTemplate = 'default';

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
        $relativePath = str_replace($this->_cwd . '/', '', $filePath);

        if (!file_exists($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        if (!$allowOverride && file_exists($filePath)) {
            echo "\033[31mExisting\033[37m: $relativePath\n";
            return -1;
        }

        if (@file_put_contents($filePath, $code)) {
            echo "\033[32mCreating\033[37m: $relativePath\n";
            return 1;
        } else {
            echo "\033[31mFAILED creating\033[37m: $relativePath\n";
        }

        return 0;
    }

    /**
     * The public method that would be exposed into ZF tool
     * @param boolean $force
     * @param string $tableList
     * @throws Zodeken_ZfTool_Exception
     * @throws Exception
     */
    public function generate($force = 0, $tableList = '')
    {
        $currentWorkingDirectory = getcwd();
        $shouldUpdateConfigFile = false;

        // replace the slash just to print a beautiful message :D
        $configDir = str_replace(
                '/', DIRECTORY_SEPARATOR, $currentWorkingDirectory . '/application/configs/');

        $configFilePath = $configDir . 'application.ini';

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
            $shouldUpdateConfigFile = true;
        }

        // get package name from config
        if ($writableConfigs->zodeken->packageName) {
            $this->_packageName = $writableConfigs->zodeken->packageName;
        }

        // get package name from config
        if ($writableConfigs->zodeken->outputTemplate) {
            $this->_outputTemplate = $writableConfigs->zodeken->outputTemplate;
        }

        $eol = PHP_EOL;

        // load the output files config
        $zodekenDir = dirname(__FILE__);
        $outputs = array();
        $outputGroups = array();
        $asciiChar = 97;
        $allKeys = array();

        if ($force) {
            echo "\033[1;31mATTENTION! Zodeken will overwrite existing files!\033[0;37m";
        }

        $templates = array_map('basename', glob($zodekenDir . '/templates/*', GLOB_ONLYDIR));
        $templateQuestion = "\nEnter output template\n\n";
        foreach ($templates as $templateName)
        {
            $templateQuestion .= "    $templateName\n";
        }
        $templateQuestion .= "\nYour choice ($this->_outputTemplate): ";
        $template = $this->_readInput($templateQuestion);

        if ('' === $template) {
            $template = $this->_outputTemplate;
        }

        if (!is_dir($zodekenDir . "/templates/$template")) {
            throw new Exception('Invalid template');
        }

        $this->_outputTemplate = $template;
        
        // parse the config file
        $xdoc = new DOMDocument();
        $xdoc->load($zodekenDir . '/templates/' . $this->_outputTemplate . '/output-config.xml');

        $question = array("
Which files do you want to generate?
- Enter 1 to generate all
- Enter a list of groups, e.g. crud,form...
- Or enter a list of individual template file keys, e.g. a,b,c,d...
- You can combine groups with file keys, e.g. crud,h,i... {$eol}    ");

        foreach ($xdoc->getElementsByTagName('outputGroup') as $outputGroupElement)
        {
            /* @var $outputGroupElement DOMElement */

            $outputGroupName = $outputGroupElement->getAttribute('name');

            $outputGroups[$outputGroupName] = array();

            $question[] = $outputGroupName;

            foreach ($outputGroupElement->getElementsByTagName('output') as $outputElement)
            {
                $output = array(
                    'key' => strtolower(chr($asciiChar++)),
                    'templateName' => $outputElement->getAttribute('templateName'),
                    'templateFile' => $zodekenDir . '/templates/' . $this->_outputTemplate . '/' . $outputElement->getAttribute('templateName'),
                    'canOverride' => (int) $outputElement->getAttribute('canOverride'),
                    'outputPath' => $outputElement->getAttribute('outputPath'),
                    'acceptMapTable' => $outputElement->getAttribute('acceptMapTable'),
                );

                $outputs[$output['key']] = $output;
                $outputGroups[$outputGroupName][] = $output;

                $allKeys[] = $output['key'];

                $question[] = '    ' . $output['key'] . '. ' . $output['templateName'];
            }
        }

        $question = implode($eol . '    ', $question) . "{$eol}{$eol}Your choice: ";

        do {
            $input = strtolower(trim($this->_readInput($question)));
        } while ('' === $input);

        if ('1' == $input) {
            $keys = $allKeys;
        } elseif ($input) {
            $expectedOutputs = preg_split('#\s*,\s*#', $input);
            $keys = array();
            foreach ($expectedOutputs as $expectedOutput)
            {
                if (isset($outputGroups[$expectedOutput])) {
                    foreach ($outputGroups[$expectedOutput] as $output)
                    {
                        $keys[] = $output['key'];
                    }
                } elseif (in_array($expectedOutput, $allKeys)) {
                    $keys[] = $expectedOutput;
                }
            }
            $keys = array_unique($keys);
        } else {
            $keys = array();
        }

        $this->_outputTemplate = $template;

        if ($writableConfigs->zodeken->outputTemplate != $this->_outputTemplate) {
            $writableConfigs->zodeken->outputTemplate = $this->_outputTemplate;
            $shouldUpdateConfigFile = true;
        }

        $packageName = $this->_readInput("Your package name ($this->_packageName): ");

        if (!empty($packageName)) {
            $this->_packageName = $packageName;
            if ($writableConfigs->zodeken->packageName != $this->_packageName) {
                $writableConfigs->zodeken->packageName = $this->_packageName;
                $shouldUpdateConfigFile = true;
            }
        }

        // module support has been suggested by Brian Gerrity (bgerrity73@gmail.com)
        $moduleName = $this->_readInput("Module name (leave empty for default): ");

        if (!empty($moduleName)) {
            $this->_moduleName = $moduleName;
            $this->_controllerNamePrefix = $this->_getCamelCase($moduleName) . '_';
            $this->_appnamespace = $this->_getCamelCase($moduleName) . '_';
        }

        // auto-add "resources.frontController.moduleDirectory" if module is specified
        if (!empty($this->_moduleName)) {
            if (!$writableConfigs->production->resources->frontController->moduleDirectory) {
                $writableConfigs->production->resources->frontController->moduleDirectory = 'APPLICATION_PATH/modules';
                $shouldUpdateConfigFile = true;
            }

            if (!isset($writableConfigs->production->resources->modules)) {
                $writableConfigs->production->resources->modules = '';
                $shouldUpdateConfigFile = true;
            }
        }

        // auto-add "Zodeken_" to the autoloadernamespaces directive
        $autoloaderNamespaces = $writableConfigs->production->autoloadernamespaces;

        if (!$autoloaderNamespaces) {
            $autoloaderNamespaces = array('Zodeken_');
            $writableConfigs->production->autoloadernamespaces = $autoloaderNamespaces;
            $shouldUpdateConfigFile = true;
        } else {
            $autoloaderNamespaces = $autoloaderNamespaces->toArray();

            if (false === array_search('Zodeken_', $autoloaderNamespaces)) {
                $autoloaderNamespaces[] = 'Zodeken_';
                $writableConfigs->production->autoloadernamespaces = $autoloaderNamespaces;
                $shouldUpdateConfigFile = true;
            }
        }

        if ($shouldUpdateConfigFile) {
            $configWriter = new Zend_Config_Writer_Ini(array(
                        'config' => $writableConfigs,
                        'filename' => $configFilePath
                    ));

            $backupName = 'application.ini';
            $backupCount = 1;

            // create a backup
            while (file_exists($configDir . "$backupName.$backupCount"))
            {
                ++$backupCount;
            }
            copy($configFilePath, $configDir . "$backupName.$backupCount");

            $configWriter->write();
        }

        // some constants like APPLICATION_PATH is replaced with "APPLICATION_PATH"
        // we need to remove the double quotes...
        $this->_preserveIniConfigs($configFilePath);

        echo 'Configs have been written to application.ini', PHP_EOL;
        // end of modifying configs

        $this->_analyzeTableDefinitions($tableList);

        $moduleBaseDirectory = $currentWorkingDirectory . '/application';

        if (!empty($this->_moduleName)) {
            $moduleBaseDirectory .= '/modules/' . $this->_moduleName;
        }
        
        
        if (!empty($tableList)) {
            $tableList = explode(',', $tableList);
        }

        foreach ($this->_tables as $tableName => $tableDefinition)
        {
            if (!empty($tableList) && !in_array($tableName, $tableList)) {
                continue;
            }
            
            $tableBaseClassName = $tableDefinition['baseClassName'];

            foreach ($keys as $key)
            {
                $output = $outputs[$key];
                
                if ($tableDefinition['isMap'] && !$output['acceptMapTable']) {
                    continue;
                }

                $fileName = $output['outputPath'];
                $canOverride = $output['canOverride'];
                $templateFile = $output['templateFile'];

                $code = require $templateFile;

                $fileName = str_replace('{MODULE_BASE_DIR}', $moduleBaseDirectory, $fileName);
                $fileName = str_replace('{APPLICATION_DIR}', $moduleBaseDirectory, $fileName);
                $fileName = str_replace('{TABLE_CAMEL_NAME}', $tableDefinition['baseClassName'], $fileName);
                $fileName = str_replace('{TABLE_CONTROLLER_NAME}', $tableDefinition['controllerName'], $fileName);

                $this->_createFile($fileName, $code, $force ? true : $canOverride);
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
        $string = str_replace(array('_', '-'), ' ', $string);
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
                    'getFunctionName' => 'get' . $this->_getCamelCase($fieldRow['Field']),
                    'setFunctionName' => 'set' . $this->_getCamelCase($fieldRow['Field']),
                    'label' => $this->_getLabel($fieldRow['Field']),
                    'is_required' => 'YES' === $fieldRow['Null'] ? false : true,
                    'is_primary_key' => $isPrimaryKey,
                    'default_value' => $fieldRow['Default'],
                    'type' => strtolower($typeAnalyzed[1]),
                    'php_type' => isset($this->_mysqlToPhpTypesMap[$typeAnalyzed[1]]) ? $this->_mysqlToPhpTypesMap[$typeAnalyzed[1]] : 'string',
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
                'controllerName' => str_replace('_', '-', $tableName),
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