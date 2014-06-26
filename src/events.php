<?php namespace Habravel;

/* Included into Habravel\Core, $this = instance. */

use App;
use View;
use Config;
use Request;
use Redirect;
use Carbon\Carbon;
use Illuminate\Support\MessageBag;

App::error(function ($e) {
  if (method_exists($e, 'getStatusCode') and $e->getStatusCode() === 401) {
    $url = Request::fullUrl();
    return Redirect::to(Core::url().'/login?back='.urlencode($url));
  }
});

if (App::isLocal()) {
  Event::listen('illuminate.query', function ($sql, array $bindings) {
    foreach ($bindings as $binding) {
      $sql = preg_replace('/\?/', $binding, $sql, 1);
    }

    file_put_contents('/1.sql', "$sql\n", FILE_APPEND | LOCK_EX);
  });
}

/***
  Article Routes
 ***/

Event::listen('habravel.out.markup', function (BaseMarkup $markup) {
  return \Response::make($markup->help(), 200, array(
    'Expires'             => gmdate('D, d M Y H:i:s', time() + 3600 * 6).' GMT',
  ));
});

Event::listen('habravel.out.source', function (Post $post, $dl) {
  if ($dl) {
    $class = get_class(Core::markup($post->markup));
    $name = preg_replace('~[\0-\x1F"]+~u', '', $post->caption).
            '.'.$class::$extension;

    return \Response::make($post->text, 200, array(
      'Content-Description'       => 'File Transfer',
      'Content-Disposition'       => 'attachment; filename="'.$name.'"',
      'Content-Length'            => strlen($post->text),
      'Content-Transfer-Encoding' => 'binary',
      'Content-Type'              => 'text/plain; charset=utf-8',
    ));
  } else {
    return View::make('habravel::source', compact('post'));
  }
});

Event::listen('habravel.out.post', function (Post $post) {
  if ($post->addSeen(Core::user() ?: Request::getClientIp())) {
    ++$post->views;
    $post->save();
  }
});

Event::listen('habravel.out.post', function (Post $post) {
  return View::make('habravel::post', compact('post'));
});

Event::listen(
  array('habravel.out.edit', 'habravel.save.post'),
  function (Post $post) {
    if (!($user = Core::user())) {
      App::abort(401);
    } elseif ( $post->id ? $post->isEditable($user) : $user->hasFlag('can.post') ) {
      return;   // Okay, permit.
    } else {
      App::abort(403);
    }
  },
  VALIDATE
);

Event::listen('habravel.out.edit', function (Post $post, MessageBag $errors = null) {
  $markups = Core::markups();
  return View::make('habravel::edit', compact('post', 'errors', 'markups'));
});

Event::listen('habravel.out.preview', function (Post $post, MessageBag $errors = null) {
  return View::make('habravel::preview', compact('post', 'errors'));
});

Event::listen('habravel.out.list', function (Query $query, array &$vars) {
  $vars['page'] = Core::input('page') ?: 1;
  $vars['perPage'] = Core::input('limit') ?: 10;
  $url = Request::fullUrl();
  $vars['pageURL'] = $url.(strrchr($url, '?') ? '&' : '?').'page=';

  $query->forPage(Core::input('page'), $vars['perPage']);
}, CUSTOMIZE);

Event::listen('habravel.out.list', function (Query $query, array &$vars) {
  $posts = $query->get();
  $vars['morePages'] = $vars['perPage'] <= count($posts);
  return View::make('habravel::posts', compact('posts') + $vars);
});

Event::listen('habravel.check.post', function (Post $post, array $input, MessageBag $errors) {
  $user = Core::user();

  if ($user and $user->hasFlag('post.setURL') and isset($input['url'])) {
    $post->url = $input['url'];
  }

  if (isset($input['sourceURL'])) {
    $url = $post->sourceURL = (string) $input['sourceURL'];
    if ($url !== '' and !preg_match('~^https?://~', $url)) {
      $post->sourceURL = 'http://'.ltrim($url, '\\/:');
    }
  }

  if (isset($input['tags'])) {
    $post->x_tags = array_map(function ($caption) {
      $tag = new Tag;
      $tag->caption = trim($caption);
      return $tag;
    }, (array) $input['tags']);
  }

  $post->author or $post->author = $user->id;
  isset($input['sourceName']) and $post->sourceName = $input['sourceName'];
  isset($input['caption']) and $post->caption = $input['caption'];
  isset($input['markup']) and $post->markup = $input['markup'];
  isset($input['text']) and $post->text = $input['text'];
  isset($post->listTime) or $post->listTime = new Carbon;
  isset($post->pubTime) or $post->pubTime = new Carbon;
  $post->format();

  if (!$post->id or ($post->caption === '' and $post->getOriginal('caption') !== '')) {
    $validator = \Validator::make($input, array('caption' => 'required'));
    $validator->fails() and $errors->merge($validator->messages());
  }
});

