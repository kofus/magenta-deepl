<?php
namespace Kofus\Deepl;

return array(
    'service_manager' => array(
        'invokables' => array(
            'KofusDeeplService' => 'Kofus\Deepl\Service\DeeplService'
        )
    ),
    'view_helpers' => array(
        'invokables' => array(
            'deepl' => 'Kofus\Deepl\View\Helper\DeeplHelper'
        )
    )
);


