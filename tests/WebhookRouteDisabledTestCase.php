<?php

namespace Lettermint\Laravel\Tests;

class WebhookRouteDisabledTestCase extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('lettermint.webhooks.enabled', false);
    }
}
