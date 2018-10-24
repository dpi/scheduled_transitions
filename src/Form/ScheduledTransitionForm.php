<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\scheduled_transitions\Entity\ScheduledTransitionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Show all scheduled transitions for an entity.
 */
class ScheduledTransitionForm extends ContentEntityForm {

  /**
   * Various date related functionality.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, DateFormatterInterface $dateFormatter) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->dateFormatter = $dateFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $entity = $this->getEntity();

    $tableHeadings = [
      'from_revision' => [
        'data' => $this->t('From revision'),
      ],
      'from_state' => [
        'data' => $this->t('From state'),
      ],
      'to_state' => [
        'data' => $this->t('To state'),
      ],
      'date' => [
        'data' => $this->t('On date'),
        'field' => 'transition_on',
        'specifier' => 'transition_on',
        'sort' => 'asc',
      ],
      'author' => [
        'data' => $this->t('Scheduled by'),
      ],
      'operations' => [
        'data' => $this->t('Operations'),
      ],
    ];

    $form['table'] = [
      '#type' => 'table',
      '#header' => $tableHeadings,
      '#empty' => $this->t('There are no scheduled transitions for @entity', [
        '@entity' => $entity->label(),
      ]),
    ];

    $entityTypeId = $entity->getEntityTypeId();
    $entityStorage = $this->entityTypeManager->getStorage($entityTypeId);

    $transitionStorage = $this->entityTypeManager->getStorage('scheduled_transition');
    $ids = $transitionStorage->getQuery()
      ->condition('entity__target_type', $entityTypeId)
      ->condition('entity__target_id', $entity->id())
      ->tableSort($tableHeadings)
      ->execute();

    $form['table']['#rows'] = array_map(
      function (ScheduledTransitionInterface $scheduledTransition) use ($entityStorage) {
        $row = [];

        $workflowPlugin = $scheduledTransition->getWorkflow()->getTypePlugin();
        $workflowStates = $workflowPlugin ? $workflowPlugin->getStates() : [];

        // From...
        $entityRevisionId = $scheduledTransition->getEntityRevisionId();
        $entityRevision = $entityStorage->loadRevision($entityRevisionId);
        $revisionTArgs = ['@revision_id' => $entityRevisionId];
        if ($entityRevision) {
          $row['from_revision'] = $entityRevision->toLink($this->t('#@revision_id', $revisionTArgs), 'revision');
          $fromState = $workflowStates[$entityRevision->moderation_state->value] ?? NULL;
          $row['from_state'] = $fromState ? $fromState->label() : $this->t('- Missing from workflow/state -');
        }
        else {
          $row['from_revision'] = [
            // Span 'from_revision', 'from_state'.
            'colspan' => 2,
            'data' => $this->t('Deleted revision #@revision_id', $revisionTArgs),
          ];
        }

        // To.
        $toState = $workflowStates[$scheduledTransition->getState()] ?? NULL;
        $row['to_state'] = $toState ? $toState->label() : $this->t('- Missing to workflow/state -');

        // Date.
        $time = $scheduledTransition->getTransitionTime();
        $row['date'] = $this->dateFormatter->format($time);

        // Author.
        $author = $scheduledTransition->getAuthor();
        $row['author'] = $author ? $author->toLink() : $this->t('- Missing user -');

        // Operations.
        $operations = $this->entityTypeManager
          ->getListBuilder('scheduled_transition')
          ->getOperations($scheduledTransition);
        $row['operations']['data'] = [
          '#type' => 'operations',
          '#links' => $operations,
        ];

        return $row;
      },
      $transitionStorage->loadMultiple($ids)
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): void {
    // Not saving.
  }

}
