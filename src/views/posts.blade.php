<?php /*
  - $title            - string
  - $posts            - array of Post instances
  - $comments         - array of Post, replies to given $posts[index]
*/?>

@extends('habravel::page')

@section('content')
  <h1 class="hvl-h1">{{{ $title }}}</h1>

  @foreach ($posts as $index => $post)
    @include('habravel::part.postTitle', compact('post'), array('level' => 2, 'link' => true))
    @include('habravel::part.post', compact('post'), array('readMore' => true))

    @if (!empty($comments[$index]))
      <div class="hvl-comments">
        @foreach ($comments[$index] as $comment)
          @include('habravel::part.comment', array('post' => $comment), array())
        @endforeach
      </div>
    @endif
  @endforeach

  @if (!count($posts))
    <p class="hvl-none">{{{ trans('habravel::g.posts.none') }}}</p>
  @endif

  @include('habravel::part.pages', compact('perPage', 'page', 'morePages', 'pageURL'), array())
@stop