<?php

namespace Drupal\petrolejka_core\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\petrolejka_core\PodcastSettings;

/**
 * Nastavenia podcastu: údaje kanála pre RSS feed.
 */
class PodcastSettingsForm extends FormBase {

  /**
   * Kategórie Apple Podcasts, ktoré sa k nám hodia.
   */
  const CATEGORIES = [
    'Society & Culture' => 'Spoločnosť a kultúra',
    'History' => 'História',
    'News' => 'Správy',
    'Arts' => 'Umenie',
    'Leisure' => 'Voľný čas',
    'Education' => 'Vzdelávanie',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'petrolejka_core_podcast_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $settings = PodcastSettings::get();
    $feed = Url::fromRoute('petrolejka_core.podcast_feed', [], ['absolute' => TRUE])->toString();

    $form['feed'] = [
      '#type' => 'item',
      '#title' => $this->t('Adresa feedu'),
      '#markup' => '<code>' . $feed . '</code>',
      '#description' => $this->t('Túto adresu zadajte v Apple Podcasts Connect a Spotify for Creators pri pridávaní podcastu.'),
    ];
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Názov podcastu'),
      '#default_value' => $settings['title'],
      '#required' => TRUE,
    ];
    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Popis'),
      '#default_value' => $settings['description'],
      '#required' => TRUE,
      '#rows' => 4,
    ];
    $form['author'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Autor'),
      '#default_value' => $settings['author'],
      '#required' => TRUE,
    ];
    $form['owner_email'] = [
      '#type' => 'email',
      '#title' => $this->t('E-mail vlastníka'),
      '#description' => $this->t('Na tento e-mail pošle Apple a Spotify overovací kód. Je viditeľný vo feede.'),
      '#default_value' => $settings['owner_email'],
      '#required' => TRUE,
    ];
    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Kategória'),
      '#options' => self::CATEGORIES,
      '#default_value' => $settings['category'],
    ];
    $form['explicit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Podcast obsahuje explicitný obsah (vulgarizmy a pod.)'),
      '#default_value' => $settings['explicit'],
    ];
    $form['cover'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Obal podcastu'),
      '#description' => $this->t('Štvorcový JPG alebo PNG, 1400 až 3000 px. Ak nenahráte vlastný, použije sa obal s logom Petrolejky.'),
      '#upload_location' => 'public://podcast',
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'jpg jpeg png'],
        'FileImageDimensions' => ['maxDimensions' => '3000x3000', 'minDimensions' => '1400x1400'],
      ],
      '#default_value' => $settings['cover_fid'] ? [$settings['cover_fid']] : [],
    ];
    $form['links'] = [
      '#type' => 'details',
      '#title' => $this->t('Odkazy na aplikácie'),
      '#description' => $this->t('Vyplňte, keď podcast schvália. Na stránke /podcast sa zobrazia tlačidlá.'),
      '#open' => TRUE,
    ];
    $form['links']['apple_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Apple Podcasts'),
      '#default_value' => $settings['apple_url'],
    ];
    $form['links']['spotify_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Spotify'),
      '#default_value' => $settings['spotify_url'],
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Uložiť')];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $old_fid = (int) PodcastSettings::get()['cover_fid'];
    $fid = (int) ($form_state->getValue('cover')[0] ?? 0);
    $usage = \Drupal::service('file.usage');
    if ($old_fid && $old_fid !== $fid && ($old = File::load($old_fid))) {
      $usage->delete($old, 'petrolejka_core', 'podcast', 1);
    }
    if ($fid && $fid !== $old_fid && ($file = File::load($fid))) {
      $file->setPermanent();
      $file->save();
      $usage->add($file, 'petrolejka_core', 'podcast', 1);
    }

    $values = ['explicit' => (bool) $form_state->getValue('explicit'), 'cover_fid' => $fid];
    foreach (['title', 'description', 'author', 'owner_email', 'category', 'apple_url', 'spotify_url'] as $key) {
      $values[$key] = trim((string) $form_state->getValue($key));
    }
    PodcastSettings::set($values);
    $this->messenger()->addStatus($this->t('Nastavenia podcastu sú uložené.'));
  }

}
