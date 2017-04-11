<?php

namespace Opencontent\Opendata\Api;

use eZTagsObject;
use eZTagsTemplateFunctions;
use Opencontent\Opendata\Api\Exception\BaseException;
use Opencontent\Opendata\Api\Structs\TagStruct;
use Opencontent\Opendata\Api\Values\Tag;

class TagRepository
{
    public function read($tagUrl)
    {
        if (is_numeric($tagUrl)) {
            $tag = eZTagsObject::fetch($tagUrl);
        } else {
            $tag = eZTagsObject::fetchByUrl($tagUrl);
        }
        if (!$tag instanceof eZTagsObject) {
            throw new BaseException("Tag {$tagUrl} not found");
        }

        return $this->buildTagTree($tag);
    }

    public function create(TagStruct $struct)
    {
        $language = \eZContentLanguage::fetchByLocale($struct->locale);
        if (!$language instanceof \eZContentLanguage) {
            throw new \Exception("Locale {$struct->locale} not found");
        }
        $languageMask = \eZContentLanguage::maskByLocale(array($language->attribute('locale')),
            $struct->alwaysAvailable);

        $parentTag = null;
        if ((int)$struct->parentTagId != 0) {
            $parentTag = eZTagsObject::fetchWithMainTranslation($struct->parentTagId);
            if (!$parentTag instanceof eZTagsObject) {
                throw new \Exception("Parent tag {$struct->parentTagId} not found");
            }
        }

        $message = 'failed';

        $tag = null;
        if ($parentTag instanceof eZTagsObject && $parentTag->attribute('main_tag_id') != 0) {
            $parentTag = eZTagsObject::fetchWithMainTranslation($parentTag->attribute('main_tag_id'));            
        }

        if ($parentTag instanceof eZTagsObject){
            $tag = $this->read($parentTag->attribute('id'))->findByKeyword($struct->keyword);
            $message = 'already exists';
        }else{
            try{
                $tag = $this->read($struct->keyword);
                $message = 'already exists';
            }
            catch(\Exception $e){

            }
        }
        
        if (!$tag instanceof Tag) {

            $db = \eZDB::instance();
            $db->begin();

            $tagObject = new eZTagsObject(array(
                'parent_id' => $struct->parentTagId,
                'main_tag_id' => 0,
                'depth' => $parentTag instanceof eZTagsObject ? $parentTag->attribute('depth') + 1 : 1,
                'path_string' => $parentTag instanceof eZTagsObject ? $parentTag->attribute('path_string') : '/',
                'main_language_id' => $language->attribute('id'),
                'language_mask' => $languageMask
            ), $language->attribute('locale'));
            $tagObject->store();

            $translation = new \eZTagsKeyword(array(
                'keyword_id' => $tagObject->attribute('id'),
                'language_id' => $language->attribute('id'),
                'keyword' => $struct->keyword,
                'locale' => $language->attribute('locale'),
                'status' => \eZTagsKeyword::STATUS_PUBLISHED
            ));

            if ($struct->alwaysAvailable) {
                $translation->setAttribute('language_id', $translation->attribute('language_id') + 1);
            }

            $translation->store();

            $tagObject->setAttribute('path_string', $tagObject->attribute('path_string') . $tagObject->attribute('id') . '/');
            $tagObject->store();
            $tagObject->updateModified();

            /* Extended Hook */
            if (class_exists('ezpEvent', false)) {
                \ezpEvent::getInstance()->filter('tag/add', array('tag' => $tagObject, 'parentTag' => $parentTag));
            }

            $db->commit();

            $tag = $this->read($tagObject->attribute('id'));
            $message = 'success';
        }

        return array(
            'message' => $message,
            'method' => 'create',
            'tag' => $tag
        );
    }

    public function update($payload)
    {

    }

    public function delete($payload)
    {

    }

    private function buildTagTree(eZTagsObject $tagObject)
    {

        $keywordTranslations = array();
        foreach($tagObject->getTranslations() as $translation){
            $keywordTranslations[$translation->attribute('locale')] = $translation->attribute('keyword');
        }

        $tag = new Tag();
        $tag->id = (int)$tagObject->attribute('id');
        $tag->parentId = (int)$tagObject->attribute('parent_id');
        $tag->hasChildren = (bool)$this->getTagChildrenCount($tagObject) > 0;
        $tag->children = $this->getTagChildren($tagObject);
        $tag->synonymsCount = (int)$tagObject->attribute('synonyms_count');
        $tag->languageNameArray = $tagObject->attribute('language_name_array');
        $tag->keywordTranslations = $keywordTranslations;
        $tag->keyword = $tagObject->attribute('keyword');
        $tag->url = '/tags/id/' . $tagObject->attribute('id');
        $tag->icon = eZTagsTemplateFunctions::getTagIcon($tagObject->getIcon());

        return $tag;
    }

    private function getTagChildrenCount(eZTagsObject $tag)
    {
        return eZTagsObject::fetchListCount(array(
            'parent_id' => $tag->attribute('id'),
            'main_tag_id' => 0
        ));
    }

    /**
     * @param eZTagsObject $tag
     *
     * @return eZTagsObject[]
     */
    private function getTagChildren(eZTagsObject $tag)
    {
        $eztagsINI = \eZINI::instance('eztags.ini');

        $maxTags = 100;
        if ($eztagsINI->hasVariable('TreeMenu', 'MaxTags')) {
            $iniMaxTags = $eztagsINI->variable('TreeMenu', 'MaxTags');
            if (is_numeric($iniMaxTags)) {
                $maxTags = (int)$iniMaxTags;
            }
        }

        $limitArray = null;
        if ($maxTags > 0) {
            $limitArray = array('offset' => 0, 'length' => $maxTags);
        }

        $children = eZTagsObject::fetchList(
            array(
                'parent_id' => $tag->attribute('id'),
                'main_tag_id' => 0
            ),
            $limitArray
        );
        $data = array();
        foreach ($children as $child) {
            $data[] = $this->buildTagTree($child);
        }

        return $data;
    }
}
