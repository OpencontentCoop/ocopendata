<?php

namespace Opencontent\Opendata\Api;

use eZTagsObject;
use eZTagsTemplateFunctions;
use Opencontent\Opendata\Api\Exception\BaseException;
use Opencontent\Opendata\Api\Exception\NotFoundException;
use Opencontent\Opendata\Api\Structs\TagStruct;
use Opencontent\Opendata\Api\Structs\TagTranslationStruct;
use Opencontent\Opendata\Api\Structs\TagSynonymStruct;
use Opencontent\Opendata\Api\Values\Tag;
use eZDB;
use eZContentLanguage;
use eZTagsKeyword;
use ezpEvent;

class TagRepository
{
    public function read($tagUrl, $offset = 0, $limit = 100)
    {
        $tagId = ltrim($tagUrl, '/');
        if (is_numeric($tagId)) {
            $tag = eZTagsObject::fetch((int)$tagId);
        } else {
            $tag = eZTagsObject::fetchByUrl($tagUrl);
        }
        if (!$tag instanceof eZTagsObject) {
            throw new BaseException("Tag {$tagUrl} not found");
        }

        return $this->buildTagTree($tag, $offset, $limit);
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
            foreach ($parentTag->getChildren() as $child){
                if ($struct->keyword == $child->attribute('keyword')){
                    $tag = $this->read($child->attribute('id'), 0, 0);
                    $message = 'already exists';
                    break;
                }
            }
        }else{
            try{
                $tag = $this->read($struct->keyword, 0, 0);
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
            ezpEvent::getInstance()->filter('tag/add', array('tag' => $tagObject, 'parentTag' => $parentTag));
            $tag = $this->read($tagObject->attribute('id'), 0, 0);
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

    public function addSynonym(TagSynonymStruct $struct)
    {
        $mainTag = eZTagsObject::fetch($struct->tagId);
        if (!$mainTag instanceof eZTagsObject){
            throw new NotFoundException($struct->tagId, 'Tag');
        }

        $language = eZContentLanguage::fetchByLocale($struct->locale, true);
        $parentTag = $mainTag->getParent(true);

        if ($parentTag instanceof eZTagsObject && eZTagsObject::exists(0, $struct->keyword, $parentTag->attribute('id'))) {
            $tags = eZTagsObject::fetchList(array('keyword' => $struct->keyword, 'parent_id' => $parentTag->attribute('id')));
            if (is_array($tags) && !empty($tags)) {

                return array(
                    'message' => 'already exists',
                    'method' => 'addSynonym',
                    'tag' => $this->read($tags[0]->attribute('id'), 0, 0)
                );
            }
        }

        $db = eZDB::instance();
        $db->begin();

        $languageMask = eZContentLanguage::maskByLocale(array($language->attribute('locale')), $struct->alwaysAvailable);

        $tag = new eZTagsObject(
            array(
                'parent_id' => $mainTag->attribute('parent_id'),
                'main_tag_id' => $mainTag->attribute('id'),
                'depth' => $mainTag->attribute('depth'),
                'path_string' => $parentTag instanceof eZTagsObject ? $parentTag->attribute('path_string') : '/',
                'main_language_id' => $language->attribute('id'),
                'language_mask' => $languageMask
            ),
            $language->attribute('locale')
        );
        $tag->store();

        $translation = new eZTagsKeyword(
            array(
                'keyword_id' => $tag->attribute('id'),
                'language_id' => $language->attribute('id'),
                'keyword' => $struct->keyword,
                'locale' => $language->attribute('locale'),
                'status' => eZTagsKeyword::STATUS_PUBLISHED
            )
        );

        if ($struct->alwaysAvailable)
            $translation->setAttribute('language_id', $translation->attribute('language_id') + 1);

        $translation->store();

        $tag->setAttribute('path_string', $tag->attribute('path_string') . $tag->attribute('id') . '/');
        $tag->store();
        $tag->updateModified();

        $db->commit();

        /* Extended Hook */
        if (class_exists('ezpEvent', false)) {
            ezpEvent::getInstance()->filter('tag/add', array('tag' => $tag, 'parentTag' => $parentTag));
            ezpEvent::getInstance()->filter('tag/makesynonym', array('tag' => $tag, 'mainTag' => $mainTag));
        }

        return array(
            'message' => 'success',
            'method' => 'addSynonym',
            'tag' => $this->read($tag->attribute('id'), 0, 0)
        );
    }

    public function addTranslation(TagTranslationStruct $struct)
    {
        $tag = eZTagsObject::fetch($struct->tagId);
        if (!$tag instanceof eZTagsObject){
            throw new NotFoundException($struct->tagId, 'Tag');
        }

        $language = eZContentLanguage::fetchByLocale($struct->locale, true);

        $tagID = $tag->attribute('id');
        $tagTranslation = eZTagsKeyword::fetch($tag->attribute('id'), $language->attribute('locale'), true);
        if ($tagTranslation instanceof eZTagsKeyword) {
            return array(
                'message' => 'already exists',
                'method' => 'addTranslation',
                'tag' => $this->read($struct->tagId, 0, 0)
            );
        }
        if (!$tagTranslation instanceof eZTagsKeyword) {
            $tagTranslation = new eZTagsKeyword(array('keyword_id' => $tag->attribute('id'),
                'keyword' => '',
                'language_id' => $language->attribute('id'),
                'locale' => $language->attribute('locale'),
                'status' => eZTagsKeyword::STATUS_DRAFT));

            $tagTranslation->store();
            $tag->updateLanguageMask();
        }

        $tag = eZTagsObject::fetch($tagID, $language->attribute('locale'));

        $newParentID = $tag->attribute('parent_id');
        $newParentTag = eZTagsObject::fetchWithMainTranslation($newParentID);

        $updateDepth = false;
        $updatePathString = false;

        $db = eZDB::instance();
        $db->begin();

        $oldParentDepth = $tag->attribute('depth') - 1;
        $newParentDepth = $newParentTag instanceof eZTagsObject ? $newParentTag->attribute('depth') : 0;

        if ($oldParentDepth != $newParentDepth)
            $updateDepth = true;

        $oldParentTag = false;
        if ($tag->attribute('parent_id') != $newParentID) {
            $oldParentTag = $tag->getParent(true);
            if ($oldParentTag instanceof eZTagsObject)
                $oldParentTag->updateModified();

            $synonyms = $tag->getSynonyms(true);
            foreach ($synonyms as $synonym) {
                $synonym->setAttribute('parent_id', $newParentID);
                $synonym->store();
            }

            $updatePathString = true;
        }

        $tagTranslation->setAttribute('keyword', $struct->keyword);
        $tagTranslation->setAttribute('status', eZTagsKeyword::STATUS_PUBLISHED);
        $tagTranslation->store();

        if ($struct->isMainTranslation)
            $tag->updateMainTranslation($language->attribute('locale'));

        $tag->setAlwaysAvailable($struct->alwaysAvailable);

        $tag->setAttribute('parent_id', $newParentID);
        $tag->store();

        if (class_exists('ezpEvent', false)) {
            ezpEvent::getInstance()->filter(
                'tag/edit',
                array(
                    'tag' => $tag,
                    'oldParentTag' => $oldParentTag,
                    'newParentTag' => $newParentTag,
                    'move' => $updatePathString
                )
            );
        }

        if ($updatePathString)
            $tag->updatePathString();

        if ($updateDepth)
            $tag->updateDepth();

        $tag->updateModified();
        $tag->registerSearchObjects();

        $db->commit();

        return array(
            'message' => 'success',
            'method' => 'addTranslation',
            'tag' => $this->read($tag->attribute('id'), 0, 0)
        );
    }

    private function buildTagTree(eZTagsObject $tagObject, $offset = 0, $limit = 100)
    {
        $keywordTranslations = array();
        foreach($tagObject->getTranslations() as $translation){
            $keywordTranslations[$translation->attribute('locale')] = $translation->attribute('keyword');
        }

        $synonyms = array();
        foreach($tagObject->getSynonyms() as $synonym){
            foreach ($synonym->getTranslations() as $synonymTranslation) {
                $synonyms[$synonymTranslation->attribute('locale')] = $synonymTranslation->attribute('keyword');
            }
        }

        $tag = new Tag();
        $tag->id = (int)$tagObject->attribute('id');
        $tag->parentId = (int)$tagObject->attribute('parent_id');
        $tag->hasChildren = (bool)$this->getTagChildrenCount($tagObject) > 0;
        $tag->childrenCount = (int)$this->getTagChildrenCount($tagObject);
        $tag->children = $this->getTagChildren($tagObject, $offset, $limit);
        $tag->synonymsCount = (int)$tagObject->attribute('synonyms_count');
        $tag->synonyms = $synonyms;
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
     * @param int $offset
     * @param int $limit
     * @return eZTagsObject[]
     */
    private function getTagChildren(eZTagsObject $tag, $offset = 0, $limit = 100)
    {
        if ($limit === 0){
            return [];
        }

        $eztagsINI = \eZINI::instance('eztags.ini');

        $maxTags = 100;
        if ($eztagsINI->hasVariable('TreeMenu', 'MaxTags')) {
            $iniMaxTags = $eztagsINI->variable('TreeMenu', 'MaxTags');
            if (is_numeric($iniMaxTags)) {
                $maxTags = (int)$iniMaxTags;
            }
        }

        if ($limit > $maxTags){
            $limit = $maxTags;
        }

        $limitArray = array('offset' => $offset, 'length' => $limit);

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
