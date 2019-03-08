<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests settings form.
 *
 * @group scheduled_transitions
 */
class ScheduledTransitionSettingsFormTest extends BrowserTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'scheduled_transitions',
    'content_moderation',
    'workflows',
    'dynamic_entity_reference',
    'user',
    'system',
  ];

  /**
   * Tests mirror operations settings.
   */
  public function testMirrorOperations(): void {
    // Set operation values to nothing.
    \Drupal::configFactory()
      ->getEditable('scheduled_transitions.settings')
      ->clear('mirror_operations.view scheduled transition')
      ->clear('mirror_operations.add scheduled transition')
      ->save(TRUE);

    $currentUser = $this->drupalCreateUser(['administer scheduled transitions']);
    $this->drupalLogin($currentUser);
    $url = Url::fromRoute('scheduled_transitions.settings');
    $this->drupalGet($url);

    $this->assertSession()->fieldValueEquals('mirror_operation_view', '');
    $this->assertSession()->fieldValueEquals('mirror_operation_add', '');

    $edit = [
      'mirror_operation_view' => 'update',
      'mirror_operation_add' => 'update',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertSession()->fieldValueEquals('mirror_operation_view', 'update');
    $this->assertSession()->fieldValueEquals('mirror_operation_add', 'update');

  }

}
