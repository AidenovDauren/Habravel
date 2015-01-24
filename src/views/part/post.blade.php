<?php /*
  - $classes          - optional; string of space-separated CSS classes
  - $post             - Models\Post instance
  - $parentPost       - Models\Post or null
  - $author           - Models\User
  - $tags             - array of Models\Tag
  - $canEdit          - boolean
  - $readMore         - false or string (link text)
  - $afterVote        - bool
  - $html             - string, actual post body to be output
  - $downshift        - integer, minimum <hN> tag to generate
*/?>

<div class="hvl-post {{{ $classes }}}">
  <header class="hvl-post-header">
    <p class="hvl-post-author">
      @if ($post->sourceURL)
        <a href="{{{ $post->sourceURL }}}" title="{{{ trans('habravel::g.post.source') }}}" target="_blank">
          {{{ $post->sourceName }}}</a>
        <span class="hvl-post-author-separ">&rarr;</span>
      @endif

      <span title="{{{ trans('habravel::g.post.author') }}}">
        @if ($author->avatar)
          <a href="{{{ $author->url() }}}" class="hvl-post-author-avatar">
            <img src="{{{ $author->avatarURL() }}}" alt="{{{ $author->name }}}">
          </a>
        @endif

        {{ $author->nameHTML() }}
      </span>

      @if ($time = ($post->pubTime ?: $post->created_at))
      <time pubdate="pubdate" datetime="{{{ date(DATE_ATOM, $time->timestamp) }}}"
            title="{{{ trans('habravel::g.post.pubTime', array('date' => DateFmt::Format('[d#my]AT h#m', $time->timestamp, Config::get('app.locale')))) }}}">
        {{{ DateFmt::Format('AGO-AT[s-d]IF>7[d# m__ y##]', $time->timestamp, Config::get('app.locale')) }}}
        @if ($post->pubTime) <i class="hvl-i-pencilg"></i> @endif
      </time>
    @endif

      @if (!empty($canEdit))
        <a href="{{{ url(Habravel\url()) }}}/edit/{{{ $post->id }}}" class="hvl-btn">
          {{{ trans('habravel::g.post.edit') }}}</a>
      @endif
    </p>

    @include('habravel::part.tags', compact('tags'), array())
  </header>

  <article class="hvl-markedup hvl-markedup-{{{ $post->markup }}}">
    {{ $html }}
  </article>

  @include('habravel::part.postFooter', compact('post', 'readMore', 'afterVote'))
</div>