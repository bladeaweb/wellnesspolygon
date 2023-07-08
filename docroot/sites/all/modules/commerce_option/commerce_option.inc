<?php

/**
 * @file
 * Extends the EntityDefaultUIController so we have a better admnin UI.
 */

class CommerceOptionUIController extends EntityDefaultUIController {

  /**
   * Generates the table headers for the overview table.
   */
  protected function overviewTableHeaders($conditions, $rows, $additional_header = array()) {
    $header = array(
      t('Option id'),
      t('Option set id'),
      t('Order id'),
      t('Commerce product'),
      t('Commerce product id'),
    );

    // Add operations with the right colspan.
    $header[] = array('data' => t('Operations'), 'colspan' => $this->operationCount());
    return $header;
  }

  /**
   * Generates the row for the passed entity.
   *
   * @param $additional_cols
   *   Additional columns to be added after the entity label column.
   */

  protected function overviewTableRow($conditions, $id, $entity, $additional_cols = array()) {
    // Option ID.
    $row[] = $entity->option_id;

    // Type
    $row[] = entity_label($this->entityType, $entity);

    // Order info.
    $line_item = commerce_line_item_load($entity->line_item_id);
    $liw = entity_metadata_wrapper('commerce_line_item', $line_item);
    $row[] = l($liw->order_id->value(), 'admin/commerce/orders/' . $liw->order_id->value() . '/view');
    $row[] = $liw->commerce_product->title->value();
    $row[] = $liw->commerce_product->product_id->value();

    // Operations.
    $row[] = l(t('edit'), $this->path . '/manage/' . $id);
    $row[] = l(t('delete'), $this->path . '/manage/' . $id . '/delete', array('query' => drupal_get_destination()));

    return $row;
  }
}
