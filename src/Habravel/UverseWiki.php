<?php namespace Habravel;

use UWikiDocument;

class UverseWiki extends BaseMarkup {
  static $extension = 'wiki';

  static function loadUWiki() {
    $file = rtrim(\Config::get('habravel::uversewiki.path'), '\\/').'/uversewiki.php';

    if (!is_file($file)) {
      throw new \Exception('Habravel cannot load UverseWiki on the configured path.');
    }

    require_once $file;
    \UWikiDocument::$loadedHandlers['wacko']['link'][] = array(__CLASS__, 'correctLink');
  }

  static function correctLink(&$caption, $linkObj) {
    $path = ltrim($linkObj->LocalPath(), '\\/');

    // $path can be empty for self-anchor links: ((#anchor)).
    if ($path !== '') {
      if ($path[0] === '=') {
        // ((=tag1/tags)) filters by both tags.
        $linkObj->LocalPath('/tags/'.substr($path, 1));
        // ((=tag+))s would be ((=tag =tags)), not ((=tag tags)).
        $linkObj->usedEndingSubst and $caption = substr($caption, 1);
        return true;
      } elseif ($path[0] === '~') {
        if ($name = $linkObj->pager->pageTitleBy($path)) {
          "$caption" === '' and $caption = $name;
          $linkObj->LocalPath('/~'.urlencode($name));
          return true;
        }
      } elseif ($model = $linkObj->pager->postModelBy($path)) {
        // User by ((~name)) and ID: ((~123)).
        // Post by ID: ((123)) or URL: ((docs/security)).
        "$caption" === '' and $caption = $model->caption;
        $linkObj->LocalPath('/'.$model->url(false));
        return true;
      }
    }
  }

  function makeUWiki($source) {
    static::loadUWiki();
    $doc = new UWikiDocument($source);

    $doc->LoadMarkup('wacko');
    $doc->settings->pager = new UverseWikiPager($this->target);
    $doc->MergeAttachmentsOfNestedDocs();
    $this->configure($doc, \Config::get('habravel::uversewiki'));

    return $doc;
  }

  protected function configure($doc, array $config) {
    foreach ($config as $key => $value) {
      switch ($key) {
      case 'setup':
        call_user_func($value, $doc);
        break;
      case 'settingsPath':
      case 'settingsPaths':
        if (!$this->loadSettingsFrom((array) $value, $doc->settings)) {
          throw new \Exception('Habravel cannot load UverseWiki settings -'.
                               ' configured paths not found.');
        }
        break;
      case 'anchorPrefix':
        if ($value !== false and $this->target) {
          $doc->settings->$key = $value.$this->target->id;
        }
        break;
      case 'baseURL':
        $doc->settings->BaseURL($value);
        break;
      default:
        $doc->settings->$key = $value;
      }
    }
  }

  protected function loadSettingsFrom(array $paths, $settings) {
    $anyLoaded = false;

    foreach ($paths as $path) {
      $path[0] === '$' and $path = UWikiRootPath.'/'.ltrim(substr($path, 1), '\\/');

      if (is_dir($path)) {
        $anyLoaded |= true;
        $settings->LoadFrom($path);
      }
    }

    return $anyLoaded;
  }

  protected function doToHTML() {
    $doc = $this->makeUWiki($this->text);
    $doc->Parse();
    $this->html = $doc->ToHTML5();
    $this->meta = &$doc->meta;
  }
}