Event::listen(
  array('habravel.check.post', 'habravel.check.reply'),
  function (Post $post, array $input, MessageBag $errors) {
    $validator = \Validator::make($post->getAttributes(), Post::rules($post));
    $validator->fails() and $errors->merge($validator->messages());
  },
  LAST
);

Event::listen('habravel.save.post', function (Post $post) {
  \DB::transaction(function () use ($post) {
    $post->url or $post->url = 'posts/%ID%';
    $post->save();

    if (isset($post->x_tags)) {
      $captions = array();
      foreach ($post->x_tags as $tag) {
        $captions[] = $tag->caption;
        try {
          $tag->save();
        } catch (\Illuminate\Database\QueryException $e) {
          // Ignore duplicate entry error.
        }
      }

      $ids = array();
      foreach (Tag::whereIn('caption', $captions)->lists('id') as $id) {
        $records[] = array('post_id' => $post->id, 'tag_id' => $id);
      }

      \DB::table('post_tag')->where('post_id', '=', $post->id)->delete();
      \DB::table('post_tag')->insert($records);
    }
  });
});

Event::listen('habravel.check.reply', function (Post $post, array $input, MessageBag $errors, Post $parent) {
  $post->author = Core::user()->id;
  $post->top = $parent->top ?: $parent->id;
  $post->parent = $parent->id;
  $post->markup = array_get($input, 'markup');
  $post->text = array_get($input, 'text');
  $post->listTime = new Carbon;
  $post->pubTime = new Carbon;
  $post->format();
});

Event::listen('habravel.save.reply', function (Post $post) {
  if (!($user = Core::user())) {
    App::abort(401);
  } elseif (!$user->hasFlag('can.reply')) {
    App::abort(403);
  }
}, VALIDATE);

Event::listen('habravel.save.reply', function (Post $post, Post $parent) {
  $post->url = strtok($parent->url, '#').'#cmt-%ID%';
  $post->save();
});

Event::listen('habravel.check.vote', function ($up, Post $post) {
  if (!$post->poll) {
    App::aobrt(400, 'This post cannot be voted for.');
  } elseif ($user = Core::user()) {
    if (!$user->hasFlag('can.vote.'.($up ? 'up' : 'down'))) {
      App::abort(403);
    }
  } else {
    App::abort(401);
  }
}, VALIDATE);

Event::listen('habravel.save.vote', function ($up, Post $post) {
  $vote = new PollVote;
  $vote->poll = $post->poll;
  $vote->option = $up + 0;
  $vote->user = Core::user()->id;
  $vote->ip = Request::getClientIp();
  $vote->save();
});

/***
  User Routes
 ***/

Event::listen('habravel.out.user', function (User $user) {
  return View::make('habravel::user', compact('user'));
});

Event::listen('habravel.out.login', function (array $input) {
  $vars = array(
    'backURL'         => array_get($input, 'back'),
    'badLogin'        => !empty($input['bad']),
  );

  return View::make('habravel::login', $vars);
});

Event::listen('habravel.save.login', function (User $user, array $input) {
  $user->loginTime = new Carbon;
  $user->loginIP = Request::getClientIp();
  return Redirect::to( array_get($input, 'back', $user->url()) );
});

Event::listen('habravel.out.register', function (array $input, MessageBag $errors = null) {
  return View::make('habravel::register', compact('input', 'errors'));
});

Event::listen('habravel.check.register', function (User $user, array $input, MessageBag $errors) {
  $user->name = array_get($input, 'name');
  $user->email = array_get($input, 'email');
  $haser = Config::get('habravel::g.password');
  $user->password = call_user_func($haser, array_get($input, 'password'));
  $user->regIP = Request::getClientIp();
});

Event::listen('habravel.check.register', function (User $user, array $input, MessageBag $errors) {
  $attrs = array_only($input, 'password') + $user->getAttributes();
  $validator = \Validator::make($attrs, User::rules($user));
  $validator->fails() and $errors->merge($validator->messages());
}, LAST);

