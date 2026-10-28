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
      '#markup' => '<p>' . $this->t('Z týchto údajov sa na stránke Zapoj sa vygeneruje PAY by square QR kód. Kým nie je vyplnený IBAN, formulár sa návštevníkom nezobrazí.') . '</p>',
    ];
    $form['iban'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IBAN'),
      '#default_value' => $settings['iban'],
      '#placeholder' => 'SK00 0000 0000 0000 0000 0000',
    ];
    $form['bic'] = [
      '#type' => 'textfield',
      '#title' => $this->t('BIC / SWIFT'),
      '#description' => $this->t('Nepovinné, slovenské banky ho nepotrebujú.'),
      '#default_value' => $settings['bic'],
      '#size' => 12,
    ];
    $form['beneficiary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Názov príjemcu'),
      '#default_value' => $settings['beneficiary'],
      '#maxlength' => 70,
      '#required' => TRUE,
    ];
    $form['variable_symbol'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Variabilný symbol'),
      '#description' => $this->t('Nepovinné, najviac 10 číslic. Hodí sa na odlíšenie darov od iných platieb.'),
      '#default_value' => $settings['variable_symbol'],
      '#size' => 12,
      '#maxlength' => 10,
    ];
    $form['constant_symbol'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Konštantný symbol'),
      '#description' => $this->t('Nepovinné, najviac 4 číslice.'),
      '#default_value' => $settings['constant_symbol'],
      '#size' => 6,
      '#maxlength' => 4,
    ];
    $form['note'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Správa pre príjemcu'),
      '#description' => $this->t('Predvyplní sa v platbe. Ak darca zadá meno, pripojí sa za správu.'),
      '#default_value' => $settings['note'],
      '#maxlength' => 140,
    ];
    $form['amounts'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Predvolené sumy (€)'),
      '#description' => $this->t('Oddelené čiarkou, napr. 10, 20, 50. Darca môže zadať aj vlastnú sumu.'),
      '#default_value' => $settings['amounts'],
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
    $values['bic'] = strtoupper($values['bic']);
    DonationSettings::set($values);
    $this->messenger()->addStatus($this->t('Nastavenia darovania sú uložené.'));
  }

}
