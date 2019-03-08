<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings for scheduled transitions.
 */
class ScheduledTransitionsSettingsForm extends ConfigFormBase {

  /**
   * Cache tag for scheduled transition settings.
   *
   * Features depending on settings from this form should add this tag for
   * invalidation.
   */
  public const SETTINGS_TAG = 'scheduled_transition_settings';

  /**
   * Cache tag invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagInvalidator;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagInvalidator
   *   Cache tag invalidator.
   */
  public function __construct(ConfigFactoryInterface $configFactory, CacheTagsInvalidatorInterface $cacheTagInvalidator) {
    parent::__construct($configFactory);
    $this->cacheTagInvalidator = $cacheTagInvalidator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('cache_tags.invalidator')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['scheduled_transitions.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'scheduled_transitions_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->config('scheduled_transitions.settings');
    $form['mirror_operation_view'] = [
      '#type' => 'select',
      '#title' => 'Mirror view scheduled transitions',
      '#description' => $this->t('When attempting to <em>view scheduled transitions</em> for an entity, defer access to another operation.'),
      '#field_suffix' => $this->t('operation'),
      '#options' => [
        'update' => $this->t('Update'),
      ],
      '#empty_option' => $this->t('- Disabled -'),
      '#default_value' => $settings->get('mirror_operations.view scheduled transition'),
    ];
    $form['mirror_operation_add'] = [
      '#type' => 'select',
      '#title' => 'Mirror add scheduled transitions',
      '#description' => $this->t('When attempting to <em>add scheduled transitions</em> for an entity, defer access to another operation.'),
      '#field_suffix' => $this->t('operation'),
      '#options' => [
        'update' => $this->t('Update'),
      ],
      '#empty_option' => $this->t('- Disabled -'),
      '#default_value' => $settings->get('mirror_operations.add scheduled transition'),
    ];

    $form['messages'] = [
      '#type' => 'details',
      '#title' => $this->t('Messages'),
      '#open' => TRUE,
    ];
    $form['message_transition_latest'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Latest'),
      '#group' => 'messages',
      '#default_value' => $settings->get('message_transition_latest'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:from-revision-id] [scheduled-transitions:from-state] [scheduled-transitions:to-state]'),
    ];
    $form['message_transition_historical'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Historical'),
      '#group' => 'messages',
      '#default_value' => $settings->get('message_transition_historical'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:from-revision-id] [scheduled-transitions:from-state] [scheduled-transitions:to-state]'),
    ];
    $form['message_transition_copy_latest_draft'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Copy latest draft'),
      '#group' => 'messages',
      '#default_value' => $settings->get('message_transition_copy_latest_draft'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:latest-state] [scheduled-transitions:latest-revision-id]'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->cacheTagInvalidator->invalidateTags([static::SETTINGS_TAG]);
    $this->config('scheduled_transitions.settings')
      ->set('mirror_operations.view scheduled transition', $form_state->getValue('mirror_operation_view'))
      ->set('mirror_operations.add scheduled transition', $form_state->getValue('mirror_operation_add'))
      ->set('message_transition_latest', $form_state->getValue('message_transition_latest'))
      ->set('message_transition_historical', $form_state->getValue('message_transition_historical'))
      ->set('message_transition_copy_latest_draft', $form_state->getValue('message_transition_copy_latest_draft'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
