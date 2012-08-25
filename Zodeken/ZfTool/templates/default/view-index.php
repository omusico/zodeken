<?php

$urlHelperModulePart = '';
if ($this->_moduleName) {
    $urlHelperModulePart = "'module' => '$this->_moduleName',";
}


$linkedTables = array();
$linkedTablesCode = array();
$searchableFields = array('all' => 'All');

$rowFields = array('<td align="center"><input type="checkbox" name="del_id[]" value="<?php echo $row->' . $tableDefinition['primaryKey'][0] . '; ?>" /></td>');
$headers = array('<th><input type="checkbox" onchange="toggleCheckboxes(this);" /></th>');
foreach ($tableDefinition['fields'] as $field)
{
    $columnFilters = '';
    $renderedFieldData = "echo \$row->$field[name];";
    
    if (('char' === substr($field['type'], -4) || 'text' === substr($field['type'], -4)) && 'password' !== $field['name']) {
        $searchableFields[$field['name']] = $field['label'];
    }
    
    if ('enum' == $field['type'] || 'set' == $field['type']) {
        $optionsCode = array('' => '- - Change - -');
        $options = preg_split('#\s*,\s*#', $field['type_arguments']);
        foreach ($options as $option)
        {
            $option = trim($option, "'");
            
            $optionsCode[$option] = $option;
        }
        $optionsCode = var_export($optionsCode, true);
        
        $columnFilters = "<?php echo \$this->formSelect('$field[name]', \$this->param$field[name], array('onchange' => 'updateFilters(\'$field[name]\', this.options[this.selectedIndex].value)'), $optionsCode); ?>";
    
    } elseif (isset($tableDefinition['referenceMap'][$field['name']])) {
        
        $referenceData = $tableDefinition['referenceMap'][$field['name']];
        $refTableName = $referenceData['table'];
        
        $refTableDefinition = $this->_tables[$refTableName];
        
        if (!isset($linkedTables[$refTableName])) {
            $linkedTables[$refTableName] = $refTableName;
            
            $linkedTablesCode[] = "\$table$refTableDefinition[baseClassName] = new $refTableDefinition[className]();";
        }
        
        $columnFilters = <<<FILTER
<?php
    echo \$this->formSelect('$field[name]', \$this->param$field[name], array('onchange' => 'updateFilters(\'$field[name]\', this.options[this.selectedIndex].value)'), array('' => '- - Change - -') + \$table$refTableDefinition[baseClassName]->fetchPairs());
?>
FILTER;
        $renderedFieldData = <<<RENDER
    \$linkedRow = \$table$refTableDefinition[baseClassName]->find(\$row->$field[name])->current();
    if (\$linkedRow) {
        echo \$linkedRow->getZodekenAutoLabel();
    } else {
        echo \$linkedRow->$field[name], ' (unlinked)';
    }
RENDER;
    }
    
    $headers[] = "<th<?php if ('$field[name]' == \$this->sortField) echo ' class=\"sort-field sort-', htmlspecialchars(\$this->param_so), '\"'; ?>>
                $field[label] 
                <a href='<?php echo \$this->url(\$_GET + array('_sf' => '$field[name]', '_so' => 'asc')); ?>'>&#x25B2;</a>
                <a href='<?php echo \$this->url(\$_GET + array('_sf' => '$field[name]', '_so' => 'desc')); ?>'>&#x25BC;</a>
                $columnFilters
            </th>";
    
    $align = ('integer' == $field['php_type'] || 'float' == $field['php_type']) 
        && !isset($tableDefinition['referenceMap'][$field['name']]) ? ' align="right"' : '';
    
    if ('text' == $field['type'] || 'mediumtext' == $field['type'] 
            || 'longtext' == $field['type'] || 'tinytext' == $field['type']) {
        $rowFields[] = "<td$align><?php echo mb_substr(\$row->$field[name], 0, 100), '...'; ?></td>";
    } else {
        $rowFields[] = "<td$align><?php $renderedFieldData ?></td>";
    }
}

$headers[] = '<th>Actions</th>';
$rowFields[] = '<td align="center"><a href="<?php echo $this->url(array(' . $urlHelperModulePart . '\'controller\' => \'' . $tableDefinition['controllerName'] . '\', \'action\' => \'update\', \'id\' => $row->' . $tableDefinition['primaryKey'][0] . '), null, true); ?>">Edit</a> 
    - <a onclick="return confirm(\'Confirm deletion!\');" href="<?php echo $this->url(array(' . $urlHelperModulePart . '\'controller\' => \'' . $tableDefinition['controllerName'] . '\', \'action\' => \'delete\', \'del_id\' => $row->' . $tableDefinition['primaryKey'][0] . '), null, true); ?>">Delete</a></td>';

$headers = '        <tr>
            ' . implode("\n            ", $headers) . '
        </tr>';
$rowFields = '        <tr>
            ' . implode("\n            ", $rowFields) . '
        </tr>';

if (!empty($linkedTablesCode)) {
    $linkedTablesCode = '<?php
' . implode("\n", $linkedTablesCode) . '
?>
';
} else {
    $linkedTablesCode = '';
}

$searchableFields = var_export($searchableFields, true);

return <<<CODE
$linkedTablesCode
<script type="text/javascript">
function toggleCheckboxes(source)
{
    var checkboxes = document.getElementsByName('del_id[]');
    for (var i = 0; i < checkboxes.length; i++)
    {
        checkboxes[i].checked = source.checked;
    }
}

function updateFilters(paramName, paramValue)
{
    var newQuery = [];
    var args = {};
    var query = location.search.indexOf('?') > -1 ? location.search.substring(1).split('&') : [];

    for (var pairIndex = 0; pairIndex < query.length; pairIndex++) {
        var param = query[pairIndex].split('=');
        args[param[0]] = param[1];
    }

    args[paramName] = paramValue;
    for (var key in args)
    {
        newQuery.push(key + '=' + encodeURIComponent(args[key]));
    }

    self.location.href = '?' + newQuery.join('&');
}
</script>

<div style="text-align:right">
<a href="<?php echo \$this->url(array($urlHelperModulePart'controller' => '$tableDefinition[controllerName]', 'action' => 'index'), null, true); ?>">Reset Filters</a>
- <a href="<?php echo \$this->url(array($urlHelperModulePart'controller' => '$tableDefinition[controllerName]', 'action' => 'create'), null, true); ?>">Add New</a></div><br />

<form method="get" action="<?php echo \$this->url(array($urlHelperModulePart'controller' => '$tableDefinition[controllerName]', 'action' => 'index'), null, true); ?>">
<div>
Search for: <input type="text" name="_kw" value="<?php echo htmlspecialchars(\$this->param_kw); ?>" /> in 
<?php echo \$this->formSelect('_sm', \$this->param_sm, array(), $searchableFields); ?>
<input type="submit" value="Go" />
</div>
</form>

<form method="post" action="<?php echo \$this->url(array($urlHelperModulePart'controller' => '$tableDefinition[controllerName]', 'action' => 'delete'), null, true); ?>" onsubmit="return confirm('Delete selected rows?');">
<table width="100%" border="1" style="border-collapse:collapse" cellspacing="0" 
    cellpadding="3">
    <thead>
$headers
    </thead>
    <tfoot>
$headers
    </tfoot>
    <tbody>
<?php foreach (\$this->paginator as \$row): ?>
$rowFields
<?php endforeach; ?>
    </tbody>
</table><br />
<input type="submit" value="Delete Selected Rows" />
</form>

<?php echo \$this->paginationControl(\$this->paginator,
                                    'Sliding',
                                    'pagination_control.phtml');
CODE;
?>
