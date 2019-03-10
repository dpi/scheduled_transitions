<?php

declare(strict_types = 1);

namespace Drupal\scheduled_transitions_test\Entity;

use Drupal\entity_test_revlog\Entity\EntityTestWithRevisionLog;

/**
 * Defines the test entity class.
 *
 * @ContentEntityType(
 *   id = "st_entity_test",
 *   label = @Translation("ST test entity"),
 *   base_table = "st_entity_test",
 *   revision_table = "st_entity_test_revision",
 *   admin_permission = "administer st_entity_test entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "revision" = "revision_id",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "langcode" = "langcode",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message"
 *   },
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *     },
 *   },
 *   links = {
 *     "canonical" = "/st_entity_test/{st_entity_test}",
 *     "edit-form" = "/st_entity_test/{st_entity_test}/edit",
 *   }
 * )
 */
class ScheduledTransitionsTestEntity extends EntityTestWithRevisionLog {

}
