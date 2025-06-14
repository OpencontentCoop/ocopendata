<?php

use Opencontent\Opendata\Api\QueryLanguage\EzFind\SolrNamesHelper;
use Opencontent\QueryLanguage\Parser;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\QueryBuilder;
use Opencontent\Opendata\Api\QueryLanguage\EzFind\SearchGateway;
use Opencontent\QueryLanguage\Converter\AnalyzerQueryConverter;
use Opencontent\QueryLanguage\Query;

class OCOpenDataQueries
{
    private static $instance;

    private $staticQueries = null;

    public static function getInstance(): OCOpenDataQueries
    {
        if (!isset(self::$instance)) {
            self::$instance = new OCOpenDataQueries();
        }
        return self::$instance;
    }

    public function canTranslate(): bool
    {
        return class_exists('TranslatorManager');
    }

    public function translate(string $query, string $fromLanguage, string $toLanguage)
    {
        $analysis = $this->analyze($query);
        /** @var Query $queryObject */
        $queryObject = $analysis['query'];

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $analysis['builder'];
        $nameHelper = $queryBuilder->getSolrNamesHelper();

        foreach ($queryObject->getFilters() as $item) {
            $this->translateItemSentences($item, $nameHelper, $fromLanguage, $toLanguage);
        }

        return (string)$queryObject;
    }

    private function translateItemSentences(Parser\Item $item, SolrNamesHelper $nameHelper, $fromLanguage, $toLanguage): void
    {
        if (!$this->canTranslate()){
            return;
        }
        foreach ($item->getSentences() as $sentence) {
            $field = $sentence->getField();
            $doTranslate = false;
            if ((string)$field === 'raw[ezf_df_text]' || (string)$field === 'ez_all_texts') {
                $doTranslate = true;
            } elseif ($field->isField()) {
                try {
                    $datatypes = $nameHelper->getDatatypesByIdentifier((string)$field);
                } catch (\Exception $exception) {
                    $datatypes = [];
                }
                if (in_array('eztext', $datatypes)
                    || in_array('ezstring', $datatypes)
                    || in_array('ezxmltext', $datatypes)) {
                    $doTranslate = true;
                }
            }
            if ($doTranslate) {
                $values = $sentence->getValue();
                if (!is_array($values)) {
                    $values = [$values];
                }
                $values = array_map(function ($value) {
                    return trim($value, '"');
                }, $values);
                $translatedValues = TranslatorManager::instance()
                    ->getHandler()
                    ->translate($values, $fromLanguage, $toLanguage);
                if (!empty($translatedValues)) {
                    $valueToken = new Parser\Token();
                    $valueToken->setToken('["' . implode('","', $translatedValues) . '"]');
                    $sentence->setValue($valueToken);
                }
            }
        }
        foreach ($item->getChildren() as $child) {
            $this->translateItemSentences($child, $nameHelper, $fromLanguage, $toLanguage);
        }
    }

    public function getStaticQueries($onlyWithStringFilters = false, $onlyWithError = false): array
    {
        if ($this->staticQueries === null) {
            $this->staticQueries = [];
        }

        //@todo
        $searchUrls = [
            'ita-IT' => '/opendata/api/content/search',
            'eng-GB' => '/en/opendata/api/content/search',
            'ger-DE' => '/de/opendata/api/content/search',
            'por-BR' => '/po/opendata/api/content/search',
            'esl-ES' => '/es/opendata/api/content/search',
        ];

        $classIdList = eZContentClass::fetchIDListContainingDatatype(eZPageType::DATA_TYPE_STRING);
        foreach ($classIdList as $classId) {
            $class = eZContentClass::fetch($classId);
            /** @var eZContentObject[] $objects */
            $objects = eZPersistentObject::fetchObjectList(
                eZContentObject::definition(),
                null,
                ['contentclass_id' => $class->attribute('id')],
                ['name' => 'asc']
            );
            foreach ($objects as $object) {
                $this->loadQueriesFromObject($object, $searchUrls, $onlyWithStringFilters, $onlyWithError);
                eZContentObject::clearCache();
            }
        }

        $response = array_values($this->staticQueries);
        foreach ($response as &$object) {
            $object['attributes'] = array_values($object['attributes']);
            foreach ($object['attributes'] as &$attribute) {
                $attribute['blocks'] = array_values($attribute['blocks']);
            }
        }

        return $response;
    }

