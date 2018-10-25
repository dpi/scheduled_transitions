<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;

/**
 * Interface for transition executor.
 */
interface ScheduledTransitionsRunnerInterface {

  /**
   * Executes a transition.
   *
   * Ignores transition time as it is already checked by job runner.
   *
   * @param \Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface $scheduledTransition
   *   A scheduled transition.
   *
   * @throws \Drupal\scheduled_transitions\Exception\ScheduledTransitionMissingEntity
   *   Thrown if any entity or entity revision is missing for a transition.
   *   Transition is never automatically deleted if exception is thrown.
   */
  public function runTransition(ScheduledTransitionInterface $scheduledTransition): void;

}
