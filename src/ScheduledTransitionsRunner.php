<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\scheduled_transitions\Entity\ScheduledTransition;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Psr\Log\LoggerInterface;

/**
 * Executes transitions.
 */
class ScheduledTransitionsRunner implements ScheduledTransitionsRunnerInterface {

  use StringTranslationTrait;

  protected const LOCK_DURATION = 1800;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * System time.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * General service for moderation-related questions about Entity API.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * Constructs a new ScheduledTransitionsRunner.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   System time.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   General service for moderation-related questions about Entity API.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, TimeInterface $time, LoggerInterface $logger, ModerationInformationInterface $moderationInformation) {
    $this->entityTypeManager = $entityTypeManager;
    $this->time = $time;
    $this->logger = $logger;
    $this->moderationInformation = $moderationInformation;
  }

  /**
   * {@inheritdoc}
   */
  public function runTransition(ScheduledTransitionInterface $scheduledTransition): void {
    $entityTypeId = $scheduledTransition->getEntity()->getEntityTypeId();

    /** @var \Drupal\Core\Entity\EntityStorageInterface|\Drupal\Core\Entity\RevisionableStorageInterface $entityStorage */
    $entityStorage = $this->entityTypeManager->getStorage($entityTypeId);

    /** @var \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface $newRevision */
    $entityRevisionId = $scheduledTransition->getEntityRevisionId();
    $newRevision = $entityStorage->loadRevision($entityRevisionId);
    /** @var \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface $latest */
    $latestRevisionId = $entityStorage->getLatestRevisionId($newRevision->id());
    $latest = $entityStorage->loadRevision($latestRevisionId);

    $this->transitionEntity($scheduledTransition, $newRevision, $latest);

    $this->logger->info('Deleted scheduled transition #@id', [
      '@id' => $scheduledTransition->id(),
    ]);
    $scheduledTransition->delete();
  }

  /**
   * Transition a revision.
   *
   * @param \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition
   *   A scheduled transition entity.
   * @param \Drupal\Core\Entity\EntityInterface $newRevision
   *   A new default revision.
   * @param \Drupal\Core\Entity\EntityInterface $latest
   *   The latest current revision.
   */
  protected function transitionEntity(ScheduledTransitionInterface $scheduledTransition, EntityInterface $newRevision, EntityInterface $latest): void {
    // Check this now before any new saves.
    $isLatestRevisionPublished = $this->moderationInformation->isLiveRevision($latest);

    /** @var \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface $newRevision */
    /** @var \Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\RevisionableInterface $latest */
    $entityRevisionId = $scheduledTransition->getEntityRevisionId();

    $workflow = $this->moderationInformation->getWorkflowForEntity($latest);
    $workflowPlugin = $workflow->getTypePlugin();
    $states = $workflowPlugin->getStates();
    $originalNewRevisionState = $states[$newRevision->moderation_state->value ?? ''] ?? NULL;
    $originalLatestState = $states[$latest->moderation_state->value ?? ''] ?? NULL;
    $newState = $states[$scheduledTransition->getState()] ?? NULL;

    $targs = [
      '@revision_id' => $entityRevisionId,
      '@original_state' => $originalNewRevisionState->label(),
      '@new_state' => $newState->label(),
      '@original_revision_id' => $latest->getRevisionId(),
      '@original_latest_state' => $originalLatestState->label(),
    ];

    // Start the transition process.
    // Determine if latest before calling setNewRevision on $newRevision.
    $newIsLatest = $newRevision->getRevisionId() === $latest->getRevisionId();
    $newRevision->moderation_state = $newState->id();
    $newRevision->setNewRevision();

    // If publishing the latest revision, then only set moderation state.
    if ($newIsLatest) {
      $this->logger->info('Transitioning latest revision from @original_state to @new_state', $targs);
      if ($newRevision instanceof RevisionLogInterface) {
        $newRevision->setRevisionLogMessage($this->t('Scheduled transition: transitioning latest revision from @original_state to @new_state', $targs));
        $newRevision->setRevisionCreationTime($this->time->getRequestTime());
      }
      $newRevision->save();
    }
    // Otherwise if publishing a revision not on HEAD, create new revisions.
    else {
      $this->logger->info('Copied revision #@revision_id and changed from @original_state to @new_state', $targs);
      if ($newRevision instanceof RevisionLogInterface) {
        $newRevision->setRevisionLogMessage($this->t('Scheduled transition: copied revision #@revision_id and changed from @original_state to @new_state', $targs));
        $newRevision->setRevisionCreationTime($this->time->getRequestTime());
      }
      $newRevision->save();

      $options = $scheduledTransition->getOptions();
      // If the new revision is now a default, and the old latest was not a
      // default (e.g Draft), then pull it back on top.
      if (!empty($options[ScheduledTransition::OPTION_RECREATE_NON_DEFAULT_HEAD])) {
        if (!$isLatestRevisionPublished) {
          $latest->setNewRevision();
          $this->logger->info('Reverted @original_latest_state revision #@original_revision_id back to top', $targs);
          if ($latest instanceof RevisionLogInterface) {
            $latest->setRevisionLogMessage($this->t('Scheduled transition: reverted @original_latest_state revision #@original_revision_id back to top', $targs));
            $latest->setRevisionCreationTime($this->time->getRequestTime());
          }
          $latest->save();
        }
      }
    }
  }

}
