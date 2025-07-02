<?php
/**
 * Extensions class for managing modules, addons, and plugins.
 *
 * @package Arraytics\ToolsSdk
 */
namespace Arraytics\ToolsSdk;

/**
 * Class Extension
 *
 * Handles registration and retrieval of plugin-related extensions such as modules, plugins, and addons.
 */
class Extension {
    /**
     * The option name used to store extension statuses in WordPress options table.
     *
     * @var string
     */
    protected string $option_name = 'extensions';

    /**
     * List of registered extensions.
     *
     * @var array
     */
    protected array $extensions = [];

    /**
     * Boot the extension system with provided option name and extensions list.
     *
     * @param string $option_name The option key used to save extension settings.
     * @param array  $extensions  List of available extensions.
     *
     * @return void
     */
    public function __construct(string $option_name, array $extensions) {
        $this->option_name = $option_name;
        $this->extensions = apply_filters('plugin_utility_manager/extensions', $extensions);
    }

    /**
     * Get all registered extensions with resolved status (on, off, upgrade, install, activate).
     *
     * @return array
     */
    public function get(): array {
        $settings = get_option($this->option_name, []);
        $resolved = [];

        foreach ($this->extensions as $key => $extension) {
            $status = $settings[$key] ?? $extension['status'];
            $extension['status'] = $status;

            // Resolve plugin state
            $slug = $extension['slug'] ?? '';
            $deps = $extension['deps'] ?? [];

            if ($extension['type'] === 'module' && !empty($deps)) {
                $dep = $deps[0];

                if (!PluginManager::is_installed($dep)) {
                    $extension['status'] = 'upgrade';
                } elseif (!PluginManager::is_activated($dep)) {
                    $extension['status'] = 'install';
                } else {
                    $extension['status'] = 'activate';
                }
            } elseif (in_array($extension['type'], ['plugin', 'addon'])) {
                if (!PluginManager::is_installed($slug)) {
                    $extension['status'] = 'upgrade';
                } elseif (!PluginManager::is_activated($slug)) {
                    $extension['status'] = 'install';
                } else {
                    $extension['status'] = 'activate';
                }
            } elseif ( $extension['type'] === 'arraytics-plugin' ) {
                if (!PluginManager::is_activated($slug)) {
                    $extension['status'] = 'install';
                } else {
                    $extension['status'] = 'activate';
                }
            }

            $resolved[$key] = $extension;
        }

        return $resolved;
    }

    /**
     * Update the status of a given extension.
     *
     * @param string $key    The extension key.
     * @param string $status The status to set ('on' or 'off').
     *
     * @return bool True if update successful, false otherwise.
     */
    public function update(string $key, string $status): bool {
        if (!isset($this->extensions[$key])) {
            return false;
        }

        $settings = get_option($this->option_name, []);
        $settings[$key] = $status === 'on' ? 'on' : 'off';

        return update_option($this->option_name, $settings);
    }

    /**
     * Get all extensions that are currently enabled (status = 'on').
     *
     * @return array
     */
    public function enabled(): array {
        return array_filter(self::get(), function($ext) {
            return $ext['status'] === 'on';
        });
    }

    /**
     * Find an extension by its key.
     *
     * @param string $key The extension key.
     *
     * @return array|null The extension data or null if not found.
     */
    public function find(string $key): ?array {
        return $this->extensions[$key] ?? null;
    }

    /**
     * Get the current option name used for storing extension settings.
     *
     * @return string
     */
    public function get_option_name(): string {
        return $this->option_name;
    }

    /**
     * Get all registered extensions as initially provided.
     *
     * @return array
     */
    public function get_extensions(): array {
        return $this->extensions;
    }

    /**
     * Get all extensions of type "plugin".
     *
     * @return array
     */
    public function get_plugins(): array {
        return array_filter(self::get(), function($e) {
            return $e['type'] === 'plugin';
        });
    }

    /**
     * Get all extensions of type "addon".
     *
     * @return array
     */
    public function get_addons(): array {
        return array_filter(self::get(), function($e) {
            return $e['type'] === 'addon';
        });
    }

    /**
     * Get all extensions of type "module".
     *
     * @return array
     */
    public function get_modules(): array {
        return array_filter(self::get(), function($e) {
            return $e['type'] === 'module';
        });
    }

    /**
     * Get all extensions of type "module".
     *
     * @return array
     */
    public function get_our_plugins(): array {
        return array_filter(self::get(), function($e) {
            return $e['type'] === 'arraytics-plugin';
        });
    }
}
