<?php

/**
 * @file
 * Bloky, menu, URL aliasy, view mode „lead“ a formulár na členstvo.
 *
 * Spustenie: ddev drush php:script scripts/setup-site.php
 */

use Drupal\block\Entity\Block;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\pathauto\Entity\PathautoPattern;
use Drupal\system\Entity\Menu;
use Drupal\webform\Entity\Webform;

// --- Bloky témy ---------------------------------------------------------------
foreach (Block::loadMultiple() as $block) {
  if ($block->getTheme() === 'petrolejka') {
    $block->delete();
  }
}
$blocks = [
  'petrolejka_main_menu' => ['system_menu_block:main', 'primary_menu', ['level' => 1, 'depth' => 1]],
  'petrolejka_messages' => ['system_messages_block', 'highlighted', []],
  'petrolejka_local_tasks' => ['local_tasks_block', 'highlighted', []],
  'petrolejka_page_title' => ['page_title_block', 'content', []],
  'petrolejka_content' => ['system_main_block', 'content', []],
  'petrolejka_footer_menu' => ['system_menu_block:footer', 'footer', ['level' => 1, 'depth' => 1]],
];
$w = 0;
foreach ($blocks as $id => [$plugin, $region, $settings]) {
  Block::create([
    'id' => $id,
    'theme' => 'petrolejka',
    'region' => $region,
    'plugin' => $plugin,
    'weight' => $w++,
    'settings' => ['label_display' => '0'] + $settings,
  ])->save();
}

// --- View mode „lead“ pre hlavný článok --------------------------------------
if (!EntityViewMode::load('node.lead')) {
  EntityViewMode::create(['id' => 'node.lead', 'label' => 'Hlavný článok', 'targetEntityType' => 'node'])->save();
}
$lead = EntityViewDisplay::load('node.article.lead') ?? EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => 'article', 'mode' => 'lead', 'status' => TRUE]);
foreach (array_keys($lead->getComponents()) as $name) {
  $lead->removeComponent($name);
}
$lead->setComponent('field_image', ['type' => 'image', 'label' => 'hidden', 'settings' => ['image_style' => 'wide']])
  ->setComponent('field_perex', ['label' => 'hidden'])
  ->save();

// Teaser obrázky v strednej veľkosti, ktorá stačí aj pre karty na celú šírku mobilu.
foreach (['article', 'cislo', 'podujatie', 'osoba', 'page'] as $bundle) {
  $teaser = EntityViewDisplay::load("node.$bundle.teaser");
  if ($teaser && $teaser->getComponent('field_image')) {
    $teaser->setComponent('field_image', ['type' => 'image', 'label' => 'hidden', 'settings' => ['image_style' => 'max_650x650']])->save();
  }
}

// --- URL aliasy ------------------------------------------------------------------
$patterns = [
  'clanok' => ['Článok', 'casopis/[node:title]', 'node', 'article'],
  'cislo' => ['Číslo časopisu', 'casopis/cislo-[node:field_poradie]', 'node', 'cislo'],
  'podujatie' => ['Podujatie', 'podujatia/[node:title]', 'node', 'podujatie'],
  'sprava' => ['Správa', 'dianie/[node:title]', 'node', 'sprava'],
  'osoba' => ['Osoba', 'ludia/[node:title]', 'node', 'osoba'],
  'stranka' => ['Stránka', '[node:title]', 'node', 'page'],
  'rubrika' => ['Rubrika', 'casopis/rubrika/[term:name]', 'taxonomy_term', 'rubrika'],
];
foreach ($patterns as $id => [$label, $pattern, $entity_type, $bundle]) {
  PathautoPattern::load($id)?->delete();
  $p = PathautoPattern::create([
    'id' => $id,
    'label' => $label,
    'type' => 'canonical_entities:' . $entity_type,
    'pattern' => $pattern,
    'weight' => 0,
  ]);
  $p->addSelectionCondition([
    'id' => 'entity_bundle:' . $entity_type,
    'bundles' => [$bundle => $bundle],
    'negate' => FALSE,
    'context_mapping' => [$entity_type => $entity_type],
  ]);
  $p->save();
}

// --- Menu päty ----------------------------------------------------------------
if (!Menu::load('footer')) {
  Menu::create(['id' => 'footer', 'label' => 'Päta'])->save();
}

// --- Formulár: prihláška za člena (čl. III stanov) -------------------------------
Webform::load('prihlaska')?->delete();
Webform::create([
  'id' => 'prihlaska',
  'title' => 'Prihláška za člena',
  'elements' => <<<YAML
meno:
  '#type': textfield
  '#title': 'Meno a priezvisko / názov organizácie'
  '#required': true
email:
  '#type': email
  '#title': 'E-mail'
  '#required': true
telefon:
  '#type': tel
  '#title': 'Telefón'
adresa:
  '#type': textfield
  '#title': 'Adresa'
ako_pomoct:
  '#type': checkboxes
  '#title': 'S čím by ste chceli pomôcť?'
  '#options':
    clanky: 'Písanie článkov a fotenie'
    podujatia: 'Organizovanie podujatí'
    historia: 'Zbieranie histórie a starých fotografií'
    technika: 'Technika, web, grafika'
    ine: 'Niečo iné'
sprava:
  '#type': textarea
  '#title': 'Čo by ste nám chceli odkázať?'
  '#rows': 4
suhlas:
  '#type': checkbox
  '#title': 'Súhlasím s cieľmi a stanovami združenia a so spracovaním osobných údajov na účely členstva.'
  '#required': true
YAML,
  'settings' => [
    'confirmation_type' => 'inline',
    'confirmation_message' => 'Ďakujeme! Vašu žiadosť posúdi rada združenia a ozveme sa vám e-mailom.',
    'form_submit_label' => 'Odoslať prihlášku',
  ],
])->save();

\Drupal::service('router.builder')->rebuild();
echo "Nastavenia hotové.\n";
