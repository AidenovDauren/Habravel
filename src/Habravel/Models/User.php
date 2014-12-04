<?php namespace Habravel\Models;

class User extends BaseModel {
  protected static $rules = array(
    'password'            => 'required|min:',
    'email'               => 'required|max:200|email|unique:users,email',
    'name'                => 'required|max:50|regex:~^\w[\w\d]+$~|unique:users,name',
    'poll'                => 'exists:polls,id',
    'score'               => '%INT%',
    'rating'              => '%INT%',
    'regIP'               => 'ip',
    'loginTime'           => 'date|after:2000-01-01',
    'loginIP'             => 'ip',
    'flags'               => '',
    'avatar'              => 'max:200',
    'site'                => 'url|max:128',
    'bitbucket'           => 'regex:~^https?://~|max:128',
    'github'              => 'regex:~^https?://~|max:128',
    'facebook'            => 'regex:~^https?://~|max:128',
    'twitter'             => 'regex:~^https?://~|max:128',
    'vk'                  => 'regex:~^https?://~|max:128',
    'jabber'              => 'email|max:128',
    'skype'               => 'max:64',
    'icq'                 => '%INT%',
    'info'                => 'max:5000',
  );

  static $avatarImageRule = array(
    'avatar'              => 'required|mimes:jpeg,gif,png|max:200',
  );

  static $changePasswordRule = array(
    'hash'                => 'accepted',
    'newPassword'         => 'required|confirmed|min:',
  );

  protected $attributes = array(
    'id'                  => 0,
    'password'            => '',    // hash.
    'remember_token'      => '',
    'email'               => '',
    'name'                => '',    // display nickname.
    'poll'                => null,  // Poll id; for counting score.
    'score'               => 0,
    'rating'              => 0,
    'regIP'               => '',
    'loginTime'           => null,
    'loginIP'             => '',
    'flags'               => '',    // '[group.perm][foo.bar]'.
    'avatar'              => '',    // 'pub/path.jpg'.
    'site'                => '',
    'bitbucket'           => '',
    'github'              => '',
    'facebook'            => '',
    'twitter'             => '',
    'vk'                  => '',
    'jabber'              => '',
    'skype'               => '',
    'icq'                 => '',
    'info'                => '',
  );

  static function rules(User $model = null) {
    $rules = parent::rules();
    $rules['password'] .= \Config::get('habravel::g.minPassword');

    if ($model) {
      $rules['email'] .= ','.$model->id;
      $rules['name'] .= ','.$model->id;
    }

    return $rules;
  }

  // Virtual attributes

  function getSiteLinkAttribute() {
    return $this->attributes['siteLink'] = \Habravel\externalUrl($this->site);
  }

  function getBitbucketLinkAttribute() {
    return $this->attributes['bitbucketLink'] = \Habravel\externalUrl($this->bitbucket, true);
  }

  function getGithubLinkAttribute() {
    return $this->attributes['githubLink'] = \Habravel\externalUrl($this->github, true);
  }

  function getFacebookLinkAttribute() {
    return $this->attributes['facebookLink'] = \Habravel\externalUrl($this->facebook, true);
  }

  function getTwitterLinkAttribute() {
    return $this->attributes['twitterLink'] = \Habravel\externalUrl($this->twitter, true);
  }

  function getVkLinkAttribute() {
    return $this->attributes['vkLink'] = \Habravel\externalUrl($this->vk, true);
  }

  function getJabberLinkAttribute() {
    return $this->attributes['JabberLink'] = \Habravel\jabberUrl($this->jabber);
  }

  function getSkypeLinkAttribute() {
    return $this->attributes['skypeLink'] = \Habravel\skypeUrl($this->skype);
  }

  function getDates() {
    $list = parent::getDates();
    $list[] = 'loginTime';
    return $list;
  }

  function setEmailAttribute($value) {
    $this->attributes['email'] = trim($value);
  }

  function setNameAttribute($value) {
    $this->attributes['name'] = trim($value);
  }

  // Published = all but drafts.
  function publishedArticles() {
    return $this->articles()->whereNotNull('listTime');
  }

  function drafts() {
    return $this->posts()->whereNull('listTime');
  }

  function articles() {
    return $this->posts()->whereTop(null);
  }

  function comments() {
    return $this->posts()->whereNotNull('top');
  }

  // Queries all posts that this user can see - everyone's published articles,
  // comments or his drafts.
  function allVisiblePosts() {
    $self = $this;

    return Post::where(function ($query) use ($self) {
      $query
        ->whereNotNull('listTime')
        ->orWhere('author', '=', $self->id);
    });
  }

  // This returns just all existing Post rows for this User including drafts and
  // comments (which are also posts).
  function posts() {
    return $this->hasMany(__NAMESPACE__.'\\Post', 'author');
  }

  function votes() {
    return $this->hasMany(__NAMESPACE__.'\\PollVote', 'user');
  }

  function flags() {
    $flags = (string) $this->flags;
    if ($flags === '-') {
      return array();
    } elseif ($flags === '') {
      return \Config::get('habravel::g.userPerms');
    } elseif ($flags[0] === '+') {
      return array_merge(\Config::get('habravel::g.userPerms'), parent::flags());
    } else {
      return parent::flags();
    }
  }

  function url($absolute = true) {
    return ($absolute ? \Habravel\url().'/' : '').'~'.urlencode($this->name);
  }

  function avatarURL() {
    $url = $this->avatar ?: 'default.png';
    return asset('packages/proger/habravel/avatars').'/'.$url;
  }

  function nameHTML(array $options = array()) {
    $options += array('link' => true);
    $html = \View::make('habravel::part.user', array('user' => $this) + $options);
    return preg_replace('~\s+</~u', '</', $html);
  }
}