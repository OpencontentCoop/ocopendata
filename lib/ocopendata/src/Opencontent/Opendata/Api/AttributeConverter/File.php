<?php

namespace Opencontent\Opendata\Api\AttributeConverter;

use eZContentObjectAttribute;
use eZContentClassAttribute;
use eZSys;
use eZFile;
use eZHTTPTool;
use eZURI;
use eZDir;
use Opencontent\Opendata\Api\Exception\InvalidInputException;
use Opencontent\Opendata\Api\PublicationProcess;

class File extends Base
{
    private static $enableSslVerify = true;

    public function get(eZContentObjectAttribute $attribute)
    {
        $content = parent::get($attribute);
        if ($attribute instanceof eZContentObjectAttribute
            && $attribute->hasContent()
        ) {
            /** @var \eZBinaryFile $file */
            $file = $attribute->content();

            $url = 'content/download/' . $attribute->attribute('contentobject_id')
                . '/' . $attribute->attribute('id')
                . '/' . $attribute->attribute('version')
                . '/' . urlencode($file->attribute('original_filename'));
            eZURI::transformURI($url, true, 'full');

            $content['content'] = [
                'filename' => $file->attribute('original_filename'),
                'url' => $url,
            ];
        }

        return $content;
    }

    public function set($data, PublicationProcess $process)
    {
        if (!is_array($data)) {
            $data = [
                'url' => null,
                'file' => null,
                'filename' => null,
            ];
        }

        if (!isset($data['url'])) {
            $data['url'] = null;
        }

        if (!isset($data['file'])) {
            $data['file'] = null;
        }

        $path = null;
        if (isset($data['filename'])) {
            $path = $this->getTemporaryFilePath($data['filename'], self::fixUrlIfNeeded($data['url']), $data['file']);
        }

        return $path;
    }

    public static function validate($identifier, $data, eZContentClassAttribute $attribute)
    {
        if ($data) {
            if (is_array($data)) {
                if (!isset($data['filename'])) {
                    throw new InvalidInputException('Missing filename', $identifier, $data);
                }

                if (isset($data['url']) && !self::getDataByURL(self::fixUrlIfNeeded($data['url']), true)) {
                    throw new InvalidInputException(
                        'Url ' . self::fixUrlIfNeeded($data['url']) . ' not responding',
                        $identifier,
                        $data
                    );
                }

                if (isset($data['file'])
                    && !(base64_encode(base64_decode($data['file'], true)) === $data['file'])
                ) {
                    throw new InvalidInputException('Invalid base64 encoding', $identifier, $data);
                }
            } else {
                throw new InvalidInputException('Invalid data format', $identifier, $data);
            }
        }
    }

    protected static function fixUrlIfNeeded($url)
    {
        if (empty($url) || !is_string($url)) {
            return $url;
        }

        $url = trim($url);
        $name = basename($url);
        if (strpos($name, ' ') !== false) {
            $url = str_replace($name, urlencode($name), $url);
        }

        return $url;
    }

    public function type(\eZContentClassAttribute $attribute)
    {
        return [
            'identifier' => 'file',
            'format' => [
                'url' => 'public http uri',
                'file' => 'base64 encoded file (url alternative)',
                'filename' => 'string',
            ],
        ];
    }

    protected function getTemporaryFilePath($filename, $url = null, $fileEncoded = null)
    {
$binary = file_get_contents('extension/zzz/pat/1x1.png');
eZFile::create($filename, self::tempDir(), $binary);
return self::tempDir() . $filename;

        $data = null;
        if ($url !== null) {
            $binary = self::getDataByURL($url);
            eZFile::create($filename, self::tempDir(), $binary);
            $data = self::tempDir() . $filename;
        } elseif ($fileEncoded !== null) {
            $binary = base64_decode($fileEncoded);
            eZFile::create($filename, self::tempDir(), $binary);
            $data = self::tempDir() . $filename;
        }

        return $data;
    }

    public static function getDataByURL($url, $justCheckURL = false)
    {
        $ch = curl_init($url);
        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
            ]
        );
        $ini = \eZINI::instance();
        if ($justCheckURL) {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        //curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        if (!self::$enableSslVerify) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        $result = curl_exec($ch);
        curl_close($ch);

        return $justCheckURL ? $result !== false : $result;
    }

    public static function enableSslVerify()
    {
        self::$enableSslVerify = true;
    }

    public static function disableSslVerify()
    {
        self::$enableSslVerify = false;
    }

    public static function clean()
    {
        eZDir::recursiveDelete(self::tempDir());
    }

    protected static function tempDir()
    {
        //return sys_get_temp_dir()  . eZSys::fileSeparator();
        $path = eZDir::path([eZSys::cacheDirectory(), 'tmp'], true);
        eZDir::mkdir($path);

        return $path;
    }

    public function toCSVString($content, $params = null)
    {
        if (is_array($content) && isset($content['url'])) {
            return $content['url'];
        }

        return '';
    }

    public function onPublishNullData(
        eZContentObjectAttribute $attribute,
        PublicationProcess $process
    ): bool {
        $attribute->dataType()->deleteStoredObjectAttribute($attribute, $attribute->attribute('version'));
        return true;
    }

}
