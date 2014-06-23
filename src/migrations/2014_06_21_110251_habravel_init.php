<?php

class HabravelInit extends Illuminate\Database\Migrations\Migration {
  function up() {
    Schema::create('polls', function ($table) {
      $table->increments('id');
      $table->timestamps();
      $table->string('target', 50);
      $table->text('caption');
      $table->boolean('multiple');
    });

    Schema::create('poll_options', function ($table) {
      $table->increments('id');
      $table->timestamps();
      $table->integer('poll')->unsigned();
      $table->text('caption');

      $table->foreign('poll')->references('id')->on('polls')
        ->onUpdate('cascade')->onDelete('cascade');
    });

    Schema::create('users', function ($table) {
      $table->increments('id');
      $table->timestamps();
      $table->string('password', 256);
      $table->string('remember_token', 200);
      $table->string('email', 200);
      $table->string('name', 50);
      $table->text('info');
      $table->integer('poll')->unsigned()->nullable();
      $table->integer('score')->default(0);
      $table->integer('rating')->default(0);
      $table->char('regIP', 16);
      $table->timestamp('loginTime');
      $table->char('loginIP', 16)->default('');
      $table->text('flags');
      $table->string('avatar', 200)->default('');

      $table->unique('email');
      $table->unique('name');
      $table->index('rating');

      $table->foreign('poll')->references('id')->on('polls')
        ->onUpdate('cascade')->onDelete('set null');
    });

    Schema::create('poll_votes', function ($table) {
      $table->timestamps();
      $table->integer('poll')->unsigned();
      $table->integer('option')->unsigned();
      $table->integer('user')->unsigned();
      $table->char('ip', 16);

      $table->primary(array('poll', 'user'));

      $table->foreign('poll')->references('id')->on('polls')
        ->onUpdate('cascade')->onDelete('cascade');

      $table->foreign('option')->references('id')->on('poll_options')
        ->onUpdate('cascade')->onDelete('cascade');

      $table->foreign('user')->references('id')->on('users')
        ->onUpdate('cascade')->onDelete('cascade');
    });

    Schema::create('posts', function ($table) {
      $table->increments('id');
      $table->timestamps();
      $table->integer('parent')->unsigned()->nullable()->default(null);
      $table->string('url', 50);
      $table->integer('author')->unsigned();
      $table->integer('poll')->unsigned()->nullable();
      $table->integer('score')->default(0);
      $table->integer('views')->unsigned()->default(0);
      $table->text('info');
      $table->text('sourceURL');
      $table->string('sourceName', 100)->default('');
      $table->string('caption', 150);
      $table->string('markup', 50);
      $table->text('text');
      $table->text('html');
      $table->text('introHTML');
      $table->text('flags');
      $table->timestamp('listTime');
      $table->timestamp('pubTime');

      $table->unique('url');
      //$table->index('author');    // implied by foreign key.
      $table->index(array('score', 'listTime'));
      $table->index('listTime');
      $table->index('sourceName');

      $table->foreign('parent')->references('id')->on('posts')
        ->onUpdate('cascade')->onDelete('cascade');

      $table->foreign('poll')->references('id')->on('polls')
        ->onUpdate('cascade')->onDelete('set null');

      $table->foreign('author')->references('id')->on('users')
        ->onUpdate('cascade')->onDelete('cascade');
    });

    Schema::create('tags', function ($table) {
      $table->increments('id');
      $table->timestamps();
      $table->integer('parent')->unsigned()->nullable()->default(null);
      $table->string('type', 50)->default('');
      $table->string('caption', 50);
      $table->text('flags');

      $table->index('type');
      $table->unique('caption');

      $table->foreign('parent')->references('id')->on('tags')
        ->onUpdate('cascade')->onDelete('set null');
    });

    Schema::create('post_tag', function ($table) {
      $table->integer('post_id')->unsigned();
      $table->integer('tag_id')->unsigned();
      $table->primary(array('post_id', 'tag_id'));
    });
  }

  function down() {
    Schema::drop('polls');
    Schema::drop('poll_options');
    Schema::drop('users');
    Schema::drop('poll_votes');
    Schema::drop('posts');
    Schema::drop('tags');
    Schema::drop('post_tag');
  }
}