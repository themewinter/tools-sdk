<?php
/**
 * Extentions class
 * 
 * @package Arraytics\Tools
 */
namespace Arraytics\Tools;

/**
 * Class extension
 */
class Extention {
    /**
     * Option name to store extensions
     *
     * @var string
     */
    private $option_name;

    /**
     * List of extensions
     *
     * @var array
     */
    private $extensions = [];

    /**
     * Constructor
     *
     * @param string $name The name of the option to store extensions.
     */

    public function __construct( $option_name = 'arraytics_extensions',  $extensions = [] ) {
        $this->option_name = $option_name;
        $this->extensions = $extensions;
    }

    /**
     * Get all modules
     *
     * @return  array
     */
    public function modules() {
        $extentions = $this->get();

        return array_filter( $extentions, function( $extension ) {
            return $extension['type'] === 'module';
        } );
    }

    /**
     * Get all addons
     *
     * @return  array
     */
    public function addons() {
        $extensions = $this->get(); // Fixed the typo

        return array_values( array_filter($extensions, function($extension) {
            return $extension['type'] === 'addon';
        } ) );
    }

    /**
     * Get all addons
     *
     * @return  array
     */
    public function plugins() {
        $extensions = $this->get(); // Fixed the typo

        return array_values( array_filter($extensions, function($extension) {
            return $extension['type'] === 'plugin';
        } ) );
    }

    /**
     * Get all extensions
     *
     * @return  array
     */
    public function get() {
        $extensions = $this->extensions();
        
        return array_map( function( $extension ) {
            $settings = get_option( $this->option_name, [] );

            if ( isset( $settings[ $extension['name'] ] ) && $settings[ $extension['name']] === 'on' ) {
                $extension['status'] = 'on';

                if ( 'addon' === $extension['type'] ) {
                    if ( 
                            $this->is_need_upgrade( $extension['name'] )
                            && ! PluginManager::is_installed( $extension['slug'] )
                        ) {
                        $extension['status'] = 'upgrade';
                    }

                    if ( PluginManager::is_installed( $extension['slug'] ) ) {
                        $extension['status'] = 'install';
                    }

                    if ( PluginManager::is_activated( $extension['slug'] ) ) {
                        $extension['status'] = 'activate';
                    }
                }

                if ( 'module' === $extension['type'] ) {
                    $dependencies = $this->get_depencies( $extension['slug'] );
                    $dependency   = is_array( $dependencies ) ? $dependencies[0] : '';

                    if ( 
                        $this->is_need_upgrade( $extension['name'] )
                        && ! PluginManager::is_installed( $dependency )
                    ) {
                        $extension['status'] = 'upgrade';
                    }

                    if ( PluginManager::is_installed( $dependency ) ) {
                        $extension['status'] = 'install';
                    }

                    if ( PluginManager::is_activated( $dependency ) ) {
                        $extension['status'] = 'activate';
                    }
                }
    
                if ( $this->dependencies_resolved( $extension['name'] ) ) {
                    $extension['notice'] = false;
                }

            }

            if ( 'plugin' === $extension['type'] ) {
                if ( 
                        $this->is_need_upgrade( $extension['name'] )
                        && ! PluginManager::is_installed( $extension['slug'] )
                    ) {
                    $extension['status'] = 'upgrade';
                }

                if ( PluginManager::is_installed( $extension['slug'] ) ) {
                    $extension['status'] = 'install';
                }

                if ( PluginManager::is_activated( $extension['slug'] ) ) {
                    $extension['status'] = 'activate';
                }
            }

            return $extension;
            
        }, $extensions );
    }

    /**
     * Find extension by name
     *
     * @return  array
     */
    public function find( $name ) {
        $extensions = $this->extensions();
        
        if ( array_key_exists( $name, $extensions ) ) {
            return $extensions[$name];  
        }

        return null;
    }

