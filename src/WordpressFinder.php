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
   * @param $start_path
   *
   * @return bool
   */
  public function locateRoot($start_path) {
    $this->webRoot = FALSE;
    $this->composerRoot = FALSE;
    $this->vendorDir = FALSE;

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
   * @param string
   *   Path to start from
   *
   * @return string|false
   *   Parent path of given path or false when $path is filesystem root
   */
  private function shiftPathUp($path) {
    $parent = dirname($path);

    return in_array($parent, ['.', $path]) ? FALSE : $parent;
  }

  /**
   * @param $path
   *
   * @return bool
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

          // Is it a johnpbloch/wordpress based project?
          // https://composer.rarst.net/recipe/site-stack/
          if (isset($json['extra']['wordpress-install-dir'])) {
            $this->webRoot = $path . '/' . $json['extra']['wordpress-install-dir'];
            $this->composerRoot = $path;
          }
          // Is it a fancyguy/webroot-installer based project?
          // https://github.com/fancyguy/webroot-installer
          elseif (isset($json['extra']['webroot-dir'])) {
            $this->webRoot = $path . '/' . $json['extra']['webroot-dir'];
            $this->composerRoot = $path;
          }

          if ($this->composerRoot) {
            $this->vendorDir = isset($json['config']['vendor-dir']) ? $this->composerRoot . '/' . $json['config']['vendor-dir'] : $this->composerRoot . '/vendor';
          }
        }
      }
    }

    return $this->webRoot && $this->composerRoot && $this->vendorDir;
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
}
