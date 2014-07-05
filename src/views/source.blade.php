<?php /*
  - $post             - Post instance with loaded author, tags
*/?>

@extends('habravel::page')

@section('content')
  @include('habravel::part.postTitle', compact('post'), array())

  <header class="hvl-psource-header">
    <p>
      <b>{{{ trans('habravel::g.source.size') }}}</b>
      {{ trans('habravel::g.post.size', array('chars' => $post->size(), 'words' => $post->wordCount())) }}
    </p>

    <p>
      <b>{{{ trans('habravel::g.source.markup') }}}</b>
      <a class="hvl-markup-help" href="{{{ Habravel\Core::url()."/markup/$post->markup" }}}">
        {{{ trans('habravel::g.markups.'.$post->markup) }}}
      </a>
    </p>

    <p>
      <a href="{{{ Habravel\Core::url() }}}/source/{{{ $post->id }}}?dl=1">
        {{{ trans('habravel::g.source.dl', array('size' => round(strlen($post->text) / 1024))) }}}
      </a>
      &darr;
      &nbsp;
      &nbsp;
      <a href="{{{ $post->url() }}}">
        {{{ trans('habravel::g.source.see') }}}
      </a>
      &rarr;
    </p>
  </header>

  <textarea cols="100" rows="30" class="hvl-psource-source" data-sqa="wr - w$body{pb} -"
            onfocus="this.select(); this.onfocus = null" autofocus="autofocus">{{{ $post->text }}}</textarea>
@stop