<?php

/**
 * @file
 * Jednorazové vytvorenie obsahového modelu Petrolejky.
 *
 * Spustenie: ddev drush php:script scripts/setup-structure.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

// --- Slovníky ---------------------------------------------------------------
$vocabs = [
  'rubrika' => 'Rubriky',
  'typ_podujatia' => 'Typy podujatí',
  'miesto' => 'Miesta',
];
foreach ($vocabs as $vid => $label) {
  if (!Vocabulary::load($vid)) {
    Vocabulary::create(['vid' => $vid, 'name' => $label])->save();
  }
}

// --- Typy obsahu --------------------------------------------------------------
$types = [
  'article' => ['Článok', 'Článok internetového časopisu.'],
  'cislo' => ['Číslo časopisu', 'Vydanie občasného internetového časopisu.'],
  'podujatie' => ['Podujatie', 'Kultúrne, spoločenské a komunitné podujatia.'],
  'sprava' => ['Správa z obce', 'Krátka správa o dianí v obci.'],
  'osoba' => ['Osoba', 'Autori, členovia rady, dobrovoľníci.'],
  'page' => ['Stránka', 'Statické stránky (O nás, Podporte nás…).'],
];
foreach ($types as $type => [$label, $desc]) {
  $nt = NodeType::load($type) ?? NodeType::create(['type' => $type]);
  $nt->set('name', $label)->set('description', $desc)->save();
}

// Pole body – profil standard v Drupale 11.3+ ho už nevytvára.
if (!FieldStorageConfig::loadByName('node', 'body')) {
  FieldStorageConfig::create(['field_name' => 'body', 'entity_type' => 'node', 'type' => 'text_with_summary'])->save();
}
foreach (array_keys($types) as $type) {
  if (!FieldConfig::loadByName('node', $type, 'body')) {
    FieldConfig::create([
      'field_name' => 'body',
      'entity_type' => 'node',
      'bundle' => $type,
      'label' => $type === 'cislo' ? 'Úvodník' : 'Text',
      'settings' => ['display_summary' => FALSE],
    ])->save();
  }
  $form = EntityFormDisplay::load("node.$type.default") ?? EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => $type, 'mode' => 'default', 'status' => TRUE]);
  $form->setComponent('body', ['type' => 'text_textarea_with_summary', 'weight' => 20])->save();
  $full = EntityViewDisplay::load("node.$type.default") ?? EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => $type, 'mode' => 'default', 'status' => TRUE]);
  $full->setComponent('body', ['type' => 'text_default', 'label' => 'hidden', 'weight' => 0])->save();
}

// --- Polia --------------------------------------------------------------------
$storages = [
  'field_perex' => ['string_long', []],
  'field_image' => ['image', []],
  'field_galeria' => ['image', [], -1],
  'field_rubrika' => ['entity_reference', ['target_type' => 'taxonomy_term']],
  'field_cislo' => ['entity_reference', ['target_type' => 'node']],
  'field_autor' => ['entity_reference', ['target_type' => 'node'], -1],
  'field_poradie' => ['integer', []],
  'field_datum' => ['daterange', ['datetime_type' => 'datetime']],
  'field_miesto' => ['entity_reference', ['target_type' => 'taxonomy_term']],
  'field_typ_podujatia' => ['entity_reference', ['target_type' => 'taxonomy_term']],
  'field_vstupne' => ['string', []],
  'field_registracia' => ['link', []],
  'field_zdroj' => ['link', []],
  'field_rola' => ['string', []],
  'field_prilohy' => ['file', [], -1],
  'field_podujatie' => ['entity_reference', ['target_type' => 'node']],
];
foreach ($storages as $name => $def) {
  if (FieldStorageConfig::loadByName('node', $name)) {
    continue;
  }
  FieldStorageConfig::create([
    'field_name' => $name,
    'entity_type' => 'node',
    'type' => $def[0],
    'settings' => $def[1],
    'cardinality' => $def[2] ?? 1,
  ])->save();
}

$ref_node = fn(array $bundles) => ['handler' => 'default:node', 'handler_settings' => ['target_bundles' => array_combine($bundles, $bundles)]];
$ref_term = fn(string $vid) => ['handler' => 'default:taxonomy_term', 'handler_settings' => ['target_bundles' => [$vid => $vid], 'auto_create' => TRUE]];
$img = fn(string $dir) => ['file_directory' => $dir, 'alt_field_required' => TRUE, 'file_extensions' => 'png jpg jpeg webp'];

// bundle => [field => [label, settings, required]]
$fields = [
  'article' => [
    'field_perex' => ['Perex', [], TRUE],
    'field_image' => ['Titulná fotka', $img('clanky/[date:custom:Y]')],
    'field_rubrika' => ['Rubrika', $ref_term('rubrika'), TRUE],
    'field_autor' => ['Autor', $ref_node(['osoba'])],
    'field_cislo' => ['Číslo časopisu', $ref_node(['cislo'])],
    'field_podujatie' => ['Súvisiace podujatie', $ref_node(['podujatie'])],
    'field_galeria' => ['Galéria', $img('galeria/[date:custom:Y]')],
  ],
  'cislo' => [
    'field_poradie' => ['Poradové číslo', [], TRUE],
    'field_image' => ['Obálka', $img('cisla')],
  ],
  'podujatie' => [
    'field_datum' => ['Dátum a čas', [], TRUE],
    'field_miesto' => ['Miesto', $ref_term('miesto')],
    'field_typ_podujatia' => ['Typ podujatia', $ref_term('typ_podujatia')],
    'field_image' => ['Plagát / obrázok', $img('podujatia')],
    'field_vstupne' => ['Vstupné', []],
    'field_registracia' => ['Registrácia', ['title' => 1, 'link_type' => 16]],
    'field_galeria' => ['Fotky z podujatia', $img('galeria/[date:custom:Y]')],
  ],
  'sprava' => [
    'field_zdroj' => ['Zdroj', ['title' => 1, 'link_type' => 16]],
  ],
  'osoba' => [
    'field_rola' => ['Rola', []],
    'field_image' => ['Fotka', $img('osoby')],
  ],
  'page' => [
    'field_image' => ['Obrázok', $img('stranky')],
    'field_prilohy' => ['Prílohy (dokumenty)', ['file_directory' => 'dokumenty', 'file_extensions' => 'pdf doc docx odt xls xlsx', 'description_field' => TRUE]],
  ],
];
foreach ($fields as $bundle => $list) {
  foreach ($list as $name => $def) {
    if (FieldConfig::loadByName('node', $bundle, $name)) {
      continue;
    }
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $def[0],
      'settings' => $def[1],
      'required' => $def[2] ?? FALSE,
    ])->save();
  }
}

// Článok zo štandardného profilu má tagy – nepotrebujeme ich.
if ($f = FieldConfig::loadByName('node', 'article', 'field_tags')) {
  $f->delete();
}
if ($f = FieldConfig::loadByName('node', 'article', 'comment')) {
  $f->delete();
}

// --- Formuláre a zobrazenia ---------------------------------------------------
$widgets = [
  'string_long' => ['string_textarea', []],
  'image' => ['image_image', []],
  'entity_reference' => ['entity_reference_autocomplete', []],
  'integer' => ['number', []],
  'daterange' => ['daterange_default', []],
  'string' => ['string_textfield', []],
  'link' => ['link_default', []],
  'file' => ['file_generic', []],
];
foreach ($fields as $bundle => $list) {
  $form = EntityFormDisplay::load("node.$bundle.default") ?? EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $full = EntityViewDisplay::load("node.$bundle.default") ?? EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  $teaser = EntityViewDisplay::load("node.$bundle.teaser") ?? EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'teaser', 'status' => TRUE]);
  $w = 0;
  foreach ($list as $name => $def) {
    $type = FieldStorageConfig::loadByName('node', $name)->getType();
    $form->setComponent($name, ['type' => $widgets[$type][0], 'weight' => ++$w]);
    // Šablóny témy si polia vykresľujú samy, tu ich len zapneme.
    $full->setComponent($name, ['label' => 'hidden', 'weight' => $w] + match ($type) {
      'entity_reference' => ['type' => 'entity_reference_label', 'settings' => ['link' => TRUE]],
      'image' => ['type' => 'image', 'settings' => ['image_style' => 'wide']],
      'daterange' => ['type' => 'daterange_default', 'settings' => ['format_type' => 'medium']],
      default => [],
    });
  }
  $teaser->setComponent('body', ['type' => 'text_summary_or_trimmed', 'label' => 'hidden', 'settings' => ['trim_length' => 300]]);
  foreach (['field_image', 'field_perex', 'field_rubrika', 'field_datum', 'field_miesto', 'field_rola'] as $t) {
    if (isset($list[$t])) {
      $teaser->setComponent($t, $full->getComponent($t));
    }
  }
  if (isset($list['field_image'])) {
    $teaser->setComponent('field_image', ['type' => 'image', 'label' => 'hidden', 'settings' => ['image_style' => 'large']]);
  }
  $form->save();
  $full->save();
  $teaser->save();
}

// Článok – titulný obrázok v teaseri.
EntityViewDisplay::load('node.article.teaser')
  ->setComponent('field_image', ['type' => 'image', 'label' => 'hidden', 'settings' => ['image_style' => 'large']])
  ->setComponent('field_perex', ['label' => 'hidden'])
  ->setComponent('field_rubrika', ['type' => 'entity_reference_label', 'label' => 'hidden', 'settings' => ['link' => FALSE]])
  ->removeComponent('body')
  ->save();

echo "Štruktúra hotová.\n";
