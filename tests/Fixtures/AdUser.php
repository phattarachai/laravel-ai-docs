<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The suite's authenticatable. The panel never touches the host's user model
 * beyond `$request->user()`, so a four-column stand-in is the whole contract.
 */
final class AdUser extends Authenticatable
{
    protected $table = 'ad_users';

    protected $guarded = [];

    public $timestamps = false;
}
