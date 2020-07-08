<?php
namespace Kofus\Deepl\Service;
use Kofus\System\Service\AbstractService;
use Kofus\System\Node\NodeInterface;
use Kofus\System\Entity\PageEntity;
use Kofus\System\Entity\TranslationEntity;


class DeeplService extends AbstractService
{
    public function translateText($txt, $params=array())
    {
        if (! $txt) return;
        
        $params['text'] = $txt;
        
        $cacheId = md5(implode('|', $params));
        
        $cache = false;
        if ($this->config()->get('deepl.cache'))
            $cache = $this->getServiceLocator()->get($this->config()->get('deepl.cache'));
        
        if (! $cache || ! $cache->hasItem($cacheId)) {
            $params['auth_key'] = $this->config()->get('deepl.auth_key');
            
            $translation = $this->sendTranslateRequest($params);
            
            if ($cache) $cache->setItem($cacheId, $translation);
        }

        if ($cache) return $cache->getItem($cacheId);
        return $translation;
    }
    
    protected function sendTranslateRequest($params)
    {
        $uri = $this->config()->get('deepl.uri', 'https://api.deepl.com/v2/translate');
        
        $client = new \Zend\Http\Client($uri);
        $client->setHeaders(array('Content-Type' => 'application/x-www-form-urlencoded'))
            ->setOptions(array('sslverifypeer' => false))
            ->setMethod('POST')
            ->setParameterPost($params);
        
        $response = $client->send();
        
        $responseArray = json_decode($response->getContent(), true);
        if (isset($responseArray['translations'])) {
            $translation = $responseArray['translations'][0]['text'];
        } elseif (isset($responseArray['message'])) {
            throw new \Exception('DeepL Exception: ' . $responseArray['message']);
        } else {
            throw new \Exception('DeepL Response Exception: ' . $response->getStatusCode() . $response->getBody());
        }
        
        return $translation;
    }
    
    public function translatePage(PageEntity $entity)
    {
        $translationService = $this->getServiceLocator()->get('KofusTranslationService');
        $locales = $this->config()->get('locales.enabled');
        $defaultLocale = $this->config()->get('locales.default');
        $deeplOptions = array('source_lang' => strtoupper(substr($defaultLocale, 0, 2)));
        $filterUriSegment = new \Kofus\System\Filter\UriSegment();
        
        foreach ($locales as $locale) {
            if ($defaultLocale == $locale) continue;
            $deeplOptions['target_lang'] = strtoupper(substr($locale, 0, 2));
            
            $title = $entity->getTitle();
            $tTitle = $this->translateText($title, $deeplOptions);
            if ($tTitle) $translationService->addNodeTranslation($entity, 'getTitle', $tTitle, $locale);
            
            $label = $entity->getNavLabel();
            if (! $label) $label = $title;
            $tLabel = $this->translateText($label, $deeplOptions);
            if ($tLabel) $translationService->addNodeTranslation($entity, 'getNavLabel', $tLabel, $locale);
            
            $uriSegment = $filterUriSegment->filter($tLabel);
            if ($uriSegment) $translationService->addNodeTranslation($entity, 'getUriSegment', $uriSegment, $locale);
            
            $deeplOptions['tag_handling'] = 'xml'; 
            $tValue = $this->translateText($entity->getContent(), $deeplOptions);
            if ($tValue) $translationService->addNodeTranslation($entity, 'getContent', $tValue, $locale);
            
        }
    }
    
    public function translateNode(NodeInterface $entity)
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
                    if ($defaultLocale == $locale) continue;
                    $value = $entity->$method();
                    if (! $value) continue;
                    
                    $deeplOptions['target_lang'] = strtoupper(substr($locale, 0, 2));
                    $tValue = $this->translateText($value, $deeplOptions);
                    if ($tValue) $translationService->addNodeTranslation($entity, $method, $tValue, $locale);
                }
            }
        }
    }
    
    public function finishTranslation(TranslationEntity $t)
    {
        if ($t->getValue()) return;
        
        $targetLang = strtoupper(substr($t->getLocale(), 0, 2));
        $sourceLang = strtoupper(substr($this->config()->get('locales.default'), 0, 2));
        $value = $this->translateText($t->getMsgId(), array('target_lang' => $targetLang, 'source_lang' => $sourceLang));
        $t->setValue($value);
        $this->em()->persist($t);
        $this->em()->flush();
        
    }
}