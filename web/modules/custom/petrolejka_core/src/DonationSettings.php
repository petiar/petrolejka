<?php

namespace Drupal\petrolejka_core;

/**
 * Nastavenia darovania cez PAY by square.
 *
 * Rovnako ako PodcastSettings sú v State API, aby ich drush deploy
 * neprepisoval – spravujú sa na produkcii v administrácii.
 */
class DonationSettings {

  const STATE_KEY = 'petrolejka_core.donation';

  const DEFAULTS = [
    'iban' => '',
    'bic' => '',
    'beneficiary' => 'Občianske združenie Petrolejka',
    'variable_symbol' => '',
    'constant_symbol' => '',
    'note' => 'Dar pre OZ Petrolejka',
    'amounts' => '10, 20, 50',
    'intro' => 'Podporte činnosť združenia ľubovoľnou sumou. Zadajte sumu, naskenujte QR kód v aplikácii svojej banky a platbu potvrďte.',
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

  /**
   * Predvolené sumy ako zoznam čísel.
   */
  public static function amounts(): array {
    // Oddeľovač je čiarka, bodkočiarka alebo medzera; desatinné číslo s bodkou.
    $amounts = array_map('floatval', preg_split('/[;,\s]+/', self::get()['amounts']));
    return array_values(array_filter($amounts, fn($a) => $a > 0));
  }

  /**
   * Overí IBAN (dĺžka, formát a kontrolný súčet mod 97).
   */
  public static function validIban(string $iban): bool {
    $iban = strtoupper(preg_replace('/\s+/', '', $iban));
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $iban)) {
      return FALSE;
    }
    $numeric = '';
    foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $char) {
      $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
    }
    $remainder = 0;
    foreach (str_split($numeric, 7) as $chunk) {
      $remainder = (int) ($remainder . $chunk) % 97;
    }
    return $remainder === 1;
  }

}
