<?php

$fields = array();

foreach ($tableDefinition['fields'] as $field) {
    $addedCode = null;
    $fieldType = null;
    $referenceTableClass = null;
    $fieldConfigs = array();
    $validators = array();
    $filters = array();

    foreach ($tableDefinition['referenceMap'] as $referenceTable => $reference) {
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
        switch ($field['type']) {
            case 'set':
            case 'enum':
                /**
                 * For example, ENUM('Male', 'Female') would be converted to
                 *
                 * ->setMultiOptions(array("Male" => "Male", "Female" => "Female"))
                 */
                $numericOptions = eval("return array($field[type_arguments]);");
                $assocOptions = array();
                foreach ($numericOptions as $option) {
                    $option = str_replace("'", "\'", $option);
                    $assocOptions[] = "'$option' => '$option'";
                }
                $array = 'array(' . implode(',', $assocOptions) . ')';
                $fieldType = 'radio';
                $fieldConfigs[] = '->setMultiOptions(' . $array . ')';
                $validators[] = "new Zend_Validate_InArray(array('haystack' => $array))";
                $fieldConfigs[] = '->setSeparator(" ")';
                break;
            case 'tinytext':
            case 'mediumtext':
            case 'text':
            case 'longtext':
                $fieldType = 'textarea';
                $filters[] = 'new Zend_Filter_StringTrim()';
                $fieldConfigs[] = '->setAttrib("class", "input-xxlarge")';
                $fieldConfigs[] = '->setAttrib("rows", "15")';
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
                $fieldConfigs[] = '->setAttrib("class", "input-xlarge")';
                break;
            case 'varchar':
            case 'char':
                $validators[] = 'new Zend_Validate_StringLength(array("max" => ' . $field['type_arguments'] . '))';
                $fieldType = 'password' == $field['type'] ? 'password' : 'text';
                $filters[] = 'new Zend_Filter_StringTrim()';
                $fieldConfigs[] = '->setAttrib("maxlength", ' . $field['type_arguments'] . ')';
                $fieldConfigs[] = '->setAttrib("class", "input-xlarge")';

                if ('email' === strtolower($field['name']) || 'emailaddress' === strtolower($field['name'])) {
                    $validators[] = 'new Zend_Validate_EmailAddress()';
                }
                break;
            case 'bit':
            case 'date':
            case 'time':
            case 'datetime':
            case 'timestamp':
            default:
                $fieldType = 'text';
                $filters[] = 'new Zend_Filter_StringTrim()';
                
                if ('datetime' == $field['type'] || 'timestamp' == $field['type']) {
                    $fieldConfigs[] = '->setValue(date("Y-m-d H:i:s"))';
                    $fieldConfigs[] = '->setAttrib("class", "input-medium")';
                } elseif ('date' == $field['type']) {
                    $fieldConfigs[] = '->setValue(date("Y-m-d"))';
                    $fieldConfigs[] = '->setAttrib("class", "input-small")';
                } elseif ('time' == $field['type']) {
                    $fieldConfigs[] = '->setValue(date("H:i:s"))';
                    $fieldConfigs[] = '->setAttrib("class", "input-small")';
                }
                break;
        }

        if ($field['is_required']) {
            $fieldConfigs[] = '->setRequired(true)';
        }
    }

    if ($field['default_value']) {
        $fieldConfigs[] = '->setValue("' . str_replace('"', '\"', $field['default_value']) . '")';
    }

    foreach ($validators as $validator) {
        $fieldConfigs[] = '->addValidator(' . $validator . ', true)';
    }

    foreach ($filters as $filter) {
        $fieldConfigs[] = '->addFilter(' . $filter . ')';
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

$fields[] = <<<CODE
        \$this->addElement(
            \$this->createElement('button', 'submit')
                ->setLabel('Save')
                ->setAttrib('class', 'btn btn-primary')
                ->setAttrib('type', 'submit')$buttonDecorators
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
class {$tableDefinition['formClassName']} extends Zend_Form
{
    public function init()
    {
        \$this->setMethod('post');

$fields

        parent::init();
    }
}
CODE;
?>
