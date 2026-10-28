<?php

namespace Drupal\petrolejka_core;

/**
 * Nastavenia podcastu.
 *
 * Sú v State API, nie v konfigurácii, aby ich drush deploy (import
 * config/sync) neprepisoval zmenami z gitu – spravujú sa na produkcii.
 */
class PodcastSettings {

  const STATE_KEY = 'petrolejka_core.podcast';

  const DEFAULTS = [
    'title' => 'Petrolejka',
    'description' => 'Podcast občianskeho združenia Petrolejka z Liptovského Petra. Rozhovory so susedmi, história obce a dianie v Petre.',
    'author' => 'OZ Petrolejka',
    'owner_email' => 'info@petrolejka.sk',
    'category' => 'Society & Culture',
    'explicit' => FALSE,
    'cover_fid' => 0,
    'apple_url' => '',
    'spotify_url' => '',
  ];

  /**
   * Vráti všetky nastavenia doplnené o predvolené hodnoty.
   */
  public static function get(): array {
    return (\Drupal::state()->get(self::STATE_KEY) ?? []) + self::DEFAULTS;
  }

  /**
   * Uloží nastavenia.
   */
  public static function set(array $values): void {
    \Drupal::state()->set(self::STATE_KEY, array_intersect_key($values, self::DEFAULTS) + self::get());
    \Drupal::service('cache_tags.invalidator')->invalidateTags([self::STATE_KEY]);
  }

}