Event::listen('habravel.save.register', function (User $user) {
  $user->save();
});

/***
  Drafts Support
 *** /

$checkDraft = function ($action, Post $post) {
  if ($post->hasFlag('draft')) {
    $user = Core::user();
    if (!$user) {
      App::abort(401);
    } elseif ($user->id !== $post->author and !$user->hasFlag("draft.$action")) {
      App::abort(403, "Cannot $action author's draft.");
    }
  }
};

Event::listen('habravel.out.post', function (Post $post) use ($checkDraft) {
  $checkDraft('read', $post);
}, VALIDATE);

Event::listen('habravel.out.edit', function (Post $post) use ($checkDraft) {
  $checkDraft('edit', $post);
}, VALIDATE);

Event::listen('habravel.out.list', function (Query $query) {
  $user = Core::user();
  if (!$user or !$user->hasFlag('read.draft')) {
    $query->where('posts.flags', 'NOT LIKE', '%[draft]%');
  }
}, CUSTOMIZE);

  + put drafts link to uheader
  + save to drafts button for compose view
+ post links support
+ comments support
+ polls support
*/

/***
  View Composers
 ***/

View::composer('habravel::post', function ($view) {
  $post = $view->post;

  if (!isset($post->x_children)) {
    $all = Post::whereTop($post->id)->orderBy('listTime')->get()->all();
    array_unshift($all, $post);
    $byID = $tree = array();

    foreach ($all as $post) {
      $tree[$post->parent][] = $post;
      $byID[$post->id] = $post;
    }

    foreach ($all as $post) {
      $post->x_children = (array) array_get($tree, $post->id);
    }
  }
});

View::composer('habravel::posts', function ($view) {
  if (!isset($view->comments)) {
    $list = array();

    foreach ($view->posts as $post) {
      $list[] = $post->children()
        ->orderBy('listTime', 'desc')
        ->take(1)
        ->get();
    }

    $view->comments = $list;
  }
});

View::composer('habravel::edit', function ($view) {
  if (!isset($view->textPlaceholder)) {
    $list = trans('habravel::g.edit.placeholders');
    $view->textPlaceholder = $list[array_rand($list)];
  }

  isset($view->tags) or $view->tags = Tag::take(14)->lists('caption');
  isset($view->post->x_tags) or $view->post->x_tags = $view->post->tags()->get();
});

View::composer('habravel::user', function ($view) {
  $user = $view->user;

  if (!isset($view->posts)) {
    $query = $user->posts()->whereTop(null)->orderBy('pubTime', 'desc');
    $view->posts = $query->take(10)->get();
    $view->postCount = $query->count();
  }

  if (!isset($view->comments)) {
    $query = $user->posts()->whereNotNull('top')->orderBy('pubTime', 'desc');
    $view->comments = $query->take(20)->get();
    $view->commentCount = $query->count();
  }
});

View::composer('habravel::part.uheader', function ($view) {
  isset($view->pageUser) or $view->pageUser = Core::user();
});

View::composer('habravel::part.markups', function ($view) {
  isset($view->markups) or $view->markups = Core::markups();

  if (isset($view->current) and $user = Core::user()) {
    $view->current = array_get($user->info, 'defaultMarkup');
  }

  isset($view->current) or $view->current = head($view->markups);
});

View::composer('habravel::part.post', function ($view) {
  $post = $view->post;

  isset($post->x_parent) or $post->x_parent = $post->parentPost();
  isset($post->x_author) or $post->x_author = $post->author();
  isset($post->x_tags) or $post->x_tags = $post->tags()->get();

  isset($view->classes) or $view->classes = '';
  $post->sourceURL and $view->classes .= ' hvl-post-sourced';
  $post->score > 0 and $view->classes .= ' hvl-post-above';
  $post->score < 0 and $view->classes .= ' hvl-post-below';

  isset($view->canEdit) or $view->canEdit = ($user = Core::user() and $post->isEditable($user));
});

View::composer('habravel::part.comment', function ($view) {
  $post = $view->post;

  isset($post->x_top) or $post->x_top = $post->top();
  isset($post->x_author) or $post->x_author = $post->author();
  isset($post->x_children) or $post->x_children = array();

  isset($view->canEdit) or $view->canEdit = ($user = Core::user() and $post->isEditable($user));
});

View::composer('habravel::page', function ($view) {
  isset($view->pageTitle) or $view->pageTitle = trans('habravel::g.pageTitle');
});
