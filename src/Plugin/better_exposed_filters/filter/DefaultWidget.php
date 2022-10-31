<?php

namespace Drupal\iq_bef_extensions\Plugin\better_exposed_filters\filter;

use Drupal\views\Views;
use Drupal\better_exposed_filters\Plugin\better_exposed_filters\filter\FilterWidgetBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\search_api\Item\Item;
use UnexpectedValueException;

/**
 *
 */
class DefaultWidget extends FilterWidgetBase {

  /**
   * Contains the entity ids per view.
   *
   * @var array[]
   */
  protected static $entityIds = [];

  /**
   * Loads the entity ids present in the current view.
   *
   * The entity ids are saved statically, keyed by view id.
   *
   * @return array
   *   The entity ids present in the view.
   */
  protected function getEntityIds($relationship = 'none'): array {

    $viewKey = $this->view->id() . '_' . $this->view->current_display;

    // Only execute view once per request.
    if (!isset(self::$entityIds[$viewKey]['none'])) {

      // Execute the view using the total row query.
      $view = Views::getView($this->view->id());
      $view->setDisplay($this->view->current_display);

      $view->setArguments($this->view->args);
      $view->setExposedInput([]);
      $view->setItemsPerPage(0);
      $view->selective_filter = TRUE;
      $view->get_total_rows = TRUE;
      $view->preExecute();
      $view->execute();

      if (!$this->view->getQuery() instanceof SearchApiQuery) {
        $entityIdKey = $view->getBaseEntityType()->getKey('id');
      }

      // Create arrays for entity ids.
      self::$entityIds[$viewKey] = [];
      // Index none contains the base entity type ids.
      self::$entityIds[$viewKey]['none'] = [];

      // Retrieve the result from the view query.
      /** @var \Drupal\Core\Database\Query\Select $query */
      $query = $view->query->query();
      $result = $query->execute();
      foreach ($result as $record) {

        if ($record instanceof Item) {
          // Handling search api.
          $match = [];
          preg_match('/([\d]+)/', $record->getId(), $match);
          self::$entityIds[$viewKey]['none'][] = $match[0];
        }
        else {
          // Handling database query.
          self::$entityIds[$viewKey]['none'][] = $record->{$entityIdKey};
        }
      }
    }
    if ($relationship != 'none' && !isset(self::$entityIds[$viewKey][$relationship])) {
      if (!empty($this->view->relationship[$relationship])) {
        $relHandler = $this->view->relationship[$relationship];
        self::$entityIds[$viewKey][$relationship] = $this->getReferencedValues(self::$entityIds[$viewKey]['none'], $relHandler->table, $relHandler->realField);
      }
      else {
        throw new \UnexpectedValueException('The given relationship cannot be found in the view.');
      }
    }
    return self::$entityIds[$viewKey][$relationship];
  }

  /**
   * Retrieves the table and column name of the field.
   *
   * @return array
   *   The table and column.
   * 
   * @throws UnexpectedValueException
   */
  protected function getTableAndColumn(): array {
    $table = $column = '';
    $referenceColumn = 'entity_id';
    if (empty($this->handler->definition['table']) || empty($this->handler->definition['field'])) {
      if ($this->view->getBaseEntityType()) {
        $entityType = $this->view->getBaseEntityType()->id();
      } elseif ($this->view->getQuery() instanceof SearchApiQuery) {
        $dataSources = array_keys($this->view->query->getIndex()->getDatasources());
        $entityType = array_map(function ($key) {
          return explode(':', $key)[1];
        }, $dataSources)[0];
      } else {
        throw new UnexpectedValueException(sprintf('Could not determine base type of view %s.', $this->view->id()));
      }
      $storage = \Drupal::entityTypeManager()->getStorage('field_storage_config')->load($entityType . '.' . $this->getExposedFilterFieldId());
      if (empty($storage)) {
        $typeDefinition = \Drupal::entityTypeManager()->getDefinition($entityType, FALSE);
        if (!empty($typeDefinition)) {
          $table = $typeDefinition->getDataTable();
          $column = $this->getExposedFilterFieldId();
          $referenceColumn = $typeDefinition->getKey('id');
        }
      } else {
        $table = $entityType . '__' . $storage->getName();
        $column = $storage->getName() . '_' . $storage->getMainPropertyName();
      }
    } else {
      $column = $this->handler->definition['field'];
      $table = $this->handler->definition['table'];
    }
    if (empty($table) || empty($column)) {
      throw new UnexpectedValueException(sprintf('Could not determine table or column for %s', $this->view->id()));
    }
    return [$table, $column, $referenceColumn];
  }

  /**
   * Load referenced values from the database.
   *
   * @param array $entityIds
   *   The entityIds to search for.
   * @param string $table
   *   The field table.
   * @param string $column
   *   The value column.
   *
   * @return array|null
   *   The referenced values or null on error.
   */
  protected function getReferencedValues(array $entityIds, string $table, string $column, string $referenceColumn = 'entity_id'): array {
    $ids = [];
    if (!empty($entityIds)) {
      try {
        $result = \Drupal::database()->select($table, 't')->condition('t.' . $referenceColumn, $entityIds, 'IN')->fields('t', [$column])->execute();
        foreach ($result as $record) {
          if ($record->{$column}) {
            $ids[] = $record->{$column};
          }
        }
      }
      catch (\Exception $e) {
        $ids = NULL;
      }
    }
    return $ids;
  }

  /**
   * Filters #options in a form element to only contain values in $keys.
   *
   * @param array $element
   *   The form element.
   * @param array $keys
   *   The allowed keys.
   */
  protected function filterElementWithOptions(array &$element, array $keys) {

    // Append selected options to allowed keys.
    $exposedFilters = $this->view->getExposedInput();
    if (array_key_exists($this->handler->field, $exposedFilters)) {
      $keys = array_unique(array_merge($keys, $exposedFilters[$this->handler->field]));
    }

    if ($keys !== NULL && !empty($element['#options'])) {
      foreach ($element['#options'] as $key => $option) {
        if ($key === 'All') {
          continue;
        }
        $target_id = $key;
        if (is_object($option) && !empty($option->option)) {
          $target_id = array_keys($option->option);
          $target_id = reset($target_id);
        }
        if (!in_array($target_id, $keys)) {
          unset($element['#options'][$key]);
        }
      }
    }
  }

}