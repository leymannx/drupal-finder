<?php

/**
 * @file
 * Contains \WordpressFinder\WordpressFinder.
 */

namespace WordpressFinder;

class WordpressFinder {

  /**
   * WordPress web public directory.
   *
   * @var string
   */
  private $webRoot;

  /**
   * WordPress package composer directory.
   *
   * @var bool
   */
  private $composerRoot;

  /**
   * Composer vendor directory.
   *
   * @var string
   *
   * @see https://getcomposer.org/doc/06-config.md#vendor-dir
   */
  private $vendorDir;

  /**
   * Vendor plugins directory.
   *
   * @var bool
   */
  private $pluginsDir;

  /**
   * Vendor mu-plugins directory.
   *
   * @var bool
   */
  private $muPluginsDir;

  /**
   * Vendor themes directory.
   *
   * @var bool
   */
  private $themesDir;

  /**
   * Vendor dropins directory.
   *
   * @var bool
   */
  private $dropinsDir;

  /**
   * @param string $start_path
   *
   * @return bool
   */
  public function locateRoot($start_path) {
    $this->webRoot = FALSE;
    $this->composerRoot = FALSE;
    $this->vendorDir = FALSE;
    $this->pluginsDir = FALSE;
    $this->muPluginsDir = FALSE;
    $this->themesDir = FALSE;
    $this->dropinsDir = FALSE;

    foreach ([TRUE, FALSE] as $follow_symlinks) {
      $path = $start_path;
      if ($follow_symlinks && is_link($path)) {
        $path = realpath($path);
      }
      // Check the start path.
      if ($this->isValidRoot($path)) {
        return TRUE;
      }
      else {
        // Move up dir by dir and check each.
        while ($path = $this->shiftPathUp($path)) {
          if ($follow_symlinks && is_link($path)) {
            $path = realpath($path);
          }
          if ($this->isValidRoot($path)) {
            return TRUE;
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Returns parent directory.
   *
   * @param string $path
   *   Path to start from.
   *
   * @return string|false
   *   Parent path of given path or false when $path is filesystem root.
   */
  private function shiftPathUp($path) {
    $parent = dirname($path);

    return in_array($parent, ['.', $path]) ? FALSE : $parent;
  }

  /**
   * @param string $path
   *
   * @return mixed
   */
  protected function isValidRoot($path) {

    if (!empty($path) && is_dir($path)) {

      if (file_exists($path . '/' . $this->getComposerFileName())) {

        // Get composer.json.
        $json = json_decode(
          file_get_contents($path . '/' . $this->getComposerFileName()),
          TRUE
        );

        if (is_array($json)) {

          // Is it a johnpbloch/wordpress based project,
          // for example roots/bedrock?
          // https://composer.rarst.net/recipe/site-stack/
          if (isset($json['extra']['wordpress-install-dir'])) {
            $this->composerRoot = $path;
            $this->webRoot = rtrim($path . '/' . $json['extra']['wordpress-install-dir'], '/');
          }
          // Is it a fancyguy/webroot-installer based project?
          // https://github.com/fancyguy/webroot-installer
          elseif (isset($json['extra']['webroot-dir'])) {
            $this->composerRoot = $path;
            $this->webRoot = rtrim($path . '/' . $json['extra']['webroot-dir'], '/');
          }
          // Is it a leymannx/wordpress-project based project?
          // https://github.com/leymannx/wordpress-project
          elseif (isset($json['extra']['custom-installer']) && is_array($json['extra']['custom-installer'])) {
            foreach ($json['extra']['custom-installer'] as $install_path => $items) {
              if (in_array('type:wordpress-core', $items)) {
                $this->composerRoot = $path;
                $this->webRoot = rtrim($path . '/' . $install_path, '/');
              }
            }
          }

          // Vendor directory configured? If no, take the default.
          if ($this->composerRoot) {
            $this->vendorDir = isset($json['config']['vendor-dir']) ? $this->composerRoot . '/' . rtrim($json['config']['vendor-dir'], '/') : $this->composerRoot . '/vendor';
          }

          // Plugin and theme installer paths configured?
          if ($this->webRoot) {

            $composer_installer = [
              'installer-paths',
              'custom-installer',
            ];

            foreach ($composer_installer as $installer) {
              if (isset($json['extra'][$installer]) && is_array($json['extra'][$installer])) {
                foreach ($json['extra'][$installer] as $install_path => $items) {
                  if (in_array('type:wordpress-plugin', $items)) {
                    $this->pluginsDir = $path . '/' . $this->sanitizeInstallerPath($install_path);
                  }
                  if (in_array('type:wordpress-muplugin', $items)) {
                    $this->muPluginsDir = $path . '/' . $this->sanitizeInstallerPath($install_path);
                  }
                  if (in_array('type:wordpress-theme', $items)) {
                    $this->themesDir = $path . '/' . $this->sanitizeInstallerPath($install_path);
                  }
                  if (in_array('type:wordpress-dropin', $items)) {
                    $this->dropinsDir = $path . '/' . $this->sanitizeInstallerPath($install_path);
                  }
                }
              }
            }

            // If no plugin and theme installer paths configured (above),
            // take the defaults.
            if (!$this->pluginsDir) {
              $this->pluginsDir = $this->webRoot . '/wp-content/plugins';
            }
            if (!$this->muPluginsDir) {
              $this->muPluginsDir = $this->webRoot . '/wp-content/mu-plugins';
            }
            if (!$this->themesDir) {
              $this->pluginsDir = $this->webRoot . '/wp-content/themes';
            }
            if (!$this->dropinsDir) {
              $this->dropinsDir = $this->webRoot . '/wp-content';
            }
          }
        }
      }
    }

    return $this->webRoot && $this->composerRoot && $this->vendorDir && $this->pluginsDir && $this->muPluginsDir && $this->themesDir && $this->dropinsDir;
  }

  /**
   * @return string
   */
  public function getWebRoot() {
    return $this->webRoot;
  }

  /**
   * @return string
   */
  public function getComposerRoot() {
    return $this->composerRoot;
  }

  /**
   * @return string
   */
  protected function getComposerFileName() {
    return trim(getenv('COMPOSER')) ?: 'composer.json';
  }

  /**
   * @return string
   */
  public function getVendorDir() {
    return $this->vendorDir;
  }

  /**
   * @return string
   */
  public function getPluginsDir() {
    return $this->pluginsDir;
  }

  /**
   * @return string
   */
  public function getMuPluginsDir() {
    return $this->muPluginsDir;
  }

  /**
   * @return string
   */
  public function getThemesDir() {
    return $this->themesDir;
  }

  /**
   * @return string
   */
  public function getDropinsDir() {
    return $this->dropinsDir;
  }

  /**
   * Remove possible {token} from an installer path.
   *
   * @param string $install_path
   *
   * @return string
   */
  protected function sanitizeInstallerPath($install_path) {
    $install_path = rtrim($install_path, '/');
    $install_path = trim(preg_replace('/\s*\{[^}]*\}/', '', $install_path));
    $install_path = rtrim($install_path, '/');
    return $install_path;
  }

}
