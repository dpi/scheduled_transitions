<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings for scheduled transitions.
 */
class ScheduledTransitionsSettingsForm extends ConfigFormBase {

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
    $form['message_transition_latest'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Latest'),
      '#default_value' => $this->config('scheduled_transitions.settings')->get('message_transition_latest'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:from-state] [scheduled-transitions:to-state]'),
    ];
    $form['message_transition_historical'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Historical'),
      '#default_value' => $this->config('scheduled_transitions.settings')->get('message_transition_historical'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:from-revision-id] [scheduled-transitions:from-state] [scheduled-transitions:to-state]'),
    ];
    $form['message_transition_copy_latest_draft'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Copy latest draft'),
      '#default_value' => $this->config('scheduled_transitions.settings')->get('message_transition_copy_latest_draft'),
      '#description' => $this->t('Available tokens: [scheduled-transitions:latest-state] [scheduled-transitions:latest-revision-id]'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('scheduled_transitions.settings')
      ->set('message_transition_latest', $form_state->getValue('message_transition_latest'))
      ->set('message_transition_historical', $form_state->getValue('message_transition_historical'))
      ->set('message_transition_copy_latest_draft', $form_state->getValue('message_transition_copy_latest_draft'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
