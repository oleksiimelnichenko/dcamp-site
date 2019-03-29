<?php

namespace Drupal\dckyiv_commerce\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter\EntityReferenceRevisionsEntityFormatter;
use Drupal\field\FieldConfigInterface;

/**
 * Plugin implementation of the 'paragraph_summary' formatter.
 *
 * @FieldFormatter(
 *   id = "paragraph_attendee",
 *   label = @Translation("Attendee rendered entity"),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class AttendeeFormatter extends EntityReferenceRevisionsEntityFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $view_mode = $this->getSetting('view_mode');
    $elements = array();

    $commerce_order_item = $items->getParent()->getValue();
    $quantity = (int) $commerce_order_item->getQuantity();

    $entities = $this->getEntitiesToView($items, $langcode);
    for ($delta = 0; $delta < $quantity; $delta++) {
      if (isset($entities[$delta])) {
        $entity = $entities[$delta];
        // Protect ourselves from recursive rendering.
        static $depth = 0;
        $depth++;
        if ($depth > 20) {
          $this->loggerFactory->get('entity')->error('Recursive rendering detected when rendering entity @entity_type @entity_id. Aborting rendering.', array('@entity_type' => $entity->getEntityTypeId(), '@entity_id' => $entity->id()));
          return $elements;
        }
        $view_builder = \Drupal::entityTypeManager()->getViewBuilder($entity->getEntityTypeId());
        $elements[$delta] = $view_builder->view($entity, $view_mode, $entity->language()->getId());

        // Add a resource attribute to set the mapping property's value to the
        // entity's url. Since we don't know what the markup of the entity will
        // be, we shouldn't rely on it for structured data such as RDFa.
        if (!empty($items[$delta]->_attributes) && !$entity->isNew() && $entity->hasLinkTemplate('canonical')) {
          $items[$delta]->_attributes += array('resource' => $entity->toUrl()->toString());
        }
        $depth = 0;

        // Check if attendee info has been filled.
        foreach ($entity->getFields() as $field) {
          if (!$field->getFieldDefinition() instanceof FieldConfigInterface) {
            continue;
          }
          $empty = $entity->get($field->getFieldDefinition()->getName())->isEmpty();
          if (!$empty) {
            break;
          }
        }

        $elements[$delta]['edit-attendee'] = [
          '#type' => 'link',
          '#weight' => 10,
          '#title' => $empty ? $this->t('Додати інфо') : $this->t('Редагувати'),
          '#url' => Url::fromRoute('dckyiv_commerce.attendee_edit', [
            'user' => $commerce_order_item->getOrder()->getCustomerId(),
            'commerce_order_item' => $commerce_order_item->id(),
            'attendee_paragraph' => $entity->id(),
          ], [
            'query' => [
              'destination' => \Drupal::service('path.current')->getPath(),
            ],
            'attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type'=> 'modal',
            ],
          ]),
        ];
      }
      else {
        $elements[$delta] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['attendee--wrapper']],
          'attendee' => [
            '#type' => 'link',
            '#title' => $this->t('Додати'),
            '#url' => Url::fromRoute('dckyiv_commerce.attendee_add', [
              'user' => $commerce_order_item->getOrder()->getCustomerId(),
              'commerce_order_item' => $commerce_order_item->id(),
            ], [
              'query' => [
                'destination' => \Drupal::service('path.current')->getPath(),
              ],
              'attributes' => [
                'class' => ['use-ajax'],
                'data-dialog-type'=> 'modal',
              ],
            ]),
            '#attributes' => ['class' => ['attendee--add-info']],
          ]
        ];
      }
    }

    return $elements;
  }

}
