- Supports only MySQL.

- Generates Controllers with CRUD actions, Db\_Tables, DbTable\_Rows, DbTable\_Rowsets with DocBlock tags that gives you autocompletion when coding in an IDE.

- Generates DbTables, DbTable\_Rows, DbTable\_Rowsets with DocBlock tags that gives you autocompletion when coding in an IDE.

- Defines DbTable relationships following InnoDB's relationships.
```php


protected $_primary = array('id');

protected $_dependentTables = array('Application_Model_DbTable_PostsTags');

protected $_referenceMap = array(
'Category' => array(
'columns' => 'category_id',
'refTableClass' => 'Application_Model_DbTable_Category',
'refColumns' => 'id'
),

'Member' => array(
'columns' => 'owner_id',
'refTableClass' => 'Application_Model_DbTable_Member',
'refColumns' => 'id'
)
);
```
- Adds some commonly used methods to DbTable: fetchPairs (an array of
key => value pairs), fetchOne (value of first column's first field), fetchOnes
(an array that is a list of fetchOne)

- Detects the relationships between the tables and generates basically necessary
methods.

  * One-to-many: `$memberRow->getPostRowsByPosterId()`, `$postRow->getMemberRowByPosterId()`
  * Many-to-many: `$tagRow->getPostRowset()`, `$postRow->getTagRowset()`

- Uses proper form element for each data type. For example: enum/set -> radio, varchar/char... -> text, text/longtext... -> textarea, foreign key -> select...

- Adds validators and filters depending on data type of the field.

For example,
  * If the column is marked as "Not Null", `->setRequired(true)` will be added to the form element
  * If the column is VARCHAR(100), `->addValidator(new Zend_Validate_StringLength(array("max" => 100)))` will be added to the form

- Adds a drop-down list for a foreign key field to the form:
```php

$tableCategory = new Application_Model_DbTable_Category();
$this->addElement(
$this->createElement('select', 'category_id')
->setLabel('Category Id')
->setMultiOptions(array("" => "- - Select - -")
+ $tableCategory->fetchPairs())
->setRequired(true)
->setDecorators($this->elementDecorators)
);
```