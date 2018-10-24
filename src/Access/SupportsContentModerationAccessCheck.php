<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\scheduled_transitions\Routing\ScheduledTransitionsRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Check if entity supports content moderation.
 *
 * Supports revisions, has active workflow, etc.
 */
class SupportsContentModerationAccessCheck implements AccessInterface {

  /**
   * Value of 'applies_to' in service tag.
   */
  public const ACCESS_CHECK_ID = '_scheduled_transitions_supports_content_moderation';

  /**
   * General service for moderation-related questions about Entity API.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * Constructs a new SupportsContentModerationAccessCheck.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   General service for moderation-related questions about Entity API.
   */
  public function __construct(ModerationInformationInterface $moderationInformation) {
    $this->moderationInformation = $moderationInformation;
  }

  /**
   * Checks the entity supports content moderation.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account): AccessResultInterface {
    /** @var string $routeEntityType */
    $routeEntityTypeId = $route
      ->getOption(ScheduledTransitionsRouteProvider::ROUTE_ENTITY_TYPE);

    $entity = $route_match->getParameter($routeEntityTypeId);
    if ($entity instanceof ContentEntityInterface) {
      if ($this->moderationInformation->isModeratedEntity($entity)) {
        return AccessResult::allowed()->addCacheableDependency($entity);
      }
      return AccessResult::forbidden('Entity does not support content moderation.')->addCacheableDependency($entity);
    }
    return AccessResult::forbidden('No entity provided.');
  }

}
