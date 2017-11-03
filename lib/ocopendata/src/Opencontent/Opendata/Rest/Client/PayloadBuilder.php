<?php

namespace Opencontent\Opendata\Rest\Client;


class PayloadBuilder extends \ArrayObject
{
    const DATE_FIELD = 1;
    const FILE_FIELD = 2;
    const IMAGE_FIELD = 3;
    const GEO_FIELD = 4;

    public function setRemoteId($value)
    {
        $this['metadata']['remoteId'] = $value;
    }

    public function setId($value)
    {
        $this['metadata']['id'] = $value;
    }

    public function setClassIdentifier($value)
    {
        $this['metadata']['classIdentifier'] = $value;
    }

    public function setSectionIdentifier($value)
    {
        $this['metadata']['sectionIdentifier'] = $value;
    }

    public function setStateIdentifiers(array $value)
    {
        $this['metadata']['stateIdentifiers'] = $value;
    }

    public function setStateIdentifier($value)
    {
        if (!isset( $this['metadata']['stateIdentifiers'] )) {
            $this['metadata']['stateIdentifiers'] = array($value);
        } else {
            list( $newGroup, $newState ) = explode('.', $value);

            $stateIdentifiers = array();
            foreach ($this['metadata']['stateIdentifiers'] as $stateIdentifier) {
                list( $group, $state ) = explode('.', $stateIdentifier);
                if ($group == $newGroup) {
                    $stateIdentifiers[] = $value;
                } else {
                    $stateIdentifiers[] = $stateIdentifier;
                }
            }
            $this['metadata']['stateIdentifiers'] = $stateIdentifiers;
        }
    }

    public function setParentNodes(array $value)
    {
        $this['metadata']['parentNodes'] = $value;
    }

    public function setParentNode($value)
    {
        if (!isset( $this['metadata']['parentNodes'] )) {
            $this['metadata']['parentNodes'] = array();
        }
        $this['metadata']['parentNodes'][] = $value;
    }

    public function setLanguages(array $value)
    {
        $this['metadata']['languages'] = $value;
    }

    public function setCreatorId(array $value)
    {
        $this['metadata']['creatorId'] = $value;
    }

    public function setPublished($value)
    {
        $this['metadata']['published'] = $value;
    }

    public function setModified($value)
    {
        $this['metadata']['modified'] = $value;
    }

    public function getMetadaData($identifier = null)
    {
        if ($identifier == null) {
            return $this['metadata'];
        }elseif ( isset($this['metadata'][$identifier]) ){
            return $this['metadata'][$identifier];
        }
        return null;
    }

    public function unSetLanguage($language)
    {
        if (in_array($language, $this['metadata']['languages'])){
            $languages = array();
            foreach($this['metadata']['languages'] as $item){
                if ($item != $language){
                    $languages[] = $item;
                }
            }
            if (count($languages) > 0) {
                $this['metadata']['languages'] = $languages;

                if (isset( $this['data'][$language] )) {
                    unset( $this['data'][$language] );
                }
            }
        }
    }

    public function unSetData($identifier = null, $language = null)
    {
        if ($identifier == null){
            unset($this['data']);
        } elseif ($language == null){
            foreach($this['metadata']['languages'] as $language){
                unset($this['data'][$language][$identifier]);
            }
        }else{
            unset($this['data'][$language][$identifier]);
        }
    }

    public function hasData($identifier, $language = null)
    {
        if ($language == null){
            foreach($this['metadata']['languages'] as $_language){
                if ($language == $_language){
                    return isset($this['data'][$language][$identifier]);
                }
            }
            return false;
        }else{
            return isset($this['data'][$language][$identifier]);
        }
    }

    public function getData($identifier = null, $language = null)
    {
        if ($identifier == null){
            return $this['data'];
        } elseif ($language == null){
            $data = array();
            foreach($this['metadata']['languages'] as $language){
                if ( isset($this['data'][$language][$identifier]) ){
                    $dataPerLanguage = $this->getData($identifier, $language);
                    if (!empty($dataPerLanguage)){
                        $data[$language] = $dataPerLanguage;
                    }
                }
            }
            return $data;
        }else{
            return $this['data'][$language][$identifier];
        }
    }

    public function setData($language, $identifier, $value, $filter = null)
    {
        if ($filter == self::DATE_FIELD) {
            $value = $this->filterISODate($value);
        }

        if ($filter == self::FILE_FIELD) {
            $value = $this->filterFile($value);
        }

        if ($filter == self::IMAGE_FIELD) {
            $value = $this->filterImage($value);
        }

        if ($filter == self::GEO_FIELD) {
            $value = $this->filterGeo($value);
        }

        if ($language == null){
            foreach($this['metadata']['languages'] as $language){
                $this['data'][$language][$identifier] = $value;
            }
        }else{
            $this['data'][$language][$identifier] = $value;
        }
    }

    public function setOptions(array $options)
    {
        $this['options'] = $options;
    }

    public function setOption($key, $value)
    {
        if (!isset($this['options']))
        {
            $this['options'] = array();
        }
        $this['options'][$key] = $value;
    }

    protected function filterISODate($value)
    {
        return date("c", $value);
    }

    protected function filterGeo($value)
    {
        $data = explode(',', $value);

        return array(
            'latitude' => isset( $data[0] ) ? (float)$data[0] : 0,
            'longitude' => isset( $data[1] ) ? (float)$data[1] : 0,
            'address' => isset( $data[2] ) ? $data[2] : ''
        );
    }

    protected function filterFile($value)
    {
        $data = array(
            'url' => null,
            'file' => null,
            'filename' => null
        );
        $fileData = file_get_contents($value);
        if ($fileData !== false) {
            $data['filename'] = basename($value);
            $data['file'] = base64_encode($fileData);
        }

        return $data;
    }

    protected function filterImage($value)
    {
        $data = array(
            'url' => null,
            'file' => null,
            'filename' => null
        );
        $fileData = file_get_contents($value);
        if ($fileData !== false) {
            $data['filename'] = basename($value);
            $data['alt'] = basename($value);
            $data['file'] = base64_encode($fileData);
        }

        return $data;
    }

}