    private function loadQueriesFromObject(
        eZContentObject $object,
        array $searchUrls = [],
        bool $onlyWithStringFilters = false,
        bool $onlyWithError = false
    ): void {
        $id = $object->attribute('id');
        $remoteId = $object->attribute('remote_id');
        $availableLanguages = $object->availableLanguages();
        foreach ($availableLanguages as $availableLanguage) {
            /** @var eZContentObjectAttribute[] $dataMap */
            $dataMap = $object->fetchDataMap(false, $availableLanguage);
            foreach ($dataMap as $attribute) {
                if ($attribute->attribute('data_type_string') == eZPageType::DATA_TYPE_STRING
                    && $attribute->hasContent()) {
                    $attributeIdentifier = $attribute->attribute('contentclass_attribute_identifier');

                    $source = $attribute->attribute('data_text');
                    /** @var \eZPage $page */
                    $page = \eZPage::createFromXML($source);

                    /** @var \eZPageZone $zone */
                    foreach ($page->attribute('zones') as $zone) {
                        /** @var \eZPageBlock[] $blocks */
                        $blocks = (array)$zone->attribute('blocks');

                        foreach ($blocks as $index => $block) {
                            $custom = $block->attribute('custom_attributes');
                            $customUrl = $custom['remote_url'] ?? null;
                            if (!empty($custom['query']) && empty($customUrl)) {
                                $error = false;
                                try {
                                    $analysis = $this->analyze($custom['query']);
                                    if ($onlyWithStringFilters && !$this->hasStringFilters($analysis)) {
                                        continue;
                                    }
                                } catch (\Exception $exception) {
                                    $error = $exception->getMessage();
                                }
                                if ($onlyWithError && !$error) {
                                    continue;
                                }

                                if (!isset($this->staticQueries[$remoteId])) {
                                    $this->staticQueries[$remoteId] = [
                                        'object_id' => $id,
                                        'object_remote_id' => $remoteId,
                                        'object_name' => $object->attribute('name'),
                                        'object_class_identifier' => $object->attribute('class_identifier'),
                                        'object_class_name' => $object->attribute('class_name'),
                                        'attributes' => [],
                                    ];
                                }
                                $this->staticQueries[$remoteId]['attributes'][$attributeIdentifier]['identifier'] = $attributeIdentifier;
                                $this->staticQueries[$remoteId]['attributes'][$attributeIdentifier]['name'] = $attribute->attribute(
                                    'contentclass_attribute_name'
                                );
                                $this->staticQueries[$remoteId]['attributes'][$attributeIdentifier]['blocks'][$index]['languages'][] = [
                                    'zone_identifier' => $zone->attribute('zone_identifier'),
                                    'block_index' => $index,
                                    'block_id' => $block->attribute('id'),
                                    'block_name' => empty($block->attribute('name')) ?
                                        '(blocco senza titolo)' : $block->attribute('name'),
                                    'query' => $custom['query'],
                                    'search_url' => $searchUrls[$availableLanguage] ?? '/opendata/api/content/search',
                                    'attribute_id' => $attribute->attribute('id'),
                                    'attribute_version' => $attribute->attribute('version'),
                                    'attribute_language' => $attribute->attribute('language_code'),
                                    'attribute_identifier' => $attributeIdentifier,
                                    'error' => $error,
                                ];
                            }
                        }
                    }
                }
            }
        }
        eZContentObject::clearCache();
    }

