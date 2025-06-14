<?php


namespace Opencontent\Opendata\Api\QueryLanguage\EzFind;

use eZINI;
use eZSolr;
use ezfSolrDocumentFieldBase;
use Opencontent\QueryLanguage\Converter\Exception;
use Opencontent\QueryLanguage\Parser\Token;


class SolrNamesHelper
{

    /**
     * @var \ArrayObject
     */
    protected $availableFieldDefinitions;

    protected $originalAvailableFieldDefinitions;

    /**
     * @var TokenFactory
     */
    protected $tokenFactory;

    public function __construct(array $availableFieldDefinitions, TokenFactory $tokenFactory)
    {
        $this->originalAvailableFieldDefinitions = $availableFieldDefinitions;
        $this->availableFieldDefinitions = new \ArrayObject($availableFieldDefinitions);
        $this->tokenFactory = $tokenFactory;
    }

    public function reset()
    {
        $this->availableFieldDefinitions = new \ArrayObject($this->originalAvailableFieldDefinitions);
    }

    /**
     * Se è presente un parametro di classe restringe il campo degli attributi a disposizione
     *
     * @param $classList
     */
    public function filterAvailableFieldDefinitionsByClasses($classList)
    {

        $filteredAvailableFieldDefinitions = array();
        foreach ($this->availableFieldDefinitions as $identifier => $fieldDefinition) {
            foreach ($fieldDefinition as $dataType => $classes) {
                $filteredClasses = array_intersect(
                    $classList,
                    $classes
                );
                if (!empty( $filteredClasses )) {
                    $filteredAvailableFieldDefinitions[$identifier][$dataType] = $filteredClasses;
                }
            }
        }
        $this->availableFieldDefinitions = $filteredAvailableFieldDefinitions;
    }

    public function generateSortNames($field)
    {
        return $this->generateFieldNames($field, 'sort');
    }

    public function generateFieldNames($field, $context = 'search')
    {
        if (!$field instanceof Token) {
            $field = $this->tokenFactory->createQueryToken($field);
        }

        if ($field->data('is_meta_field')) {
            if ($field == 'section') {
                $field = 'section_id';
            } elseif ($field == 'state') {
                $field = 'object_states';
            } elseif ($field == 'author') {
                $field = 'owner_id';
            } elseif ($field == 'class') {
                return array('meta_class' => 'meta_class_identifier_ms');
            }

            return array('meta_' . $field => $this->getMetaFieldName((string)$field, $context));
        } elseif ($field->data('is_field')) {
            if ($field == 'ez_tag_ids') {
                return array('ezf_df_tag_ids' => 'ezf_df_tag_ids');
            }
            if ($field == 'ez_all_texts') {
                return array('ezf_df_text' => 'ezf_df_text');
            }

            if ($subFields = $field->data('sub_fields')) {
                if (count($subFields) == 2) {
                    $mainField = $subFields[0];
                    $subField = $subFields[1];

                    return $this->getSubFieldNames($mainField, $subField, $context);
                } else {
                    throw new Exception("Max one subfield is allowed ($field)");
                }
            }

            return $this->getFieldNames($field, $context);
        } elseif ($field->data('is_function_field') && $field->data('function') == 'raw') {
            $fieldName = trim(str_replace('raw', '', (string)$field), '[]');

            return array($fieldName=>$fieldName);
        }
        throw new Exception("Can not convert field $field");
    }

    protected function getMetaFieldName($field, $context)
    {
        if ((string)$field == 'name' && $context = 'sort'){
            $field = 'sort_name';
        }
        return eZSolr::getMetaFieldName((string)$field, $context);
    }

    protected function getFieldNames(Token $field, $context)
    {
        $data = array();

        $dataTypes = $this->getDatatypesByIdentifier((string)$field);
        foreach ($dataTypes as $dataType) {
            $type = $this->getSolrType($dataType, $context);
            $data[$field . '.' . $type] = $this->generateSolrFieldName(
                (string)$field,
                $type
            );
        }

        if (empty( $data )) {
            throw new Exception("{$field} not found or not searchable");
        }

        return $data;
    }

