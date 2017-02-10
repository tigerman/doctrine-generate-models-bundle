<?php

namespace tigerman\DoctrineGenerateModelsBundle\Doctrine\ORM\Tools;

use Doctrine\ORM\Mapping\ClassMetadataInfo;

class EntityGenerator extends \Doctrine\ORM\Tools\EntityGenerator
{
    protected static $isMethodTemplate =
        '/**
 * <description>
 *
 * @return <variableType>
 */
public function <methodName>()
{
<spaces>return $this-><fieldName>;
}';

    protected function generateEntityStubMethod(ClassMetadataInfo $metadata, $type, $fieldName, $typeHint = null, $defaultValue = null)
    {
        $type = ($type == 'get' && $typeHint == 'boolean') ? 'is' : $type;
        return parent::generateEntityStubMethod($metadata, $type, $fieldName, $typeHint, $defaultValue);
    }
}
