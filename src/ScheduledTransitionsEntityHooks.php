<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\scheduled_transitions\Form\Entity\ScheduledTransitionAddForm;
use Drupal\scheduled_transitions\Form\ScheduledTransitionForm;
use Drupal\scheduled_transitions\Routing\ScheduledTransitionsRouteProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity related hooks for Scheduled Transitions module.
 */
class ScheduledTransitionsEntityHooks implements ContainerInjectionInterface {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Array of IDs of Entity types using content moderation workflows.
   *
   * @var string[]
   */
  protected $moderatedEntityTypes;

  /**
   * General service for moderation-related questions about Entity API.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInformation;

  /**
   * Constructs a new ScheduledTransitionsEntityHooks.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderationInformation
   *   General service for moderation-related questions about Entity API.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ModerationInformationInterface $moderationInformation) {
    $this->entityTypeManager = $entityTypeManager;
    $this->moderationInformation = $moderationInformation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('content_moderation.moderation_information')
    );
  }

  /**
   * Implements hook_entity_type_build().
   *
   * @see \scheduled_transitions_entity_type_build()
   */
  public function entityTypeBuild(array &$entityTypes): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface[] $entityTypes */
    foreach ($entityTypes as $entityType) {
      if (!$entityType->hasLinkTemplate('canonical') || !$entityType instanceof ContentEntityType) {
        continue;
      }
      if ($entityType->id() === 'scheduled_transition') {
        continue;
      }

      // Add our entity route provider.
      $routeProviders = $entityType->getRouteProviderClasses() ?: [];
      $routeProviders['scheduled_transitions'] = ScheduledTransitionsRouteProvider::class;
      $entityType->setHandlerClass('route_provider', $routeProviders);

      $canonicalPath = $entityType->getLinkTemplate('canonical');
      $entityType
        ->setFormClass(ScheduledTransitionsRouteProvider::FORM, ScheduledTransitionForm::class)
        ->setLinkTemplate(ScheduledTransitionsRouteProvider::LINK_TEMPLATE, $canonicalPath . ScheduledTransitionsRouteProvider::CANONICAL_PATH_SUFFIX);

      $entityType
        ->setFormClass(ScheduledTransitionsRouteProvider::FORM_ADD, ScheduledTransitionAddForm::class)
        ->setLinkTemplate(ScheduledTransitionsRouteProvider::LINK_TEMPLATE_ADD, $canonicalPath . ScheduledTransitionsRouteProvider::CANONICAL_PATH_SUFFIX_ADD);
    }
  }

}
