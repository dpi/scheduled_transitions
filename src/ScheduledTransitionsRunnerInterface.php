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
   */
  public function runTransition(ScheduledTransitionInterface $scheduledTransition): void;

}
