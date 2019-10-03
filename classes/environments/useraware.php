<?php

use Opencontent\Opendata\Api\Values\Content;

class UserAwareEnvironmentSettings extends DefaultEnvironmentSettings
{
    public function filterContent( Content $content )
    {
        $language = \eZLocale::currentLocaleCode();
        $object = $content->getContentObject($language);

        $content = parent::filterContent($content);
        $content['metadata']['userAccess'] = $this->getObjectAccess($object);

        return $content;
    }

    private function getObjectAccess(eZContentObject $object)
    {
        return array(
            'canRead' => $object->canRead(),
            'canEdit' => $object->canEdit(),
            'canRemove' => $object->canRemove(),
            'canTranslate' => $object->canTranslate(),
        );
    }
}