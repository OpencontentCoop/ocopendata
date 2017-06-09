<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use eZContentObject;
use Opencontent\Opendata\Api\ContentRepository;
use Opencontent\Opendata\Api\EnvironmentSettings;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\Gateway\FileSystem;
use Opencontent\Opendata\Api\PublicationProcess;
use Opencontent\Opendata\Api\Values\Content;
use Opencontent\Opendata\Api\Values\Metadata;
use eZContentUpload;
use eZSys;
use eZFile;
use eZHTTPTool;
use eZURI;
use eZDir;

class Relations extends Base
{

    /**
     * @var FileSystem
     */
    protected static $gateway;

    protected static function gateway()
    {
        if ( self::$gateway === null )
            self::$gateway = new FileSystem();

        return self::$gateway;
    }

    public function get( eZContentObjectAttribute $attribute )
    {
        $content = parent::get( $attribute );
        $ids = explode( '-', $attribute->toString() );
        $contents = array();
        foreach( $ids as $id )
        {
            try
            {
                $id = intval($id);
                if ($id > 0) {
                    $contentObject = eZContentObject::fetch($id);
                    if ($contentObject instanceof eZContentObject) {
                        $contents[] = Metadata::createFromEzContentObject($contentObject);
                    }
                }
            }
            catch( \Exception $e )
            {
                \eZDebug::writeError( $e->getMessage() );
            }
        }
        $content['content'] = $contents;
        return $content;
    }

    public function set( $data, PublicationProcess $process )
    {
        $results = array();
        foreach( $data as $item )
        {
            if ( is_array( $item ) )
            {
                $results []= self::uploadFile($item);
            }
            else
            {
                $results []= self::findContent($item);
            }
        }
        return empty($results) ? null : implode( '-', $results );
    }

    public static function validate( $identifier, $data, eZContentClassAttribute $attribute )
    {
        if ( is_array( $data ) )
        {
            foreach( $data as $item )
            {
                if ( is_array( $item ) )
                {
                    if (isset( $item['image'] )) {
                        Image::validate($identifier, $item, $attribute);
                    } elseif (isset( $item['file'] )) {
                        File::validate($identifier, $item, $attribute);
                    } elseif (isset( $item['remoteId'] )) {
                        try {
                            self::gateway()->loadContent($data['remoteId']);
                        } catch (\Exception $e) {
                            throw new InvalidInputException('Invalid content identifier', $identifier, array($item));
                        }
                    } elseif (isset( $item['id'] )) {
                        try {
                            self::gateway()->loadContent($data['id']);
                        } catch (\Exception $e) {
                            throw new InvalidInputException('Invalid content identifier', $identifier, array($item));
                        }
                    } else {
                        throw new InvalidInputException('Invalid input', $identifier, array($item));
                    }
                }
                else
                {
                    try
                    {
                        self::gateway()->loadContent( $item );
                    }
                    catch( \Exception $e )
                    {
                        throw new InvalidInputException( 'Invalid content identifier', $identifier, array( $item ) );
                    }
                }
            }
        }else {
            throw new InvalidInputException('Invalid data', $identifier, $data);
        }
    }

    protected static function findContent( $item )
    {
        $content = self::gateway()->loadContent( $item );
        return $content->metadata->id;
    }

    /**
     * @param $item
     * @return mixed|null
     * @throws \Exception
     */
    protected function uploadFile( $item )
    {
        if (isset($item['image']))
        {
            $data = $item['image'];
        }
        else
        {
            $data = $item['file'];
        }

        $remoteID = md5($data);
        $node = false;
        $object = eZContentObject::fetchByRemoteID($remoteID);
        if ($object instanceof eZContentObject) {
            //$node = $object->attribute('main_node');
            return $object->attribute('id');
        }
        $name = $item['name'];
        $fileStored = $this->getTemporaryFilePath($item['filename'], $item['url'], $data);
        if ($fileStored !== null) {
            $result = array();
            $upload = new eZContentUpload();
            $uploadFile = $upload->handleLocalFile($result, $fileStored, 'auto', $node, $name);
            if (isset($result['contentobject']) && (!$object instanceof eZContentObject)) {
                $object = $result['contentobject'];
                $object->setAttribute('remote_id', $remoteID);
                $object->store();
            } elseif (isset($result['errors']) && !empty($result['errors'])) {
                throw new \Exception(implode(', ', $result['errors']));
            }
            if ($object instanceof eZContentObject) {
                return $object->attribute('id');
                //$this->removeObjects[] = $object;
            } else {
                throw new \Exception('Errore caricando ' . var_export($item, 1) . ' ' . $fileStored);
            }
        }
        return null;
    }

    protected function getTemporaryFilePath($filename, $url = null, $fileEncoded = null)
    {
        $data = null;
        if ($fileEncoded !== null) {
            $binary = base64_decode($fileEncoded);
            eZFile::create($filename, self::tempDir(), $binary);
            $data = self::tempDir() . $filename;
        }
        elseif ($url !== null) {
            $binary = eZHTTPTool::getDataByURL($url);
            eZFile::create($filename, self::tempDir(), $binary);
            $data = self::tempDir() . $filename;
        }
        return $data;
    }

    protected static function tempDir()
    {
        //return sys_get_temp_dir()  . eZSys::fileSeparator();
        $path = eZDir::path(array(eZSys::cacheDirectory(), 'tmp'), true);
        eZDir::mkdir($path);

        return $path;
    }


    public function type( eZContentClassAttribute $attribute )
    {
        return array(
            'identifier' => 'array of id or remoteId or file or image'
        );
    }

    public function toCSVString($content, $language = null)
    {
        $data = array();
        foreach( $content as $metadata ){
            $data[] = $metadata['name'][$language];
        }
        return implode("\n", $data);
    }
}
