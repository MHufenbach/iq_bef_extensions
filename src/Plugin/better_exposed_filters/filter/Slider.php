<?php

namespace Drupal\iq_bef_extensions\Plugin\better_exposed_filters\filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;

/**
 * Slider implementation using the noUiSlider JS library.
 *
 * @BetterExposedFiltersFilterWidget(
 *   id = "iq_slide",
 *   label = @Translation("Slider"),
 * )
 */
class Slider extends DefaultWidget {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'min' => NULL,
      'max' => NULL,
      'step' => NULL,
      'histogram_num_of_bins' => 20,
      'auto_submit' => FALSE,
      'tooltip_factor' => 1,
      'tooltip_thousand_separator' => '',
      'tooltip_decimal_separator' => '.',
      'tooltip_scale' => 2,
      'tooltip_prefix' => '',
      'tooltip_suffix' => '',
      'apply_filter_text' => $this->t('OK'),
      'reset_filter_text' => $this->t('Reset'),
      'remove_unused_filter' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable($filter = NULL, array $filter_options = []) {
    /** @var \Drupal\views\Plugin\views\filter\FilterPluginBase $filter */
    $is_applicable = FALSE;

    // The date filter handler extends the numeric filter handler so we have
    // to exclude it specifically.
    $is_numeric_filter = is_a($filter, 'Drupal\views\Plugin\views\filter\NumericFilter');
    $is_range_filter = is_a($filter, 'Drupal\range\Plugin\views\filter\Range');
    $is_date_filter = is_a($filter, 'Drupal\views\Plugin\views\filter\Date');
    if (($is_numeric_filter || $is_range_filter) && !$is_date_filter && !$filter->isAGroup()) {
      $is_applicable = TRUE;
    }

    return $is_applicable;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['auto_submit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto submit after change'),
      '#default_value' => $this->configuration['auto_submit'],
    ];

    $form['remove_unused_filter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Remove filter if not used"),
      '#description' => $this->t("Remove the filter if it doesn't affect the results."),
      '#default_value' => $this->configuration['remove_unused_filter'],
    ];