    private function hasStringFilters(array $analysis): bool
    {
        foreach ($analysis['analysis'] as $analysisItem) {
            if (isset($analysisItem['field'])) {
                $values = (array)Parser\Sentence::parseString($analysisItem['value']);
                foreach ($values as $value) {
                    $value = trim($value, '"\'');
                    if (!is_numeric($value)){
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function optimize(string $query): string
    {
        $analysis = $this->analyze($query);
        /** @var Query $queryObject */
        $queryObject = $analysis['query'];

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $analysis['builder'];
        $nameHelper = $queryBuilder->getSolrNamesHelper();

        foreach ($queryObject->getFilters() as $item) {
            $this->optimizeItemSentences($item, $nameHelper);
        }

        return (string)$queryObject;
    }

    private function optimizeItemSentences(Parser\Item $item, SolrNamesHelper $nameHelper): void
    {
        foreach ($item->getSentences() as $sentence) {
            $field = $sentence->getField();

            if ((string)$field === 'raw[ezf_df_text]') {
                $field->setToken('ez_all_texts');
                $sentence->setField($field);

            } elseif ((string)$field === 'raw[ezf_df_tag_ids]') {
                $field->setToken('ez_tag_ids');
                $sentence->setField($field);

            } elseif ($field->isField()) {
                try {
                    $datatypes = $nameHelper->getDatatypesByIdentifier((string)$field);
                } catch (\Exception $exception) {
                    $datatypes = [];
                }
                if (in_array('eztags', $datatypes) || (string)$field === 'raw[ezf_df_tags]') {
                    $values = $sentence->getValue();
                    if (!is_array($values)) {
                        $values = [$values];
                    }
                    $tagIds = [];
                    foreach ($values as $value) {
                        $value = trim($value, '"');
                        $tags = eZTagsObject::fetchByKeyword($value);
                        if (!empty($tags)) {
                            foreach ($tags as $tag) {
                                $tagIds[] = $tag->attribute('id');
                            }
                        }
                    }
                    if (!empty($tagIds)) {
                        $field->setToken('ez_tag_ids');
                        $sentence->setField($field);
                        $valueToken = new Parser\Token();
                        $valueToken->setToken('[' . implode(',', $tagIds) . ']');
                        $sentence->setValue($valueToken);
                    }
                } elseif ($subFields = $field->data('sub_fields')) {
                    if (count($subFields) == 2) {
                        $mainField = $subFields[0];
                        $subField = $subFields[1];
                        try {
                            $datatypes = $nameHelper->getDatatypesByIdentifier((string)$mainField);
                        } catch (\Exception $exception) {
                            $datatypes = [];
                        }
                        if (in_array('ezobjectrelationlist', $datatypes) || (string)$subField === 'name') {
                            $values = $sentence->getValue();
                            if (!is_array($values)) {
                                $values = [$values];
                            }
                            $objectIds = [];
                            foreach ($values as $value) {
                                $value = eZDB::instance()->escapeString(trim($value, '"\''));
                                $findByNameQuery = "select distinct ezcontentobject_name.contentobject_id from ezcontentobject_name 
                                        join ezcontentobject_link on (
                                            ezcontentobject_name.contentobject_id = ezcontentobject_link.to_contentobject_id 
                                            AND ezcontentobject_link.contentclassattribute_id IN (
                                                select ezcontentclass_attribute.id from ezcontentclass_attribute WHERE ezcontentclass_attribute.identifier = '{$mainField}'
                                            )
                                        ) where ezcontentobject_name.name = '{$value}'";
                                $objectIds = array_unique(array_column(
                                    eZDB::instance()->arrayQuery($findByNameQuery),
                                    'contentobject_id'
                                ));
                                if (count($objectIds) > 0) {
                                    $field->setToken($mainField . '.id');
                                    $sentence->setField($field);
                                    $valueToken = new Parser\Token();
                                    $valueToken->setToken('[' . implode(',', $objectIds) . ']');
                                    $sentence->setValue($valueToken);
                                }
                            }
                        }
                    }
                }
            }
        }
        foreach ($item->getChildren() as $child) {
            $this->optimizeItemSentences($child, $nameHelper);
        }
    }

    private function analyze($query): array
    {
        $builder = new QueryBuilder();
        $builder->getTokenFactory()->setSubQueryResolver(function ($query) {
            $searchGateway = new SearchGateway();
            $searchGateway->setEnvironmentSettings(new DefaultEnvironmentSettings());
            $searchGateway->setQueryBuilder(new QueryBuilder());
            return array_filter((array)$searchGateway->search($query, []));
        });
        $queryObject = $builder->instanceQuery($query);

        $ezFindQuery = $queryObject->convert();
        $tokenFactory = $builder->getTokenFactory();
        $parser = new Parser(new Query($query));
        $query = $parser->setTokenFactory($tokenFactory)->parse();
        $converter = new AnalyzerQueryConverter();
        $converter->setQuery($query);

        return [
            'query' => $queryObject,
            'analysis' => $converter->convert(),
            'ezfind' => $ezFindQuery,
            'builder' => $builder,
        ];
    }
}