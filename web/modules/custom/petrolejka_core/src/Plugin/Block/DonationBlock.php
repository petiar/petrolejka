<?php

namespace Drupal\petrolejka_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\petrolejka_core\DonationSettings;

/**
 * Formulár na finančnú podporu s PAY by square QR kódom.
 *
 * QR kód sa generuje v prehliadači (js/donation.js), server nič neodosiela.
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
    if ($settings['iban'] === '') {
      return ['#cache' => $cache];
    }
    $iban = $settings['iban'];
    return [
      '#theme' => 'petrolejka_donation',
      '#intro' => $settings['intro'],
      '#amounts' => DonationSettings::amounts(),
      '#beneficiary' => $settings['beneficiary'],
      '#iban_formatted' => trim(chunk_split($iban, 4, ' ')),
      '#variable_symbol' => $settings['variable_symbol'],
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
          ],
        ],
      ],
      '#cache' => $cache,
    ];
  }

}
