<?php

/**
 * @file
 * Post update hooky (bežia v `drush updatedb`, teda pred importom konfigurácie).
 */

/**
 * Zmaže uzly typov „Číslo časopisu“ a „Osoba“ pred odstránením týchto typov.
 */
function petrolejka_core_post_update_delete_cislo_osoba(): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $ids = $storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', ['cislo', 'osoba'], 'IN')
    ->execute();
  if ($ids) {
    $storage->delete($storage->loadMultiple($ids));
  }
  return sprintf('Zmazaných uzlov (cislo, osoba): %d.', count($ids));
}

/**
 * Prevedie „Správy z obce“ na články s rubrikou „Z obce“.
 *
 * Beží pred importom konfigurácie, keď ešte existuje typ `sprava`, ale článok
 * ešte nemá pole `field_zdroj`. Zdroje sa preto odložia do State a doplní ich
 * petrolejka_core_deploy_merge_sprava() po importe.
 */
function petrolejka_core_post_update_sprava_to_article(): string {
  $etm = \Drupal::entityTypeManager();
  $storage = $etm->getStorage('node');
  $ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'sprava')->execute();
  if (!$ids) {
    return 'Žiadne správy na prevod.';
  }

  $terms = $etm->getStorage('taxonomy_term');
  $term = current($terms->loadByProperties(['vid' => 'rubrika', 'name' => 'Z obce']));
  if (!$term) {
    $term = $terms->create(['vid' => 'rubrika', 'name' => 'Z obce']);
    $term->save();
  }

  $zdroje = [];
  foreach ($storage->loadMultiple($ids) as $sprava) {
    $article = $storage->create([
      'type' => 'article',
      'langcode' => $sprava->language()->getId(),
      'title' => $sprava->label(),
      'uid' => $sprava->getOwnerId(),
      'status' => $sprava->isPublished(),
      'created' => $sprava->getCreatedTime(),
      'promote' => $sprava->isPromoted(),
      'sticky' => FALSE,
      'body' => $sprava->get('body')->getValue(),
      'field_rubrika' => $term->id(),
    ]);
    $article->save();
    if (!$sprava->get('field_zdroj')->isEmpty()) {
      $zdroje[$article->id()] = $sprava->get('field_zdroj')->getValue();
    }
  }
  \Drupal::state()->set('petrolejka_core.sprava_zdroj', $zdroje);
  $storage->delete($storage->loadMultiple($ids));

  return sprintf('Prevedených správ: %d (so zdrojom: %d).', count($ids), count($zdroje));
}
