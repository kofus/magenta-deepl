<?php
namespace Kofus\Deepl\Service;
use Kofus\System\Service\AbstractService;
use Zend\Uri\UriFactory;
use Zend\Http\Client as HttpClient;
use Kofus\GoogleMaps\Geocode;

class DeeplService extends AbstractService
{
    public function translate($txt, $params=array())
    {
        $uri = $this->config()->get('deepl.uri', 'https://api.deepl.com/v2/translate');
        
        $params['text'] = $txt;
        
        $cacheId = md5(implode('|', $params));
        
        $cache = $this->getServiceLocator()->get('cache');
        
        if (! $cache->hasItem($cacheId)) {
            $params['auth_key'] = $this->config()->get('deepl.auth_key');
            
            $client = new \Zend\Http\Client($uri);
            $client->setHeaders(array('Content-Type' => 'application/json'))
                ->setOptions(array('sslverifypeer' => false))
                ->setMethod('GET')
                ->setParameterGet($params);
            
            $response = $client->send();
            
            $responseArray = json_decode($response->getContent(), true);
            $translation = $responseArray['translations'][0]['text'];
            
            $cache->setItem($cacheId, $translation);
        }
        
        return $cache->getItem($cacheId);
    }
}