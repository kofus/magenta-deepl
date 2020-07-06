<?php
namespace Kofus\Deepl\Service;
use Kofus\System\Service\AbstractService;
use Kofus\System\Node\NodeInterface;


class DeeplService extends AbstractService
{
    public function translateText($txt, $params=array())
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
    
    public function translateEntity(NodeInterface $entity)
    {
        $translationService = $this->getServiceLocator()->get('KofusTranslationService');
        $locales = $this->config()->get('locales.enabled');
        $builder = new \Kofus\Deepl\Annotation\AnnotationBuilder();
        $spec = $builder->getFormSpecification($entity);
        $arraySpec = \Zend\Stdlib\ArrayUtils::iteratorToArray($spec);
        $defaultLocale = $this->config()->get('locales.default');
        $deeplOptions = array('source_lang' => strtoupper(substr($defaultLocale, 0, 2)));
        
        foreach ($arraySpec['elements'] as $delta) {
            if (isset($delta['spec']['type']) && 'auto-translate' == $delta['spec']['type']) {
                $deeplOptions = array();
                if (isset($delta['spec']['options'])) $deeplOptions = $delta['spec']['options'];
                $attrib = $delta['spec']['name'];
                $method = 'get' . ucfirst($attrib);
                foreach ($locales as $locale) {
                    $value = $entity->$method();
                    
                    $deeplOptions['target_lang'] = strtoupper(substr($locale, 0, 2));
                    $tValue = $this->translateText($value, $deeplOptions);
                    $translationService->addNodeTranslation($entity, $method, $tValue, $locale);
                }
            }
        }
    }
}