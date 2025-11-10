<?php

if ( ! defined('ABSPATH') ) { exit; }

/**
 * Loader class for SentinelPro Analytics plugin.
 *
 * Collects and registers all WordPress actions and filters.
 *
 * @package    SentinelPro_Analytics
 * @subpackage SentinelPro_Analytics/includes
 * @since      1.0.0
 */

class SentinelPro_Analytics_Loader {

    /**
     * The array of actions registered with WordPress.
     *
     * @since 1.0.0
     * @access private
     * @var array
     */
    private array $actions = [];

    /**
     * The array of filters registered with WordPress.
     *
     * @since 1.0.0
     * @access private
     * @var array
     */
    private array $filters = [];

    /**
     * Add a new action to the collection to be registered with WordPress.
     *
     * @since 1.0.0
     * @param string   $hook      The name of the WordPress action.
     * @param object   $component The instance of the object on which the action is defined.
     * @param string   $callback  The name of the method to be called on the component.
     */
    public function add_action(string $hook, $component, string $callback): void {
        $this->actions[] = compact('hook', 'component', 'callback');
    }

    /**
     * Add a new filter to the collection to be registered with WordPress.
     *
     * @since 1.0.0
     * @param string   $hook      The name of the WordPress filter.
     * @param object   $component The instance of the object on which the filter is defined.
     * @param string   $callback  The name of the method to be called on the component.
     */
    public function add_filter(string $hook, $component, string $callback): void {
        $this->filters[] = compact('hook', 'component', 'callback');
    }

    /**
     * Register all actions and filters with WordPress.
     *
     * @since 1.0.0
     */
    public function run(): void {
        foreach ($this->actions as $hook) {
            add_action($hook['hook'], [$hook['component'], $hook['callback']]);
        }

        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], [$hook['component'], $hook['callback']]);
        }
    }
}
