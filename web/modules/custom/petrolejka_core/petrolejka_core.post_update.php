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
