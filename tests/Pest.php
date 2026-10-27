<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Phattarachai\AiDocs\Tests\Fixtures\AdUser;
use Phattarachai\AiDocs\Tests\NotesTestCase;
use Phattarachai\AiDocs\Tests\PanelsTestCase;
use Phattarachai\AiDocs\Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');
uses(PanelsTestCase::class, RefreshDatabase::class)->in('Panels');
uses(NotesTestCase::class, RefreshDatabase::class)->in('Notes');

/**
 * A signed-in user the gate accepts.
 */
function adUser(): AdUser
{
    return AdUser::query()->firstOrCreate(
        ['email' => 'reader@example.test'],
        ['name' => 'Docs Reader', 'password' => bcrypt('secret')],
    );
}
