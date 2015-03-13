## The structure of the output-config.xml file ##

This file contains a list of 'output' element, each of them represents a file that would be generated following a MySql table. These are required attributes of each output:

  * **templateName:** the template filename that is available under the templates folder
  * **canOverride:** 1 or 0, indicates whether the existing file could be overridden
  * **acceptMapTable:** 1 or 0, indicates whether the output should be generated if the table is a map table (for example, posts\_tags is a map table of posts and tags, and a dedicated form for editing the posts\_tags record is not necessary so it doesn't need to be generated)
  * **outputPath:** the filename of the generated file, there are some variables can be used:
    * **{APPLICATION\_DIR}:** would be replaced by the APPLICATION\_DIR constant
    * **{TABLE\_CAMEL\_NAME}:** the CamelCase-based name of the table, for example PostsTags for the table posts\_tags
    * **{TABLE\_CONTROLLER\_NAME}:** the ZF-compatible controller name, for example posts-tags for the table posts\_tags

## The $tableDefinition variable ##
Each template file is passed with the $tableDefinition variable, this is an associative array consisting of these elements:
  * **name:** the original name of the table
  * **className:** the table class name, e.g. Application\_Model\_Post\_DbTable
  * **classNameAbstract:** the abstract class name of the table, e.g. Application\_Model\_Post\_DbTable\_Abstract
  * **baseClassName:** the CamelCase name of the table, e.g. PostsTags for posts\_tags\_table
  * **controllerName:** the controller name converted from the table name, e.g. posts-tags for posts\_tags table
  * **rowClassName:** the row class name (this is used by the table class), e.g. Application\_Model\_Post\_Row
  * **rowClassNameAbstract:** the abstract class name of the row, e.g. Application\_Model\_Post\_Row\_Abstract
  * **rowsetClassName:** the rowset class name, e.g. Application\_Model\_Post\_Rowset
  * **rowsetClassNameAbstract:** the abstract rowset clas name, e.g. Application\_Model\_Post\_Rowset\_Abstract
  * **mapperClassName:** the mapper class name, e.g. Application\_Model\_PostMapper
  * **formClassName:** the form class name (this file would not be overridden by default), e.g. Application\_Form\_Post
  * **formClassNameLatest:** the latest form class name, this is not used by the application and would be overridden to help you compare this with your edited form to see any difference, e.g. Application\_Form\_Post\_Latest
  * **primaryKey:** the indexed array that is a list of primary key fields, e.g. array('post\_id', 'tag\_id')
  * **dependentTables:** the list of dependent tables, each element is another indexed array with index 0 is the dependent table name and 1 is the name of the columns that links to this table. For example, dependentTables of the member table could be something like this:
> > ```php
array(
array('post', 'member_id'),
array('comment', 'comment_id')
[...]
)```
  * **isMap:** a boolean flag that indicates whether the table is a map table. A table is determined as a map table if its primary key consists of 2 fields that link to 2 other tables
  * **referenceMap:** an associcative array with each key is name of the column that links to another table and the value is an associative array consisting of these elements:
    * **columns:** the column name that links to a referenced table
    * **refTableClass:** name of the referenced table class
    * **refColumns:** name of the linked column in the referenced table
    * **table:** the referenced table name
For example, the 'post' table links to the 'category' table and the referenceMap should be like this:
```php

array(
'category_id' => array(
'columns' => 'category_id',
'refTableClass' => 'Application_Model_Category_DbTable',
'refColumns' => 'id',
'table' => 'category'
)
)```
  * **fields:** list of the columns in the table, each is an associative array with these elements:
    * **name:** the original column name
    * **getFunctionName:** getter function name, e.g. getTitle (without the   parentheses)
    * **setFunctionName:** setter function name, e.g. setTitle (without the parentheses)
    * **label:** form label that is created from the column name, e.g. Title
    * **is\_required:** a boolean indicating that this field is required or not
    * **is\_primary\_key:** a boolean indicating that this field is primary key or not
    * **default\_value:** default value (get this info from the table)
    * **type:** an indexed array:
      * 1: the lowercased mysql type, e.g. varchar
      * 2: arguments of the type if presented, e.g. 100 (taken from varchar(100))
    * **php\_type:** the type that has been converted from mysql type
    * **type\_arguments:** (optional) type arguments (similary to the '2' index of the 'type')
  * **hasMany:** (optional) this is quite complex, I will not explain this temporarily

With the above structure, hope that you can easily change the templates and configs for it to meet your needs.