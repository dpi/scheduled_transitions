<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Utilities for Scheduled Transitions module.
 */
class ScheduledTransitionsUtility implements ScheduledTransitionsUtilityInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ScheduledTransitionsUtility.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransitions(EntityInterface $entity): array {
    $transitionStorage = $this->entityTypeManager->getStorage('scheduled_transition');
    $ids = $transitionStorage->getQuery()
      ->condition('entity__target_type', $entity->getEntityTypeId())
      ->condition('entity__target_id', $entity->id())
      ->execute();
    return $transitionStorage->loadMultiple($ids);
  }

  /**
   * Creates a cache tag for scheduled transitions related to an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   An entity.
   *
   * @return string
   *   Cache tag to add to lists showing scheduled transitions for an entity.
   */
  public static function createScheduledTransitionsCacheTag(EntityInterface $entity): string {
    return sprintf('scheduled_transitions_for:%s:%s', $entity->getEntityTypeId(), $entity->id());
  }

}
