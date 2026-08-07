<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Tests;

use Override;

/**
 * The same host as {@see TestCase}, with a second tree hung off `.ai/tasks` and both
 * trees declared as panels. Routes are registered from config at boot, so a panel
 * layout cannot be switched on mid-test — it needs its own host.
 */
abstract class PanelsTestCase extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->link($app->basePath(), 'tasks', __DIR__.'/Fixtures/tasks');

        $app['config']->set('ai-docs.panels', [
            'docs' => ['root' => '.ai/documents', 'label' => 'Documents'],
            'tasks' => ['root' => '.ai/tasks', 'label' => 'Tasks'],
        ]);
    }
}