    protected function getSubFieldNames(Token $field, Token $subField, $context)
    {
        if ( $context == 'sort' ){
            return $this->getFieldNames($field, $context);
        }
        
        $data = array();
        $dataTypes = $this->getDatatypesByIdentifier((string)$field);
        foreach ($dataTypes as $dataType) {
            if ($subField && ( $dataType == 'ezobjectrelationlist' || $dataType == 'ezobjectrelation' )) {
                if ($subField->data('is_meta_field')) {
                    $data[$field . '.meta_' . $subField] = $this->generateSolrSubMetaFieldName(
                        (string)$field,
                        (string)$subField
                    );
                } elseif ($subField->data('is_field')) {
                    $subDataTypes = $this->getUnFilteredDatatypesByIdentifier((string)$subField);
                    foreach ($subDataTypes as $subDataType) {
                        $type = $this->getSolrType($subDataType, $context);
                        $data[$field . '/' . $subField . '.' . $type] = $this->generateSolrSubFieldName(
                            (string)$field,
                            (string)$subField,
                            $type
                        );
                    }
                }
            } elseif ($subField->data('is_custom_subfield')) {
                $data[$field . '/' . $subField . '.' . $subField->data('custom_subfield_type')] = $this->generateSolrSubFieldName(
                    (string)$field,
                    (string)$subField,
                    $subField->data('custom_subfield_type')
                );
            }
        }
        if (empty( $data )) {
            throw new Exception("{$field}.{$subField} not found or not searchable");
        }

        return $data;
    }

    public function getDatatypesByIdentifier($identifier)
    {
        if (isset( $this->availableFieldDefinitions[$identifier] )) {
            return array_keys($this->availableFieldDefinitions[$identifier]);
        }
        throw new Exception("Field $identifier not found or not searchable in query class range");
    }

    public function getUnFilteredDatatypesByIdentifier($identifier)
    {
        if (isset( $this->originalAvailableFieldDefinitions[$identifier] )) {
            return array_keys($this->originalAvailableFieldDefinitions[$identifier]);
        }
        throw new Exception("Field $identifier not found or not searchable");
    }

    public function getIdentifiersByDatatype($datatype)
    {
        $result = array();
        foreach ($this->availableFieldDefinitions as $identifier => $data) {
            if (array_key_exists($datatype, $data)) {
                $result[] = $identifier;
            }
        }
        if (!empty( $result )) {
            return $result;
        }
        throw new Exception("Datatype $datatype not found or not searchable in query class range");
    }

    public function getUnFilteredIdentifiersByDatatype($datatype)
    {
        $result = array();
        foreach ($this->originalAvailableFieldDefinitions as $identifier => $data) {
            if (array_key_exists($datatype, $data)) {
                $result[] = $identifier;
            }
        }
        if (!empty( $result )) {
            return $result;
        }
        throw new Exception("Datatype $datatype not found or not searchable");
    }

    /**
     * @see SentenceConverter::getFieldName
     *
     * @param $datatypeString
     * @param string $context
     *
     * @return string
     */
    public function getSolrType($datatypeString, $context = 'search')
    {
        $eZFindINI = eZINI::instance('ezfind.ini');
        $datatypeMapList = $eZFindINI->variable(
            'SolrFieldMapSettings',
            eZSolr::$fieldTypeContexts[$context]
        );
        if (!empty( $datatypeMapList[$datatypeString] )) {
            return $datatypeMapList[$datatypeString];
        }
        $datatypeMapList = $eZFindINI->variable('SolrFieldMapSettings', 'DatatypeMap');
        if (!empty( $datatypeMapList[$datatypeString] )) {
            return $datatypeMapList[$datatypeString];
        }

        return $eZFindINI->variable('SolrFieldMapSettings', 'Default');
    }

    /**
     * @see SentenceConverter::getFieldName
     *
     * @param $identifier
     * @param $type
     *
     * @return string
     */
    public function generateSolrFieldName($identifier, $type)
    {
        return ezfSolrDocumentFieldBase::generateAttributeFieldName(
            new \eZContentClassAttribute(array('identifier' => $identifier)),
            $type
        );
    }

    /**
     * @see SentenceConverter::getFieldName
     *
     * @param $identifier
     * @param $type
     * @param $subIdentifier
     *
     * @return string
     */
    public function generateSolrSubFieldName($identifier, $subIdentifier, $type)
    {
        return ezfSolrDocumentFieldBase::generateSubattributeFieldName(
            new \eZContentClassAttribute(array('identifier' => $identifier)),
            $subIdentifier,
            $type
        );
    }

    public function generateSolrSubMetaFieldName($identifier, $subIdentifier)
    {
        // per via del ocsolrdocumentfieldobjectrelation.php di ocsearchtools
        if ($subIdentifier == 'name') {
            return ezfSolrDocumentFieldBase::generateSubattributeFieldName(
                new \eZContentClassAttribute(array('identifier' => $identifier)),
                'name',
                'string'
            );
        }

        if ($subIdentifier == 'section') {
            $subIdentifier = 'section_id';
        } elseif ($subIdentifier == 'state') {
            $subIdentifier = 'object_states';
        }

        return ezfSolrDocumentFieldBase::generateSubmetaFieldName(
            $subIdentifier,
            new \eZContentClassAttribute(array('identifier' => $identifier))
        );
    }
}
