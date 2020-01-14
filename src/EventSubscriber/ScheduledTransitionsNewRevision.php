<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsEvents;
use Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Determines which revision should be transitioned.
 */
class ScheduledTransitionsNewRevision implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new ScheduledTransitionsNewRevision.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, LoggerInterface $logger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
  }

  /**
   * Default new revision functionality.
   *
   * Determines the new revision based on the revision ID on the scheduled
   * transition entity.
   *
   * @param \Drupal\scheduled_transitions\Event\ScheduledTransitionsNewRevisionEvent $event
   *   New revision event.
   */
  public function newRevision(ScheduledTransitionsNewRevisionEvent $event): void {
    $scheduledTransition = $event->getScheduledTransition();
    $scheduledTransitionId = $scheduledTransition->id();
    $targs = [
      '@id' => $scheduledTransitionId,
    ];

    $entity = $scheduledTransition->getEntity();
    if (!$entity) {
      $this->logger->info('Entity does not exist for scheduled transition #@id', $targs);
      return;
    }

    $entityRevisionId = $scheduledTransition->getEntityRevisionId();
    if (!$entityRevisionId) {
      // Revision ID with zero value usually means revision is determined
      // dynamically with another subscriber.
      $this->logger->debug('New revision will be determined dynamically for scheduled transition #@id', $targs);
      return;
    }

    /** @var \Drupal\Core\Entity\EntityStorageInterface|\Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());

    /** @var \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface $newRevision */
    $newRevision = $entityStorage->loadRevision($entityRevisionId);
    if (!isset($newRevision)) {
      $this->logger->info('Target revision does not exist for scheduled transition #@id', $targs);
      return;
    }

    $entityRevisionLanguage = $scheduledTransition->getEntityRevisionLanguage();
    if ($entityRevisionLanguage && $newRevision instanceof TranslatableInterface && $newRevision->hasTranslation($entityRevisionLanguage)) {
      $newRevision = $newRevision->getTranslation($entityRevisionLanguage);
    }

    $event->setNewRevision($newRevision);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ScheduledTransitionsEvents::NEW_REVISION][] = ['newRevision'];
    return $events;
  }

}
