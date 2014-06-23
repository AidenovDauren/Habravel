<?php namespace Habravel;

abstract class BaseMarkup {
  public $text;

  // If set is an object for which the text is being formatted - like Post.
  public $target;

  // doToHTML() must populate these fields.
  public $html;
  public $introHTML;

  static function format($text, $target = null) {
    $self = new static;
    $self->text = $text;
    $self->target = $target;
    return $self->toHTML();
  }

  function toHTML() {
    $this->doToHTML();
    $this->html = trim($this->html);

    if (!$this->introHTML) {
      $this->introHTML = trim(strtok($this->html, "\r\n"));
    }

    return $this;
  }

  protected abstract function doToHTML();
}