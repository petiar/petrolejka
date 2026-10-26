<?php

/**
 * @file
 * Zoznamy (Views) pre časopis, podujatia a správy.
 *
 * Spustenie: ddev drush php:script scripts/setup-views.php
 */

use Drupal\views\Entity\View;

$base_filters = fn(string $bundle) => [
  'status' => [
    'id' => 'status', 'table' => 'node_field_data', 'field' => 'status',
    'plugin_id' => 'boolean', 'value' => '1', 'group' => 1,
    'expose' => ['operator' => FALSE], 'entity_type' => 'node', 'entity_field' => 'status',
  ],
  'type' => [
    'id' => 'type', 'table' => 'node_field_data', 'field' => 'type',
    'plugin_id' => 'bundle', 'value' => [$bundle => $bundle], 'group' => 1,
    'entity_type' => 'node', 'entity_field' => 'type',
  ],
];

$sort_created = [
  'created' => [
    'id' => 'created', 'table' => 'node_field_data', 'field' => 'created',
    'plugin_id' => 'date', 'order' => 'DESC', 'granularity' => 'second',
    'entity_type' => 'node', 'entity_field' => 'created',
  ],
];

$sort_datum = fn(string $order) => [
  'field_datum_value' => [
    'id' => 'field_datum_value', 'table' => 'node__field_datum', 'field' => 'field_datum_value',
    'plugin_id' => 'datetime', 'order' => $order, 'granularity' => 'second',
  ],
];

$filter_datum = fn(string $op) => [
  'field_datum_end_value' => [
    'id' => 'field_datum_end_value', 'table' => 'node__field_datum', 'field' => 'field_datum_end_value',
    'plugin_id' => 'datetime', 'operator' => $op, 'group' => 1,
    'value' => ['type' => 'offset', 'value' => 'now', 'min' => '', 'max' => ''],
  ],
];

$display = function (string $title, array $filters, array $sorts, int $items, string $empty) {
  return [
    'title' => $title,
    'query' => ['type' => 'views_query', 'options' => []],
    'access' => ['type' => 'perm', 'options' => ['perm' => 'access content']],
    'cache' => ['type' => 'tag', 'options' => []],
    'exposed_form' => ['type' => 'basic', 'options' => []],
    'pager' => ['type' => 'full', 'options' => ['items_per_page' => $items, 'offset' => 0]],
    'style' => ['type' => 'default', 'options' => ['row_class' => 'listing__item']],
    'row' => ['type' => 'entity:node', 'options' => ['view_mode' => 'teaser']],
    'filters' => $filters,
    'filter_groups' => ['operator' => 'AND', 'groups' => [1 => 'AND']],
    'sorts' => $sorts,
    'empty' => [
      'area_text_custom' => [
        'id' => 'area_text_custom', 'table' => 'views', 'field' => 'area_text_custom',
        'plugin_id' => 'text_custom', 'empty' => TRUE, 'content' => $empty,
      ],
    ],
    'fields' => [],
    'arguments' => [],
    'relationships' => [],
    'use_more' => FALSE,
    'css_class' => 'listing',
  ];
};

$views = [
  'casopis' => [
    'label' => 'Časopis',
    'default' => $display('Časopis', $base_filters('article'), $sort_created, 12, 'Zatiaľ tu nie sú žiadne články.'),
    'pages' => ['page_1' => ['path' => 'casopis', 'title' => NULL]],
  ],
  'cisla' => [
    'label' => 'Čísla časopisu',
    'default' => $display('Čísla časopisu', $base_filters('cislo'), $sort_created, 24, 'Prvé číslo sa pripravuje.'),
    'pages' => ['page_1' => ['path' => 'casopis/cisla', 'title' => NULL]],
  ],
  'dianie' => [
    'label' => 'Dianie v obci',
    'default' => $display('Dianie v obci', $base_filters('sprava'), $sort_created, 20, 'Zatiaľ žiadne správy.'),
    'pages' => ['page_1' => ['path' => 'dianie', 'title' => NULL]],
  ],
  'podujatia' => [
    'label' => 'Podujatia',
    'default' => $display('Pripravované podujatia', $base_filters('podujatie') + $filter_datum('>='), $sort_datum('ASC'), 20, 'Momentálne nemáme naplánované žiadne podujatia. Sledujte nás.'),
    'pages' => [
      'page_1' => ['path' => 'podujatia', 'title' => NULL],
      'page_2' => [
        'path' => 'podujatia/archiv',
        'title' => 'Archív podujatí',
        'overrides' => [
          'filters' => $base_filters('podujatie') + $filter_datum('<'),
          'sorts' => $sort_datum('DESC'),
        ],
      ],
    ],
  ],
];

foreach ($views as $id => $def) {
  if ($existing = View::load($id)) {
    $existing->delete();
  }
  $displays = [
    'default' => [
      'id' => 'default', 'display_plugin' => 'default', 'display_title' => 'Default',
      'position' => 0, 'display_options' => $def['default'],
    ],
  ];
  $pos = 1;
  foreach ($def['pages'] as $did => $p) {
    $opts = ['path' => $p['path']];
    if ($p['title']) {
      $opts['title'] = $p['title'];
      $opts['defaults']['title'] = FALSE;
    }
    foreach ($p['overrides'] ?? [] as $k => $v) {
      $opts[$k] = $v;
      $opts['defaults'][$k] = FALSE;
      if ($k === 'filters') {
        $opts['defaults']['filter_groups'] = FALSE;
        $opts['filter_groups'] = $def['default']['filter_groups'];
      }
    }
    $displays[$did] = [
      'id' => $did, 'display_plugin' => 'page', 'display_title' => 'Page',
      'position' => $pos++, 'display_options' => $opts,
    ];
  }
  View::create([
    'id' => $id,
    'label' => $def['label'],
    'base_table' => 'node_field_data',
    'base_field' => 'nid',
    'module' => 'views',
    'display' => $displays,
  ])->save();
}

\Drupal::service('router.builder')->rebuild();
echo "Views hotové.\n";
