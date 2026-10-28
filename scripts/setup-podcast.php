<?php

/**
 * @file
 * Typ obsahu „Epizóda podcastu“, zoznam /podcast a URL aliasy.
 *
 * Spustenie: ddev drush php:script scripts/setup-podcast.php
 * Výsledok sa exportuje do config/sync (drush config:export).
 */

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\views\Entity\View;

$bundle = 'epizoda';
if (!NodeType::load($bundle)) {
  NodeType::create([
    'type' => $bundle,
    'name' => 'Epizóda podcastu',
    'description' => 'Epizóda podcastu Petrolejka s MP3 súborom. Objaví sa na webe aj vo feede pre podcastové aplikácie.',
  ])->save();
}

$storages = [
  'field_audio' => ['file', []],
  'field_cislo_epizody' => ['integer', ['unsigned' => TRUE]],
  'field_trvanie' => ['integer', ['unsigned' => TRUE]],
];
foreach ($storages as $name => [$type, $settings]) {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create(['field_name' => $name, 'entity_type' => 'node', 'type' => $type, 'settings' => $settings])->save();
  }
}

$fields = [
  'field_perex' => ['Krátky popis', 'Jedna-dve vety. Zobrazí sa v zozname epizód a v podcastových aplikáciách.', [], TRUE],
  'field_audio' => ['Zvukový súbor', 'MP3 (odporúčame 128 kbps mono alebo stereo) alebo M4A.', [
    'file_directory' => 'podcast/[date:custom:Y]',
    'file_extensions' => 'mp3 m4a',
    'max_filesize' => '256 MB',
    'description_field' => FALSE,
  ], TRUE],
  'field_cislo_epizody' => ['Číslo epizódy', '', ['min' => 1], FALSE],
  'field_trvanie' => ['Dĺžka (sekundy)', 'Vyplní sa automaticky z MP3 súboru pri uložení.', [], FALSE],
  'field_image' => ['Obrázok epizódy', 'Nepovinné. Štvorcový obrázok, aspoň 1400 × 1400 px. Inak sa použije obal podcastu.', [
    'file_directory' => 'podcast/obrazky',
    'file_extensions' => 'png jpg jpeg',
    'alt_field_required' => TRUE,
  ], FALSE],
];
foreach ($fields as $name => [$label, $description, $settings, $required]) {
  if (!FieldConfig::loadByName('node', $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $label,
      'description' => $description,
      'settings' => $settings,
      'required' => $required,
    ])->save();
  }
}
if (!FieldConfig::loadByName('node', $bundle, 'body')) {
  FieldConfig::create([
    'field_name' => 'body',
    'entity_type' => 'node',
    'bundle' => $bundle,
    'label' => 'Poznámky k epizóde',
    'description' => 'Hostia, témy, odkazy. Zobrazí sa na webe a v aplikáciách.',
    'settings' => ['display_summary' => FALSE],
  ])->save();
}

// Formulár.
$form = EntityFormDisplay::load("node.$bundle.default") ?? EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
$form->setComponent('field_cislo_epizody', ['type' => 'number', 'weight' => 1])
  ->setComponent('field_perex', ['type' => 'string_textarea', 'weight' => 2, 'settings' => ['rows' => 3]])
  ->setComponent('field_audio', ['type' => 'file_generic', 'weight' => 3])
  ->setComponent('body', ['type' => 'text_textarea', 'weight' => 4])
  ->setComponent('field_image', ['type' => 'image_image', 'weight' => 5])
  ->setComponent('field_trvanie', ['type' => 'number', 'weight' => 6])
  ->save();

// Zobrazenia – šablóny témy si polia vykresľujú samy.
$full = EntityViewDisplay::load("node.$bundle.default") ?? EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
$full->setComponent('body', ['type' => 'text_default', 'label' => 'hidden'])
  ->setComponent('field_perex', ['label' => 'hidden'])
  ->setComponent('field_image', ['type' => 'image', 'label' => 'hidden', 'settings' => ['image_style' => 'max_650x650']])
  ->removeComponent('field_audio')
  ->removeComponent('field_trvanie')
  ->removeComponent('field_cislo_epizody')
  ->save();
$teaser = EntityViewDisplay::load("node.$bundle.teaser") ?? EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'teaser', 'status' => TRUE]);
foreach (array_keys($teaser->getComponents()) as $name) {
  $teaser->removeComponent($name);
}
$teaser->setComponent('field_perex', ['label' => 'hidden'])->save();

// URL alias.
PathautoPattern::load('epizoda')?->delete();
$pattern = PathautoPattern::create([
  'id' => 'epizoda',
  'label' => 'Epizóda podcastu',
  'type' => 'canonical_entities:node',
  'pattern' => 'podcast/[node:title]',
  'weight' => 0,
]);
$pattern->addSelectionCondition([
  'id' => 'entity_bundle:node',
  'bundles' => [$bundle => $bundle],
  'negate' => FALSE,
  'context_mapping' => ['node' => 'node'],
]);
$pattern->save();

// Zoznam epizód /podcast – kópia view „dianie“ s iným typom obsahu.
View::load('podcast')?->delete();
$view = View::load('dianie')->createDuplicate();
$view->set('id', 'podcast');
$view->set('label', 'Podcast');
$displays = $view->get('display');
$displays['default']['display_options']['title'] = 'Podcast';
$displays['default']['display_options']['filters']['type']['value'] = [$bundle => $bundle];
$displays['default']['display_options']['empty']['area_text_custom']['content'] = 'Prvá epizóda sa pripravuje.';
// Box s odkazmi na odber pridáva modul (petrolejka_core_views_pre_render).
$displays['page_1']['display_options']['path'] = 'podcast';
$view->set('display', $displays);
$view->save();

\Drupal::service('router.builder')->rebuild();
echo "Podcast hotový.\n";
