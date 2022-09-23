<?php

use MapasCulturais\i;

$nav_items = [
    'main' => [
        'label' => '',
        'items' => [
            ['route' => 'panel/index', 'icon' => 'dashboard', 'label' => i::__('Visão geral')],
            ['route' => 'panel/agents', 'icon' => 'agent', 'label' => i::__('Meus Agentes')],
            ['route' => 'panel/spaces', 'icon' => 'space', 'label' => i::__('Meus Espaços')],
            ['route' => 'panel/events', 'icon' => 'event', 'label' => i::__('Meus Eventos')],
            ['route' => 'panel/projects', 'icon' => 'project', 'label' => i::__('Meus Projetos')],
        ]
    ],

    'opportunities' => [
        'label' => i::__('Editais e oportunidades'),
        'items' => [
            ['route' => 'panel/registrations', 'icon' => 'opportunity', 'label' => i::__('Minhas oportunidades')],
            ['route' => 'panel/registrations', 'icon' => 'opportunity', 'label' => i::__('Minhas inscrições')],
            ['route' => 'panel/accountability', 'icon' => 'opportunity', 'label' => i::__('Prestações de contas')],
        ]
    ],

    'more' => [
        'label' => i::__('Outras opções'),
        'items' => [
            ['route' => 'panel/apps', 'icon' => 'app', 'label' => i::__('Integrações')],

        ]
    ],

    'admin' => [
        'label' => i::__('Administração'),
        'condition' => function () use($app) {
            return $app->user->is('admin');
        },
        'items' => [

        ]
    ]
];

$app->applyHook('panel.nav', [&$nav_items]);

$result = [];

foreach ($nav_items as $id => $group) {
    $condition = $group['condition'] ?? function () { return true; };
    if (is_callable($condition) && $condition()) {
        unset($group['condition']);
        
        $items = [];
        foreach($group['items'] as $item) {
            $condition = $item['condition'] ?? function () { return true; };
            if (is_callable($condition) && $condition()) {
                unset($item['condition']);
                $items[] = $item;
            }
        }

        $result[] = [
            'id' => $id,
            'label' => $group['label'],
            'items' => $items
        ];
    }
}

$this->jsObject['config']['panelNav'] = $result;