<?php

namespace Drupal\petrolejka_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\petrolejka_core\DonationSettings;

/**
 * Finančná podpora: tlačidlá na Stripe Payment Links a bankový prevod.
 *
 * Stripe rieši platbu kartou / Apple Pay / Google Pay na svojej stránke.
 * PAY by square QR kód sa generuje v prehliadači (js/donation.js).
 */
#[Block(
  id: 'petrolejka_donation',
  admin_label: new TranslatableMarkup('Darovanie (PAY by square)'),
)]
class DonationBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $settings = DonationSettings::get();
    $cache = ['tags' => [DonationSettings::STATE_KEY]];
    if ($settings['iban'] === '' && $settings['stripe_once_url'] === '' && $settings['stripe_monthly_url'] === '') {
      return ['#cache' => $cache];
    }
    $build = [
      '#theme' => 'petrolejka_donation',
      '#stripe_once_url' => $settings['stripe_once_url'] ?: NULL,
      '#stripe_monthly_url' => $settings['stripe_monthly_url'] ?: NULL,
      '#intro' => $settings['intro'],
      '#cache' => $cache,
    ];
    $iban = $settings['iban'];
    if ($iban === '') {
      return $build;
    }
    return $build + [
      '#amounts' => DonationSettings::amounts(),
      '#beneficiary' => $settings['beneficiary'],
      '#iban_formatted' => trim(chunk_split($iban, 4, ' ')),
      '#variable_symbol' => $settings['variable_symbol'],
      '#monthly_day' => $settings['monthly'] ? (int) $settings['monthly_day'] : NULL,
      '#attached' => [
        'library' => ['petrolejka_core/donation'],
        'drupalSettings' => [
          'petrolejkaDonation' => [
            'iban' => $iban,
            'bic' => $settings['bic'],
            'beneficiary' => $settings['beneficiary'],
            'variableSymbol' => $settings['variable_symbol'],
            'constantSymbol' => $settings['constant_symbol'],
            'note' => $settings['note'],
            'monthlyDay' => $settings['monthly'] ? (int) $settings['monthly_day'] : NULL,
          ],
        ],
      ],
    ];
  }

}
