<?php

namespace Phunky\LaravelMessagingAttachments\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Phunky\LaravelMessaging\Contracts\Messageable;
use Phunky\LaravelMessaging\Traits\HasMessaging;

class User extends Model implements Messageable
{
    use HasMessaging;

    protected $table = 'users';

    protected $guarded = [];
}
