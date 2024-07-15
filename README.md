# iq_bef_extensions

Extensions for the BEF (better exposed filters) module.

## Background
Eventhough the BEF module providies significant enhancements for exposed filter, there's still some missing functionalities, such as range sliders etc.

## This module includes
Filter plugins:
- Slider: Rangefilter, based on noUiSlider JS library. Contains a histogram that shows the distribution of the filter results.
- Advanced select: Select dropdown based on chosen JS library
Settings for filter extension can be found in a view display's advanced settings tab, in the section exposed filter settings
- Single: Single on/off widget implementation

A basic filter form layout
To use the layout, change the style of the exposed filter form to "Better Exposed Filters (with layout)" and choose one of the given layouts

Integration with views_ajax_history:
This module bundles drupal/views_ajax_history and appropiate patches to allow GET views with filter values in URL.

## Setup & Installation

### Installation on Drupal 9 and 10:

Install module as usual:

    composer require iqual/iq_bef_extensions
    drush en iq_bef_extensions

## Configuration

### General options
- **Remove filter if not used**: If this option is enabled, the filter will be hidden if it has no effect on the result. This means: none of the resulting objects have values for the corresponding fields.

### Slider options (Only the non-self-explanatory ones)
- **Range minimum**: The minimum of the slider range. Leave empty for automatic calculation.
- **Range maximum**: The maximum of the slider range. Leave empty for automatic calculation.
- **Step**: The minimum sliding distance of the slider. Leave empty for automatic calculation, based on the number of histogram bins.
- **Tooltip Factor**: Factor between the tooltip and the effective filter value. Use this if you want the tooltip to display a different unit than the underlying data model. Example: A numeric field is configured to store values in *mm*. If the slider should display *cm* instead, the tooltip factor can simply be set to 10.
- **Tooltip scale**: Number of decimals.

### Advanced select options
- **Remove unused items**: Hides all options that not return empty filter results.

## Advanced usage / extend behavior

### Interaction with JS

The iq_bef_extensions frontend is built using jQuery and is based on its event system. Three events are triggered that can be used as entry points to change/extend the base functionality:

- iq-bef-extionsions-before-init
- iq-bef-extionsions-init
- iq-bef-extionsions-after-init

Interaction can be done by setting jQuery event listeners:

    $(document).on("iq-bef-extionsions-before-init", function(){
      // Do stuff here...
    });

### Create new filter plugins

iq_bef_extensions filters are implemented as `BetterExposedFiltersFilterWidgets`. Use Symfony's Annotation System to create a new filter plugin with its own functionality. To access the basic functionality of iq_bef_extensions, make sure your custom plugins inherit from the `Drupal\iq_bef_extensions\Plugin\better_exposed_filters\filter\DefaultWidget` class.


Example code:

    <?php

    namespace Drupal\custom_module\Plugin\better_exposed_filters\filter;

    use Drupal\iq_bef_extensions\Plugin\better_exposed_filters\filter\DefaultWidget;

    /**
     * Select implementation using the chosen JS library.
     *
     * @BetterExposedFiltersFilterWidget(
     *   id = "custom_filter_id",
     *   label = @Translation("Custom Filter name"),
     * )
     */
    class CustomFilterPlugin extends DefaultWidget {

      ... Youre custom methods .

    }
