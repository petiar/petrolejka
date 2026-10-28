<?php

namespace Drupal\petrolejka_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\petrolejka_core\DonationSettings;

/**
 * Nastavenia darovania: údaje pre PAY by square QR kód.
 */
class DonationSettingsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'petrolejka_core_donation_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $settings = DonationSettings::get();

    $form['info'] = [
      '#markup' => '<p>' . $this->t('Darovací formulár je na stránkach Zapoj sa a Podporte nás. Zobrazí sa, keď je vyplnený aspoň jeden Stripe odkaz alebo IBAN.') . '</p>',
    ];
    $form['stripe'] = [
      '#type' => 'details',
      '#title' => $this->t('Stripe – karta, Apple Pay, Google Pay (hlavná možnosť)'),
      '#description' => $this->t('V Stripe vytvorte Payment Links: jednorazový s možnosťou „zákazník si zvolí sumu“ a mesačný (predplatné). Vložte sem ich adresy (https://buy.stripe.com/…).'),
      '#open' => TRUE,
    ];
    $form['stripe']['stripe_once_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Odkaz na jednorazový dar'),
      '#default_value' => $settings['stripe_once_url'],
    ];
    $form['stripe']['stripe_monthly_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Odkaz na mesačný dar'),
      '#default_value' => $settings['stripe_monthly_url'],
    ];
    $form['bank'] = [
      '#type' => 'details',
      '#title' => $this->t('Bankový prevod – PAY by square QR kód (doplnková možnosť, bez poplatkov)'),
      '#open' => TRUE,
    ];
    $form['bank']['iban'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IBAN'),
      '#default_value' => $settings['iban'],
      '#placeholder' => 'SK00 0000 0000 0000 0000 0000',
    ];
    $form['bank']['bic'] = [
      '#type' => 'textfield',
      '#title' => $this->t('BIC / SWIFT'),
      '#description' => $this->t('Nepovinné, slovenské banky ho nepotrebujú.'),
      '#default_value' => $settings['bic'],
      '#size' => 12,
    ];
    $form['bank']['beneficiary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Názov príjemcu'),
      '#default_value' => $settings['beneficiary'],
      '#maxlength' => 70,
      '#required' => TRUE,
    ];
    $form['bank']['variable_symbol'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Variabilný symbol'),
      '#description' => $this->t('Nepovinné, najviac 10 číslic. Hodí sa na odlíšenie darov od iných platieb.'),
      '#default_value' => $settings['variable_symbol'],
      '#size' => 12,
      '#maxlength' => 10,
    ];
    $form['bank']['constant_symbol'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Konštantný symbol'),
      '#description' => $this->t('Nepovinné, najviac 4 číslice.'),
      '#default_value' => $settings['constant_symbol'],
      '#size' => 6,
      '#maxlength' => 4,
    ];
    $form['bank']['note'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Správa pre príjemcu'),
      '#description' => $this->t('Predvyplní sa v platbe. Ak darca zadá meno, pripojí sa za správu.'),
      '#default_value' => $settings['note'],
      '#maxlength' => 140,
    ];
    $form['bank']['amounts'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Predvolené sumy (€)'),
      '#description' => $this->t('Oddelené čiarkou, napr. 10, 20, 50. Darca môže zadať aj vlastnú sumu.'),
      '#default_value' => $settings['amounts'],
    ];
    $form['bank']['monthly'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Ponúknuť aj mesačnú podporu (trvalý príkaz)'),
      '#default_value' => $settings['monthly'],
    ];
    $form['bank']['monthly_day'] = [
      '#type' => 'number',
      '#title' => $this->t('Deň v mesiaci pre trvalý príkaz'),
      '#description' => $this->t('1 až 28, aby deň existoval v každom mesiaci.'),
      '#default_value' => $settings['monthly_day'],
      '#min' => 1,
      '#max' => 28,
      '#states' => ['visible' => [':input[name="monthly"]' => ['checked' => TRUE]]],
    ];
    $form['intro'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Úvodný text nad formulárom'),
      '#default_value' => $settings['intro'],
      '#rows' => 3,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Uložiť')];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $iban = strtoupper(preg_replace('/\s+/', '', (string) $form_state->getValue('iban')));
    if ($iban !== '' && !DonationSettings::validIban($iban)) {
      $form_state->setErrorByName('iban', $this->t('IBAN nie je platný, skontrolujte ho.'));
    }
    $bic = strtoupper(trim((string) $form_state->getValue('bic')));
    if ($bic !== '' && !preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic)) {
      $form_state->setErrorByName('bic', $this->t('BIC má 8 alebo 11 znakov, napr. TATRSKBX.'));
    }
    foreach (['stripe_once_url', 'stripe_monthly_url'] as $key) {
      $url = trim((string) $form_state->getValue($key));
      if ($url !== '' && !str_starts_with($url, 'https://')) {
        $form_state->setErrorByName($key, $this->t('Odkaz musí začínať https://'));
      }
    }
    foreach (['variable_symbol' => 10, 'constant_symbol' => 4] as $key => $max) {
      $value = trim((string) $form_state->getValue($key));
      if ($value !== '' && !preg_match('/^\d{1,' . $max . '}$/', $value)) {
        $form_state->setErrorByName($key, $this->t('Môže obsahovať najviac @max číslic.', ['@max' => $max]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = [];
    foreach (array_keys(DonationSettings::DEFAULTS) as $key) {
      $values[$key] = trim((string) $form_state->getValue($key));
    }
    $values['iban'] = strtoupper(preg_replace('/\s+/', '', $values['iban']));
    $values['monthly'] = (bool) $form_state->getValue('monthly');
    $values['monthly_day'] = min(28, max(1, (int) $form_state->getValue('monthly_day')));
    $values['bic'] = strtoupper($values['bic']);
    DonationSettings::set($values);
    $this->messenger()->addStatus($this->t('Nastavenia darovania sú uložené.'));
  }

}
