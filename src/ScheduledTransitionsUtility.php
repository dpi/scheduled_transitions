<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
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
   * The bundle information service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * General service for moderation-related questions about Entity API.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * Constructs a new ScheduledTransitionsUtility.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The bundle information service.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   General service for moderation-related questions about Entity API.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $bundleInfo, ModerationInformationInterface $moderationInformation) {
    $this->entityTypeManager = $entityTypeManager;
    $this->bundleInfo = $bundleInfo;
    $this->moderationInformation = $moderationInformation;
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
   * {@inheritdoc}
   */
  public function getApplicableBundles(): array {
    $bundles = [];

    $bundleInfo = $this->bundleInfo->getAllBundleInfo();
    foreach ($bundleInfo as $entityTypeId => $entityTypeBundles) {
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
      $entityTypeBundles = array_filter($entityTypeBundles, function ($bundleId) use ($entityType): bool {
        return $this->moderationInformation->shouldModerateEntitiesOfBundle($entityType, $bundleId);
      }, \ARRAY_FILTER_USE_KEY);
      $bundles[$entityTypeId] = array_keys($entityTypeBundles);
    }

    return array_filter($bundles);
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
