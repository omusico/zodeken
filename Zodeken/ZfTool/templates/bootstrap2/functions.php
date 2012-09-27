<?php

function getCamelCase($string)
{
    $string = str_replace(array('_', '-'), ' ', $string);
    $string = ucwords($string);
    $string = str_replace(' ', '', $string);

    return $string;
}

function getDbTableClassName($tableName, $appNamespace)
{
    return $appNamespace . 'Model_' . getCamelCase($tableName) . 'Table';
}

function getDbTableAbstractClassName($tableName, $appNamespace)
{
    return $appNamespace . 'Model_Abstract_' . getCamelCase($tableName) . 'TableAbstract';
}

function getRowClassName($tableName, $appNamespace)
{
    return $appNamespace . 'Model_' . getCamelCase($tableName) . 'Row';
}

function getRowAbstractClassName($tableName, $appNamespace)
{
    return $appNamespace . 'Model_Abstract_' . getCamelCase($tableName) . 'RowAbstract';
}

function getRowsetClassName($tableName, $appNamespace)
{
    return $appNamespace . 'Model_' . getCamelCase($tableName) . 'Rowset';
}

function getRowsetAbstractClassName($tableName, $appNamespace)
{
    return $appNamespace . 'Model_Abstract_' . getCamelCase($tableName) . 'RowsetAbstract';
}

function getMapperClassName($tableName, $appNamespace)
{
    return $appNamespace . 'Model_' . getCamelCase($tableName) . 'Mapper';
}

function getFormLatestClassName($tableName, $appNamespace)
{
    return $appNamespace . 'Form_Latest_Edit' . getCamelCase($tableName);
}

function getFormClassName($tableName, $appNamespace)
{
    return $appNamespace . 'Form_Edit' . getCamelCase($tableName);
}

function getLabel($string)
{
    $string = str_replace('_', ' ', $string);
    $string = ucwords($string);

    return $string;
}