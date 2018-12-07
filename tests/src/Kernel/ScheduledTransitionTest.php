<?php

declare(strict_types = 1);

namespace Drupal\Tests\scheduled_transitions\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test_revlog\Entity\EntityTestWithRevisionLog;
use Drupal\KernelTests\KernelTestBase;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\Entity\User;

/**
 * Tests basic functionality of scheduled_transitions fields.
 *
 * @group scheduled_transitions
 */
class ScheduledTransitionTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test_revlog',
    'entity_test',
    'scheduled_transitions',
    'content_moderation',
    'workflows',
    'dynamic_entity_reference',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('entity_test_revlog');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('user');
    $this->installEntitySchema('scheduled_transition');
    $this->installSchema('system', ['queue']);
    $this->installConfig(['scheduled_transitions']);
  }

  /**
   * Tests a scheduled revision.
   *
   * Publish a revision in the past (not latest).
   */
  public function testScheduledRevision() {
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $entity = EntityTestWithRevisionLog::create(['type' => 'entity_test_revlog']);
    $entity->moderation_state = 'draft';
    $entity->save();
    $entityId = $entity->id();
    $this->assertEquals(1, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->moderation_state = 'draft';
    $entity->save();
    $this->assertEquals(2, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->moderation_state = 'draft';
    $entity->save();
    $this->assertEquals(3, $entity->getRevisionId());

    $newState = 'published';
    $scheduledTransition = ScheduledTransition::create([
      'entity' => $entity,
      'entity_revision_id' => 2,
      'author' => $author,
      'workflow' => $workflow->id(),
      'moderation_state' => $newState,
      'transition_on' => (new \DateTime('2 Feb 2018 11am'))->getTimestamp(),
    ]);
    $scheduledTransition->save();

    $this->runTransition($scheduledTransition);

    $revisionIds = $this->getRevisionIds($entity);
    $this->assertCount(4, $revisionIds);

    // Reload the entity.
    $entity = EntityTestWithRevisionLog::load($entityId);
    $this->assertEquals('published', $entity->moderation_state->value, sprintf('Entity is now %s.', $newState));
    $this->assertEquals('Scheduled transition: copied revision #2 and changed from Draft to Published', $entity->getRevisionLogMessage());
  }

  /**
   * Tests a scheduled revision.
   *
   * Publish the lateset revision.
   */
  public function testScheduledRevisionLatestNonDefault() {
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $entity = EntityTestWithRevisionLog::create(['type' => 'entity_test_revlog']);
    $entity->moderation_state = 'draft';
    $entity->save();
    $entityId = $entity->id();
    $this->assertEquals(1, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->moderation_state = 'draft';
    $entity->save();
    $this->assertEquals(2, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->moderation_state = 'draft';
    $entity->save();
    $this->assertEquals(3, $entity->getRevisionId());

    $newState = 'published';
    $scheduledTransition = ScheduledTransition::create([
      'entity' => $entity,
      'entity_revision_id' => 3,
      'author' => $author,
      'workflow' => $workflow->id(),
      'moderation_state' => $newState,
      'transition_on' => (new \DateTime('2 Feb 2018 11am'))->getTimestamp(),
    ]);
    $scheduledTransition->save();

    $this->runTransition($scheduledTransition);

    $revisionIds = $this->getRevisionIds($entity);
    $this->assertCount(4, $revisionIds);

    // Reload the entity.
    $entity = EntityTestWithRevisionLog::load($entityId);
    $this->assertEquals('published', $entity->moderation_state->value, sprintf('Entity is now %s.', $newState));
    $this->assertEquals('Scheduled transition: transitioning latest revision from Draft to Published', $entity->getRevisionLogMessage());
  }

  /**
   * Tests a scheduled revision.
   */
  public function testScheduledRevisionRecreateNonDefaultHead() {
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $entity = EntityTestWithRevisionLog::create(['type' => 'entity_test_revlog']);
    $entity->name = 'foobar1';
    $entity->moderation_state = 'draft';
    $entity->save();
    $entityId = $entity->id();
    $this->assertEquals(1, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->name = 'foobar2';
    $entity->moderation_state = 'draft';
    $entity->save();
    $this->assertEquals(2, $entity->getRevisionId());

    $revision3State = 'draft';
    $entity->setNewRevision();
    $entity->name = 'foobar3';
    $entity->moderation_state = $revision3State;
    $entity->save();
    $this->assertEquals(3, $entity->getRevisionId());

    $newState = 'published';
    $scheduledTransition = ScheduledTransition::create([
      'entity' => $entity,
      'entity_revision_id' => 2,
      'author' => $author,
      'workflow' => $workflow->id(),
      'moderation_state' => $newState,
      'transition_on' => (new \DateTime('2 Feb 2018 11am'))->getTimestamp(),
      'options' => [
        ['recreate_non_default_head' => TRUE],
      ],
    ]);
    $scheduledTransition->save();

    $this->runTransition($scheduledTransition);

    $revisionIds = $this->getRevisionIds($entity);
    $this->assertCount(5, $revisionIds);

    // Reload the entity default revision.
    $entityStorage = \Drupal::entityTypeManager()->getStorage('entity_test_revlog');
    $entity = EntityTestWithRevisionLog::load($entityId);
    $revision4 = $entityStorage->loadRevision($revisionIds[3]);
    $revision5 = $entityStorage->loadRevision($revisionIds[4]);
    $this->assertEquals($revision4->getRevisionId(), $entity->getRevisionId(), 'Default revision is revision 4');
    $this->assertEquals($newState, $entity->moderation_state->value, sprintf('Entity is now %s.', $newState));

    $this->assertEquals($revision4->name->value, 'foobar2');
    $this->assertEquals('Scheduled transition: copied revision #2 and changed from Draft to Published', $revision4->getRevisionLogMessage());

    $this->assertEquals($revision5->name->value, 'foobar3');
    $this->assertEquals('Scheduled transition: reverted Draft revision #3 back to top', $revision5->getRevisionLogMessage());
  }

  /**
   * Tests a scheduled revision.
   *
   * The latest revision is published, ensure it doesnt get republished when
   * recreate_non_default_head is TRUE.
   */
  public function testScheduledRevisionRecreateDefaultHead() {
    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addEntityTypeAndBundle('entity_test_revlog', 'entity_test_revlog');
    $workflow->save();

    $author = User::create([
      'uid' => 2,
      'name' => $this->randomMachineName(),
    ]);
    $author->save();

    $entity = EntityTestWithRevisionLog::create(['type' => 'entity_test_revlog']);
    $entity->name = 'foobar1';
    $entity->moderation_state = 'draft';
    $entity->save();
    $entityId = $entity->id();
    $this->assertEquals(1, $entity->getRevisionId());

    $entity->setNewRevision();
    $entity->name = 'foobar2';
    $entity->moderation_state = 'draft';
    $entity->save();
    $this->assertEquals(2, $entity->getRevisionId());

    $revision3State = 'published';
    $entity->setNewRevision();
    $entity->name = 'foobar3';
    $entity->moderation_state = $revision3State;
    $entity->save();
    $this->assertEquals(3, $entity->getRevisionId());

    $newState = 'published';
    $scheduledTransition = ScheduledTransition::create([
      'entity' => $entity,
      'entity_revision_id' => 2,
      'author' => $author,
      'workflow' => $workflow->id(),
      'moderation_state' => $newState,
      'transition_on' => (new \DateTime('2 Feb 2018 11am'))->getTimestamp(),
      'options' => [
        ['recreate_non_default_head' => TRUE],
      ],
    ]);
    $scheduledTransition->save();

    $this->runTransition($scheduledTransition);

    $revisionIds = $this->getRevisionIds($entity);
    $this->assertCount(4, $revisionIds);

    // Reload the entity default revision.
    $entityStorage = \Drupal::entityTypeManager()->getStorage('entity_test_revlog');
    $entity = EntityTestWithRevisionLog::load($entityId);
    $revision4 = $entityStorage->loadRevision($revisionIds[3]);
    $this->assertEquals($revision4->getRevisionId(), $entity->getRevisionId(), 'Default revision is revision 4');
    $this->assertEquals($newState, $entity->moderation_state->value, sprintf('Entity is now %s.', $newState));

    $this->assertEquals($revision4->name->value, 'foobar2');
    $this->assertEquals('Scheduled transition: copied revision #2 and changed from Draft to Published', $revision4->getRevisionLogMessage());
  }

  /**
   * Checks and runs any ready transitions.
   *
   * @param \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition
   *   A scheduled transition.
   */
  protected function runTransition(ScheduledTransitionInterface $scheduledTransition): void {
    $runner = $this->container->get('scheduled_transitions.runner');
    $runner->runTransition($scheduledTransition);
  }

  /**
   * Get revision IDs for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity.
   *
   * @return int[]
   *   Revision IDs.
   */
  protected function getRevisionIds(EntityInterface $entity): array {
    $entityTypeId = $entity->getEntityTypeId();
    $entityDefinition = \Drupal::entityTypeManager()->getDefinition($entityTypeId);
    $entityStorage = \Drupal::entityTypeManager()->getStorage($entityTypeId);

    /** @var int[] $ids */
    $ids = $entityStorage->getQuery()
      ->allRevisions()
      ->condition($entityDefinition->getKey('id'), $entity->id())
      ->execute();
    return array_keys($ids);
  }

}
