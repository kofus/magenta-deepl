<?php

namespace Kofus\Deepl\View\Helper;
use Zend\View\Helper\AbstractHelper;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;


class DeeplHelper extends AbstractHelper implements ServiceLocatorAwareInterface
{
    protected $sm;
    protected $service;
    
    public function __invoke()
    {
    	if (! $this->service)
    		$this->service = $this->getServiceLocator()->get('KofusDeeplService');
    	return $this->service;
    }
    
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
    	$this->sm = $serviceLocator;
    }
    
    public function getServiceLocator()
    {
    	return $this->sm->getServiceLocator();
    }
}


