<?php namespace Habravel\Models;

class User extends BaseModel {
  protected static $rules = array(
    'password'            => 'required|min:6',
    'email'               => 'required|max:200|email|unique:users,email',
    'name'                => 'required|max:50|regex:~^\w[\w\d]+$~|unique:users,name',
    'info'                => '',
    'poll'                => 'exists:polls,id',
    'score'               => '%INT%',
    'rating'              => '%INT%',
    'regIP'               => 'ip',
    'loginTime'           => 'date|after:2000-01-01',
    'loginIP'             => 'ip',
    'flags'               => '',
    'avatar'              => 'max:200',
  );

  protected $attributes = array(
    'id'                  => 0,
    'password'            => '',    // hash.
    'remember_token'      => '',
    'email'               => '',
    'name'                => '',    // display nickname.
    'info'                => '',    // serialized.
    'poll'                => null,  // Poll id; for counting score.
    'score'               => 0,
    'rating'              => 0,
    'regIP'               => '',
    'loginTime'           => null,
    'loginIP'             => '',
    'flags'               => '',    // '[group.perm][foo.bar]'.
    'avatar'              => '',    // 'pub/path.jpg'.
  );

  static function rules(User $model = null) {
    $rules = parent::rules();

    if ($model) {
      $rules['email'] .= ','.$model->id;
      $rules['name'] .= ','.$model->id;
    }

    return $rules;
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

  function posts() {
    return $this->hasMany(__NAMESPACE__.'\\Post', 'author');
  }

  function votes() {
    return $this->hasMany(__NAMESPACE__.'\\PollVote', 'user');
  }

  function flags() {
    $flags = $this->flags;
    if ($flags === '-') {
      return array();
    } elseif ($flags === '') {
      return \Config::get('habravel::g.userPerms');
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