<?php

/**
 * @file
 * Jednorazové úpravy obsahu pri deployi (drush deploy).
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Pridá „Podcast“ do hlavného menu za „Podujatia“.
 */
function petrolejka_core_deploy_podcast_menu(): string {
  $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  if ($storage->loadByProperties(['menu_name' => 'main', 'link__uri' => 'internal:/podcast'])) {
    return 'Odkaz Podcast už v menu je.';
  }
  $links = $storage->loadByProperties(['menu_name' => 'main']);
  uasort($links, fn($a, $b) => $a->getWeight() <=> $b->getWeight());
  $weight = 0;
  $podcast_weight = NULL;
  foreach ($links as $link) {
    $link->set('weight', $weight++)->save();
    if ($link->getTitle() === 'Podujatia') {
      $podcast_weight = $weight++;
    }
  }
  MenuLinkContent::create([
    'menu_name' => 'main',
    'title' => 'Podcast',
    'link' => ['uri' => 'internal:/podcast'],
    'weight' => $podcast_weight ?? $weight,
  ])->save();
  return 'Do hlavného menu pribudol Podcast.';
}

/**
 * Dokončí zlúčenie správ do článkov a premenovanie „Časopis“ na „Články“.
 *
 * Doplní zdroje prevedených správ, upraví menu a pregeneruje aliasy článkov
 * a rubrík podľa nových vzorov (clanky/…).
 */
function petrolejka_core_deploy_merge_sprava(): string {
  $etm = \Drupal::entityTypeManager();
  $nodes = $etm->getStorage('node');

  $state = \Drupal::state();
  $zdroje = $state->get('petrolejka_core.sprava_zdroj', []);
  foreach ($nodes->loadMultiple(array_keys($zdroje)) as $nid => $node) {
    $node->set('field_zdroj', $zdroje[$nid])->save();
  }
  $state->delete('petrolejka_core.sprava_zdroj');

  $links = $etm->getStorage('menu_link_content');
  foreach ($links->loadByProperties(['link__uri' => 'internal:/casopis/cisla']) as $link) {
    $link->delete();
  }
  foreach ($links->loadByProperties(['menu_name' => 'main', 'link__uri' => 'internal:/casopis']) as $link) {
    $link->set('title', 'Články')->set('link', ['uri' => 'internal:/clanky'])->save();
  }

  $generator = \Drupal::service('pathauto.generator');
  $count = 0;
  $article_ids = $nodes->getQuery()->accessCheck(FALSE)->condition('type', 'article')->execute();
  foreach ($nodes->loadMultiple($article_ids) as $node) {
    $count += (bool) $generator->updateEntityAlias($node, 'bulkupdate');
  }
  $terms = $etm->getStorage('taxonomy_term');
  $term_ids = $terms->getQuery()->accessCheck(FALSE)->condition('vid', 'rubrika')->execute();
  foreach ($terms->loadMultiple($term_ids) as $term) {
    $count += (bool) $generator->updateEntityAlias($term, 'bulkupdate');
  }

  return sprintf('Doplnené zdroje: %d, nové aliasy: %d.', count($zdroje), $count);
}