    /**
     * Update extension status
     *
     * @param   string  $name
     *
     * @return  bool | WP_Error
     */
    public function update( $name, $status ) {
        $extension = $this->find( $name );

        if ( ! $extension ) {
            return false;
        }

        $settings = $this->get_settings();
        $updated_status = 'off' !== $status ? 'on' : 'off';
        $settings[$name] = $updated_status;

        $slug = ! empty( $extension['slug'] ) ? $extension['slug'] : '';

        $result = true;

        if ( 'addon' === $extension['type'] && 'on' === $updated_status ) {
            if ( ! $slug ) {
                return false;
            }

            if ( 'install' === $status && ! PluginManager::is_installed( $slug ) ) {
                $result = PluginManager::install_plugin( $slug );
            }

            if ( 'activate' === $status && ! PluginManager::is_activated( $slug ) ) {
                $result = PluginManager::activate_plugin( $slug );
            }

            if ( 'deactivate' === $status && PluginManager::is_activated( $slug ) ) {
                $result = PluginManager::deactivate_plugin( $slug );
            }
        }

        if ( 'plugin' === $extension['type'] ) {
            if ( ! $slug ) {
                return false;
            }

            if ( 'install' === $status && ! PluginManager::is_installed( $slug ) ) {
                $result = PluginManager::install_plugin( $slug );
            }

            if ( 'activate' === $status && ! PluginManager::is_activated( $slug ) ) {
                $result = PluginManager::activate_plugin( $slug );
            }

            if ( 'deactivate' === $status && PluginManager::is_activated( $slug ) ) {
                $result = PluginManager::deactivate_plugin( $slug );
            }
        }

        if ( 'addon' === $extension['type'] && 'off' === $status ) {

            if ( ! $slug ) {
                return false;
            }

            if ( PluginManager::is_activated( $slug ) ) {
                $result = PluginManager::deactivate_plugin( $slug );
            }
        }

        if ( 'module' === $extension['type']  && ! empty( $extension['deps'] ) ) {
            $dependency = $extension['deps'][0];
            if ( 'install' === $status && ! PluginManager::is_installed( $dependency ) ) {
                $result = PluginManager::install_plugin( $dependency );
            }

            if ( 'activate' === $status && ! PluginManager::is_activated( $dependency ) ) {
                $result = PluginManager::activate_plugin( $dependency );
            }

            if ( 'deactivate' === $status && PluginManager::is_activated( $dependency ) ) {
                $result = PluginManager::deactivate_plugin( $dependency );
            }
        }

        update_option( $this->option_name, $settings );

        return $result;
    }

    /**
     * Get settings
     *
     * @return  array
     */
    public function get_settings() {
        $settings = get_option( $this->option_name, [] );

        return $settings;
    }

    /**
     * Check if an extension's dependencies are resolved.
     *
     * @param string $extension_name Name of the extension.
     * @return bool True if all dependencies are resolved, false otherwise.
     */
    public function dependencies_resolved( $extension_name ) {
        $depencies = $this->get_depencies( $extension_name );

        if ( ! $depencies ) {
            return true;
        }

        foreach ( $depencies as $dependency ) {
            if ( ! PluginManager::is_activated( $dependency ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get dependencies
     *
     * @param   string  $extension_name  [$extension_name description]
     *
     * @return array
     */
    public function get_depencies( $extension_name ) {
        $extension = $this->find( $extension_name );

        if ( ! $extension ) {
            return null;
        }

        if ( empty( $extension['deps'] ) ) {
            return null;
        }

        return $extension['deps'];
    }

    /**
     * Get dependencies
     *
     * @param   string  $extension_name  [$extension_name description]
     *
     * @return array
     */
    public function get_depency_names( $extension_name ) {
        $depencies = $this->get_depencies( $extension_name );
        
        $names = [];

        if ( is_array( $depencies ) ) {
            foreach( $depencies as $dependency ) {
                $names[] = PluginManager::get_plugin_name_by_slug( $dependency );
            }
        }

        return $names;
    }

    /**
     * Get dependencies
     *
     * @param   string  $extension_name  [$extension_name description]
     *
     * @return string
     */
    public function get_depency_string( $extension_name ) {
        $depencies = $this->get_depency_names( $extension_name );
        
        return implode( ',', $depencies );
    }

    /**
     * Check a module or addon need to upgrade
     *
     * @param   string  $extension_name  [$extension_name description]
     *
     * @return  bool
     */
    public function is_need_upgrade( $extension_name ) {
        $extension = $this->find( $extension_name );

        if ( ! $extension ) {
            return false;
        }

        if ( isset( $extension['upgrade'] ) && $extension['upgrade'] ) {
            return true;
        }

        return false;
    }
    
    /**
     * List of all extensions
     *
     * @return  array
     */
    private function extensions() {
        if ( ! empty( $this->extensions ) ) {
            return $this->extensions;
        }
    }
}
