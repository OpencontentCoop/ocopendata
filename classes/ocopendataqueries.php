<?php

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

    public function getStaticQueries($onlyWithFields = false, $onlyWithError = false): array
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
                $this->loadQueriesFromObject($object, $searchUrls, $onlyWithFields, $onlyWithError);
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
        bool $onlyWithFields = false,
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
                                    if ($onlyWithFields && empty(array_column($analysis['analysis'], 'field'))) {
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

    public function optimize(string $query): string
    {
        /** @var Query $queryObject */
        $queryObject = $this->analyze($query)['query'];
        foreach ($queryObject->getFilters() as $item) {
            foreach ($item->getSentences() as $sentence) {
                $token = $sentence->getField();
                if ((string)$token === 'raw[ezf_df_text]') {
                    $token->setToken('all_text');
                    $sentence->setField($token);
                }else if ((string)$token === 'raw[ezf_df_tag_ids]') {
                    $token->setToken('eztags_id');
                    $sentence->setField($token);
                }
            }
        }

        return (string)$queryObject;
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