    $form['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Range minimum'),
      '#default_value' => $this->configuration['min'],
    ];

    $form['max'] = [
      '#type' => 'number',
      '#title' => $this->t('Range maximum'),
      '#default_value' => $this->configuration['max'],
    ];

    $form['step'] = [
      '#type' => 'number',
      '#title' => $this->t('Step'),
      '#default_value' => $this->configuration['step'],
      '#description' => $this->t('Determines the size or amount of each interval or step the slider takes between the min and max.'),
      '#min' => 0,
    ];

    $form['histogram_num_of_bins'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of bins in the histogram'),
      '#default_value' => $this->configuration['histogram_num_of_bins'],
    ];

    $form['apply_filter_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button text for apply filter'),
      '#default_value' => $this->configuration['apply_filter_text'],
      '#min' => 0,
    ];

    $form['reset_filter_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button text for reset filter'),
      '#default_value' => $this->configuration['reset_filter_text'],
      '#min' => 0,
    ];

    $form['tooltip_factor'] = [
      '#type' => 'number',
      '#title' => $this->t('Tooltip Factor'),
      '#default_value' => $this->configuration['tooltip_factor'],
      '#min' => 0,
    ];

    $form['tooltip_thousand_separator'] = [
      '#type' => 'select',
      '#title' => $this->t('Tooltip Thousand marker'),
      '#default_value' => $this->configuration['tooltip_thousand_separator'],
      '#options' => [
        '' => $this->t('- None -'),
        '.' => $this->t('Decimal point'),
        ',' => $this->t('Comma'),
        ' ' => $this->t('Space'),
        '&thinsp;' => $this->t('Thin space'),
        '\'' => $this->t('Apostrophe'),
      ],
    ];

    $form['tooltip_decimal_separator'] = [
      '#type' => 'select',
      '#title' => $this->t('Tooltip Decimal marker'),
      '#default_value' => $this->configuration['tooltip_decimal_separator'],
      '#options' => [
        '.' => $this->t('Decimal point'),
        ',' => $this->t('Comma'),
      ],
    ];

    $form['tooltip_scale'] = [
      '#type' => 'number',
      '#title' => $this->t('Tooltip Scale'),
      '#default_value' => $this->configuration['tooltip_scale'],
      '#min' => 0,
    ];

    $form['tooltip_prefix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tooltip prefix'),
      '#default_value' => $this->configuration['tooltip_prefix'],
      '#min' => 0,
    ];

    $form['tooltip_suffix'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tooltip suffix'),
      '#default_value' => $this->configuration['tooltip_suffix'],
      '#min' => 0,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    // Max must be > min.
    $min = $form_state->getValue('min');
    $max = $form_state->getValue('max');
    if ($min && $max && $max <= $min) {
      $form_state->setError($form['max'], $this->t('The slider max value must be greater than the slider min value.'));
    }

    // Step must have:
    // - No more than 5 decimal places.
    // - Slider range must be evenly divisible by step.
    $step = $form_state->getValue('step');
    if (strlen(substr(strrchr((string) $step, '.'), 1)) > 5) {
      $form_state->setError($form['step'], $this->t('The slider step option for %name cannot have more than 5 decimal places.'));
    }

    // Very small step and a vary large range can go beyond the max value of
    // an int in PHP. Thus we look for a decimal point when casting the result
    // to a string.
    if (strpos((string) ($max - $min) / $step, '.')) {
      $form_state->setError($form['step'], $this->t('The slider range must be evenly divisible by the step option.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function exposedFormAlter(array &$form, FormStateInterface $form_state) {
    parent::exposedFormAlter($form, $form_state);
    $exposedFilterId = $this->getExposedFilterFieldId();
    $fieldId = $exposedFilterId . '_wrapper';
    $filter = $this->handler;
    $element = &$form[$fieldId];
    $element['#attached']['library'][] = 'iq_bef_extensions/sliders';

    $hasSettings = !empty($form_state->getUserInput()[$exposedFilterId]['min']) ||
      !empty($form_state->getUserInput()[$exposedFilterId]['max']);

    if (
      $filter->isExposed() && empty($this->view->selective_filter)
      && (!$hasSettings || !\Drupal::request()->isXmlHttpRequest())
    ) {

      $relationship = ($filter->options['relationship']) ? $filter->options['relationship'] : 'none';
      $values = $this->getFilterIds($relationship);

      // Set a default value.
      $min = 0;
      $max = 1000;
      $step = 1;
      $valueHistogram = range($min, $max - $step, $step);

      // If there's values, set min/max.
      if (!empty($values)) {
        $min = min($values);
        $max = max($values);
      }

      // If make sure to have different values for min & max.
      if ($min == $max) {
        $min = min($min, 0);
        $max = max($max, 0);
      }

      // If the min/max ar specified, set them according to the configuration.
      if (strlen($this->configuration['min'])) {
        $min = intval($this->configuration['min']);
      }

      if (strlen($this->configuration['max'])) {
        $max = intval($this->configuration['max']);
      }

      // Hide element if specified and filter not set.
      if ((empty($values) || count($values) < 2) && !empty($this->configuration['remove_unused_filter'])) {
        $element['#access'] = FALSE;
      }
      else {
        $histogramNumOfBins = (intval($this->configuration['histogram_num_of_bins'])) ?: count($values);
        if ($histogramNumOfBins > count($values)) {
          $histogramNumOfBins = count($values);
        }
        if ($histogramNumOfBins > 1) {
          $step = ($max - $min) / max(($histogramNumOfBins - 1), 1);

          $valueHistogram = range($min, $max, $step);

          // For some reason, range() sometimes creates an array with one
          // missing element.
          if (count($valueHistogram) < $histogramNumOfBins) {
            array_push($valueHistogram, max($valueHistogram) + $step);
          }

          $numOfBins = max(array_keys($valueHistogram)) + 1;
          $numOfValues = count($values);

          $dist = array_count_values(array_map(function ($value) use ($min, $max, $numOfBins) {
            return intval(floor(($value - $min) / $max * $numOfBins));
          }, $values));

          array_walk($valueHistogram, function (&$value, $num) use ($dist, $numOfValues) {
            $value = array_key_exists($num, $dist) ? $dist[$num] / $numOfValues * 100 : 0;
          });

          $maxValue = max($valueHistogram);
          if ($maxValue) {
            array_walk($valueHistogram, function (&$value, $num) use ($maxValue) {
              $value = $value / $maxValue * 100;
            });
          }

          $step = ($max - $min) / ($histogramNumOfBins);
          if (strlen($this->configuration['step'])) {
            $step = floatval($this->configuration['step']);
          }

        }
        else {
          $max = $min = $step = 0;
          $valueHistogram = [];
        }

        // Set the slider settings.
        $element['#attached']['drupalSettings']['iq_bef_extensions'][$this->view->id() . '__' . $this->view->current_display]['filters'][$fieldId] = [
          'type' => 'slider',
          'min' => $min,
          'max' => $max,
          'step' => $step,
          'histogram_num_of_bins' => $histogramNumOfBins,
          'auto_submit' => $this->configuration['auto_submit'],
          'id' => Html::getUniqueId($fieldId),
          'dataSelector' => Html::getId($fieldId),
          'viewId' => $form['#id'],
          'value_histogram' => json_encode($valueHistogram),
          'apply_filter_text' => $this->configuration['apply_filter_text'],
          'reset_filter_text' => $this->configuration['reset_filter_text'],
          'tooltip_settings' => [
            'factor' => $this->configuration['tooltip_factor'],
            'thousand_separator' => $this->configuration['tooltip_thousand_separator'],
            'decimal_separator' => $this->configuration['tooltip_decimal_separator'],
            'scale' => $this->configuration['tooltip_scale'],
            'prefix' => $this->configuration['tooltip_prefix'],
            'suffix' => $this->configuration['tooltip_suffix'],
          ],
          'remove_unused_filter' => !empty($this->configuration['remove_unused_filter']),
        ];
      }
    }
  }

}
